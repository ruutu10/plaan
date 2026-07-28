<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\DeleteTeam;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Http\Resources\Team as TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The JSON API behind team management: the groups the signed-in user may keep
 * straight, what they are called and who belongs to them. Holders of
 * {@see Team::EDIT_ALL_PERMISSION} manage the whole house.
 *
 * The pages are shells rendered by {@see TeamAdminPageController}; every byte
 * they list or save passes through here. Who belongs to a team is written
 * through {@see TeamAdminMemberController}.
 */
class TeamAdminController extends Controller
{
    /**
     * List the teams the user may reach.
     *
     * @return AnonymousResourceCollection<int, TeamResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Team::class);

        $teams = Team::query()
            ->withCount(['members', 'shows'])
            ->editableBy($request->user())
            ->orderByRaw('LOWER(teams.name)')
            ->get();

        return TeamResource::collection($teams)->additional([
            'roles' => TeamRole::assignable(),
        ]);
    }

    /**
     * Return a single team with the people in it, the roles they may be given
     * and what the reader may write — the edit page needs all of it and one
     * round trip is enough for them.
     *
     * Reaching a team is not the same as being allowed to change it: a plain
     * member sees their team here but writes nothing, which is why the page is
     * told rather than left to find out by being refused.
     */
    public function show(Team $team): TeamResource
    {
        Gate::authorize('view', $team);

        $team->loadCount('shows');
        $team->load(['members' => fn ($query) => $query->orderByRaw('LOWER(users.name)')]);

        return TeamResource::make($team)->additional([
            'roles' => TeamRole::assignable(),
            'permissions' => [
                'canUpdate' => Gate::allows('update', $team),
                'canAddMember' => Gate::allows('addMember', $team),
                'canUpdateMember' => Gate::allows('updateMember', $team),
                'canRemoveMember' => Gate::allows('removeMember', $team),
            ],
        ]);
    }

    /**
     * Start a new team. Whoever enters it owns it — a team without an owner is
     * one nobody could keep straight afterwards — but the user is left working
     * in the team they were in, since this is somebody else's group as often
     * as it is their own.
     */
    public function store(SaveTeamRequest $request, CreateTeam $createTeam): JsonResponse
    {
        $team = $createTeam->handle($request->user(), $request->validated('name'), switch: false);

        return TeamResource::make($team->loadCount(['members', 'shows']))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Rename the team and return it as it now stands. The slug follows the
     * name (see {@see Team::boot()}), so the answer carries the new one.
     */
    public function update(SaveTeamRequest $request, Team $team): TeamResource
    {
        $team->update(['name' => $request->validated('name')]);

        return TeamResource::make($team->loadCount(['members', 'shows']));
    }

    /**
     * Put the team aside. It is soft-deleted, so what it staged keeps its
     * trail, but the memberships go and everyone standing in it is moved on.
     */
    public function destroy(Team $team, DeleteTeam $deleteTeam): Response
    {
        Gate::authorize('delete', $team);

        $deleteTeam->handle($team);

        return response()->noContent();
    }
}
