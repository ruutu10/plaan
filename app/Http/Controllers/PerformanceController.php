<?php

namespace App\Http\Controllers;

use App\Http\Requests\Performances\SavePerformanceRequest;
use App\Http\Resources\Performance as PerformanceResource;
use App\Models\Performance;
use App\Models\Show;
use App\Policies\PerformancePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The JSON API behind a show's dated stagings. Every route is nested under the
 * show and the bindings are scoped to it, so a staging is only ever reachable
 * through the show it belongs to.
 *
 * Who may write here is settled by {@see PerformancePolicy}.
 */
class PerformanceController extends Controller
{
    /**
     * List the show's stagings, soonest first.
     *
     * @return AnonymousResourceCollection<int, PerformanceResource>
     */
    public function index(Request $request, Show $show): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Performance::class, $show]);

        $performances = $show->performances()
            ->withCount('technicalPlans')
            ->orderBy('date')
            ->get();

        return PerformanceResource::collection($performances);
    }

    /**
     * Add a staging to the show.
     */
    public function store(SavePerformanceRequest $request, Show $show): JsonResponse
    {
        $performance = $show->performances()->create($request->validated());

        return PerformanceResource::make($performance->loadCount('technicalPlans'))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Update one of the show's stagings.
     */
    public function update(SavePerformanceRequest $request, Show $show, Performance $performance): PerformanceResource
    {
        $performance->update($request->validated());

        return PerformanceResource::make($performance->loadCount('technicalPlans'));
    }

    /**
     * Delete one of the show's stagings. The technical plans written for it are
     * not deleted with it — they are left without a staging, the column being
     * nulled, which is why the screen warns when there are any.
     */
    public function destroy(Show $show, Performance $performance): Response
    {
        Gate::authorize('delete', $performance);

        $performance->delete();

        return response()->noContent();
    }
}
