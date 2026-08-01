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
use Illuminate\Support\Facades\Log;
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
     * List the shows the user may open — their groups' own, and the evenings
     * their groups merely have a performance on.
     *
     * @return AnonymousResourceCollection<int, ShowResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Show::class);

        $shows = Show::query()
            // The reading that made each show rides along: the listing offers
            // it as a button, and asking per row would be a query per row.
            ->with(['team', 'reasoningLogs'])
            ->withCount('performances')
            ->visibleTo($request->user())
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

        return ShowResource::make($show->load(['team', 'reasoningLogs']))->additional([
            'teams' => $this->assignableTeams($request->user()),
        ]);
    }

    /**
     * Enter a new show by hand, for a group the user may file it under.
     */
    public function store(SaveShowRequest $request): JsonResponse
    {
        $show = Show::create($request->validated());

        Log::info('Show created', [
            'show_id' => $show->id,
            'team_id' => $show->team_id,
            'user_id' => $request->user()->id,
        ]);

        return ShowResource::make($show->load('team'))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Update the show's data fields and return it as it now stands.
     */
    public function update(SaveShowRequest $request, Show $show): ShowResource
    {
        $show->fill($request->validated());

        // Which fields moved, not what they moved to: a show handed to another
        // group is the change that decides who may still reach it.
        $changed = array_keys($show->getDirty());

        $show->save();

        Log::info('Show updated', [
            'show_id' => $show->id,
            'team_id' => $show->team_id,
            'user_id' => $request->user()->id,
            'changed' => $changed,
        ]);

        return ShowResource::make($show->load('team'));
    }

    /**
     * Put the show aside. It is soft-deleted, taking its performances with it (see
     * {@see Show::booted()}), so the plans written for them keep their trail.
     */
    public function destroy(Request $request, Show $show): Response
    {
        Gate::authorize('delete', $show);

        // Counted before the delete: putting a show aside takes its
        // performances with it, and that reach is the point of the record.
        $performances = $show->performances()->count();

        $show->delete();

        Log::notice('Show deleted', [
            'show_id' => $show->id,
            'team_id' => $show->team_id,
            'user_id' => $request->user()->id,
            'performances_deleted' => $performances,
        ]);

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
