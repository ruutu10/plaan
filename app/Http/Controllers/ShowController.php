<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shows\SaveShowRequest;
use App\Http\Resources\Show as ShowResource;
use App\Models\Show;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The JSON API behind show management: the shows the signed-in user's groups
 * have staged, and what a show is called, is about and belongs to. Holders of
 * {@see Show::EDIT_ALL_PERMISSION} manage the whole house.
 *
 * The pages are shells rendered by {@see ShowPageController}; every byte they
 * list or save passes through here.
 */
class ShowController extends Controller
{
    /**
     * List the shows the user may edit.
     *
     * @return AnonymousResourceCollection<int, ShowResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Show::class);

        $shows = Show::query()
            ->with('team')
            ->withCount('performances')
            ->editableBy($request->user())
            ->orderByRaw('LOWER(shows.name)')
            ->get();

        // The groups ride along: the listing is where a new show is entered,
        // and it has to offer the same choice of owners the edit form does.
        return ShowResource::collection($shows)->additional([
            'teams' => $this->assignableTeams($request->user()),
        ]);
    }

    /**
     * Return a single show, together with the groups it may be handed to — the
     * edit form needs both and one round trip is enough for them.
     */
    public function show(Request $request, Show $show): ShowResource
    {
        Gate::authorize('view', $show);

        return ShowResource::make($show->load('team'))->additional([
            'teams' => $this->assignableTeams($request->user()),
        ]);
    }

    /**
     * Enter a new show by hand, for a group the user may file it under.
     */
    public function store(SaveShowRequest $request): JsonResponse
    {
        $show = Show::create($request->validated());

        return ShowResource::make($show->load('team'))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Update the show's data fields and return it as it now stands.
     */
    public function update(SaveShowRequest $request, Show $show): ShowResource
    {
        $show->update($request->validated());

        return ShowResource::make($show->load('team'));
    }

    /**
     * Put the show aside. It is soft-deleted, taking its stagings with it (see
     * {@see Show::booted()}), so the plans written for them keep their trail.
     */
    public function destroy(Show $show): Response
    {
        Gate::authorize('delete', $show);

        $show->delete();

        return response()->noContent();
    }

    /**
     * The groups the user may file a show under, as the forms offer them.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function assignableTeams(User $user): Collection
    {
        return Show::assignableTeams($user)
            ->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
            ])
            ->values();
    }
}
