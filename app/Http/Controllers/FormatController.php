<?php

namespace App\Http\Controllers;

use App\Console\Commands\ImportPlankaPerformances;
use App\Http\Requests\Formats\SaveFormatRequest;
use App\Http\Resources\Format as FormatResource;
use App\Models\Format;
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
 * The JSON API behind format management: the formats the signed-in user's groups
 * have staged, and what a format is called, is about and belongs to. Holders of
 * {@see Format::EDIT_ALL_PERMISSION} manage the whole house.
 *
 * The pages are shells rendered by {@see FormatPageController}; every byte they
 * list or save passes through here.
 */
class FormatController extends Controller
{
    /**
     * List the formats the user may open — their groups' own, and the evenings
     * their groups merely have a performance on.
     *
     * @return AnonymousResourceCollection<int, FormatResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Format::class);

        $formats = Format::query()
            // The reading that made each format rides along: the listing offers
            // it as a button, and asking per row would be a query per row.
            ->with(['team', 'reasoningLogs'])
            ->withCount('performances')
            ->visibleTo($request->user())
            ->orderByRaw('LOWER(formats.name)')
            ->get();

        // The groups ride along: the listing is where a new format is entered,
        // and it has to offer the same choice of owners the edit form does.
        return FormatResource::collection($formats)->additional([
            'teams' => $this->assignableTeams($request->user()),
        ]);
    }

    /**
     * Return a single format, together with the groups it may be handed to — the
     * edit form needs both and one round trip is enough for them.
     */
    public function show(Request $request, Format $format): FormatResource
    {
        Gate::authorize('view', $format);

        return FormatResource::make($format->load(['team', 'reasoningLogs']))->additional([
            'teams' => $this->assignableTeams($request->user()),
        ]);
    }

    /**
     * Enter a new format by hand, for a group the user may file it under.
     */
    public function store(SaveFormatRequest $request): JsonResponse
    {
        $format = Format::create($request->validated());

        Log::info('Format created', [
            'format_id' => $format->id,
            'team_id' => $format->team_id,
            'user_id' => $request->user()->id,
        ]);

        return FormatResource::make($format->load('team'))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Update the format's data fields and return it as it now stands.
     */
    public function update(SaveFormatRequest $request, Format $format): FormatResource
    {
        $format->fill($request->validated());

        // Which fields moved, not what they moved to: a format handed to another
        // group is the change that decides who may still reach it.
        $changed = array_keys($format->getDirty());

        $format->save();

        Log::info('Format updated', [
            'format_id' => $format->id,
            'team_id' => $format->team_id,
            'user_id' => $request->user()->id,
            'changed' => $changed,
        ]);

        return FormatResource::make($format->load('team'));
    }

    /**
     * Put the format aside, or wipe it outright.
     *
     * Put aside is the usual answer and the default the screen offers: the
     * format is soft-deleted, taking its performances with it (see
     * {@see Format::booted()}), so the plans written for them keep their trail —
     * and the row left behind is what keeps the Planka import from announcing
     * the same format all over again next week (see
     * {@see ImportPlankaPerformances::primeFormats()}).
     *
     * `force` asks for the other answer: the row goes, and with it — by the
     * database's own cascade — every performance of the format and every plan
     * written for those. Nothing is left for the import to recognise, so a
     * format still on the board comes back on the next run, which is the point
     * of asking for it.
     */
    public function destroy(Request $request, Format $format): Response
    {
        Gate::authorize('delete', $format);

        $permanently = $request->boolean('force');

        // Counted before the delete: deleting a format takes its performances
        // with it, and that reach is the point of the record. A wipe reaches
        // the ones already put aside too, so those are counted as well.
        $performances = $permanently
            ? $format->performances()->withTrashed()->count()
            : $format->performances()->count();

        $permanently ? $format->forceDelete() : $format->delete();

        Log::notice('Format deleted', [
            'format_id' => $format->id,
            'team_id' => $format->team_id,
            'user_id' => $request->user()->id,
            'performances_deleted' => $performances,
            'permanently' => $permanently,
        ]);

        return response()->noContent();
    }

    /**
     * The groups the user may file a format under, as the forms offer them.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function assignableTeams(User $user): Collection
    {
        return Format::assignableTeams($user)
            ->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
            ])
            ->values();
    }
}
