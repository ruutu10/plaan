<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shows\SaveShowRequest;
use App\Http\Resources\Show as ShowResource;
use App\Models\Show;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

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

        return ShowResource::collection($shows);
    }

    /**
     * Return a single show, together with the groups it may be handed to — the
     * edit form needs both and one round trip is enough for them.
     */
    public function show(Request $request, Show $show): ShowResource
    {
        Gate::authorize('view', $show);

        return ShowResource::make($show->load('team'))->additional([
            'teams' => Show::assignableTeams($request->user())
                ->map(fn (Team $team): array => [
                    'id' => $team->id,
                    'name' => $team->name,
                ])
                ->values(),
        ]);
    }

    /**
     * Update the show's data fields and return it as it now stands.
     */
    public function update(SaveShowRequest $request, Show $show): ShowResource
    {
        $show->update($request->validated());

        return ShowResource::make($show->load('team'));
    }
}
