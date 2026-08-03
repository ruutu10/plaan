<?php

namespace App\Http\Controllers\Users;

use App\Actions\GrantStaffAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\AssignRoleRequest;
use App\Http\Resources\AdminUser as AdminUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/**
 * The JSON API behind which roles an account holds. Both routes are nested
 * under the account, so a grant is only ever written through the account it is
 * written on, and both answer with the account as it now stands — the screen
 * shows what the server did, not what was asked for.
 *
 * Handing out a role hands out every right it carries, so each write is logged
 * with who made it.
 */
class UserRoleController extends Controller
{
    /**
     * Grant a role. Granting one already held changes nothing, which is what
     * makes a double-clicked toggle harmless.
     */
    public function store(AssignRoleRequest $request, User $user): AdminUserResource
    {
        $role = $request->validated('role');

        $user->assignRole($role);

        Log::notice('Role granted from the account management screen', [
            'user_id' => $user->id,
            'role' => $role,
            'granted_by' => $request->user()->id,
        ]);

        return $this->reread($user);
    }

    /**
     * Take a role away. Taking away one the account never held changes
     * nothing, which — like granting one twice — is what makes a
     * double-clicked toggle harmless; see the route for how the role is looked
     * up to keep that true.
     *
     * The staff role is the one that comes back on its own: an account on one
     * of the theatre's own domains is taken on again the next time its address
     * is proven (see {@see GrantStaffAccess}). Taking it off here is undoing a
     * mistake, not shutting a colleague out for good.
     */
    public function destroy(Request $request, User $user, Role $role): AdminUserResource
    {
        Gate::authorize('updateRoles', $user);

        $user->removeRole($role);

        Log::notice('Role taken away from the account management screen', [
            'user_id' => $user->id,
            'role' => $role->name,
            'removed_by' => $request->user()->id,
        ]);

        return $this->reread($user);
    }

    /**
     * Read the account back with everything the edit screen lists it by, so a
     * written role lands in the same shape the page was loaded with.
     */
    private function reread(User $user): AdminUserResource
    {
        $user->load('roles');
        $user->loadCount('teams');

        return AdminUserResource::make($user);
    }
}
