<?php

namespace App\Http\Controllers\Users;

use App\Actions\FindOrCreateUserByEmail;
use App\Actions\GrantStaffAccess;
use App\Enums\SignupSource;
use App\Events\UserProvisioned;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\CreateUserRequest;
use App\Http\Requests\Users\SaveUserRequest;
use App\Http\Resources\AdminRole as AdminRoleResource;
use App\Http\Resources\AdminUser as AdminUserResource;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Account management: every account in the house, what it is called and where
 * it came from. Unlike formats and teams, nothing here is scoped to what the
 * reader belongs to — either they hold {@see User::MANAGE_PERMISSION} and see
 * the house, or they are refused. That is one flat right with no per-account
 * shading to it, so the routes guard the whole controller with `can:` and
 * nothing below asks a second time.
 *
 * The two screens are shells: {@see overview()} and {@see edit()} carry no
 * account data, and everything they list or save travels over the JSON actions
 * beneath them. Which roles an account holds is written through
 * {@see UserRoleController}.
 */
class UserAdminController extends Controller
{
    public function __construct(private FindOrCreateUserByEmail $findOrCreateUser) {}

    /**
     * Render the list of accounts.
     */
    public function overview(): InertiaResponse
    {
        return Inertia::render('admin/users/Index');
    }

    /**
     * Render the edit screen of a single account.
     */
    public function edit(User $user): InertiaResponse
    {
        return Inertia::render('admin/users/Edit', [
            'userId' => $user->id,
        ]);
    }

    /**
     * List every account, the roles it holds and how many groups it stands in.
     *
     * @return AnonymousResourceCollection<int, AdminUserResource>
     */
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('roles')
            ->withCount('teams')
            ->orderByRaw('LOWER(users.name)')
            ->get();

        return AdminUserResource::collection($users);
    }

    /**
     * Create an account from nothing but an address, and — unless the
     * technician said not to — mail its owner a one-time link in.
     *
     * The address stays unproven until that link is followed — see
     * {@see UserProvisioned} — so a mistyped house address is not taken on as
     * staff merely because a technician entered it.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->findOrCreateUser->handle($request->validated('email'), SignupSource::AdminCreated);

        Log::notice('Account created from the management screen', [
            'user_id' => $user->id,
            'created_by' => $request->user()->id,
            'welcome_sent' => $request->sendsWelcome(),
        ]);

        UserProvisioned::dispatch($user, $request->user(), $request->sendsWelcome());

        $user->load('roles');
        $user->loadCount('teams');

        return AdminUserResource::make($user)
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Return a single account with the roles it holds and every role that could
     * be granted to it — the edit screen needs both, and one round trip is
     * enough for them.
     *
     * The one thing a reader who got this far may still be refused is the
     * account's roles, which is why that alone is answered: a technician reaches
     * their own account here like any other, but writes its roles nowhere. See
     * {@see UserPolicy::updateRoles()}.
     */
    public function show(User $user): AdminUserResource
    {
        $user->load('roles');
        $user->loadCount('teams');

        return AdminUserResource::make($user)->additional([
            'roles' => AdminRoleResource::collection(Role::orderBy('name')->get()),
            'permissions' => [
                'canUpdateRoles' => Gate::allows('updateRoles', $user),
            ],
        ]);
    }

    /**
     * Correct the account's name and address.
     *
     * A changed address is unproven again, exactly as it is when the owner
     * changes it themselves: it decides where password resets and magic links
     * go, and — through {@see GrantStaffAccess} — whether a house
     * domain is taken on as staff. Typing a colleague's address into somebody
     * else's account must not hand them that.
     */
    public function update(SaveUserRequest $request, User $user): AdminUserResource
    {
        $user->fill($request->validated());

        $emailChanged = $user->isDirty('email');
        $previousEmail = $user->getOriginal('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $changed = array_keys($user->getDirty());

        $user->save();

        $emailChanged
            ? Log::notice("A technician changed another account's e-mail address", [
                'user_id' => $user->id,
                'previous_email' => $previousEmail,
                'changed_by' => $request->user()->id,
            ])
            : Log::info('Account updated from the management screen', [
                'user_id' => $user->id,
                'changed' => $changed,
                'changed_by' => $request->user()->id,
            ]);

        $user->load('roles');
        $user->loadCount('teams');

        return AdminUserResource::make($user);
    }
}
