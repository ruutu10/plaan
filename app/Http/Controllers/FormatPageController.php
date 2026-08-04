<?php

namespace App\Http\Controllers;

use App\Models\Show;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Inertia shells of the show-management screens. Neither carries any show
 * data: the pages fetch what they need from {@see ShowController}'s JSON API.
 * A shell is still refused up front when the user may not have the show, so a
 * forbidden link fails on the page rather than a step later in the browser.
 */
class ShowPageController extends Controller
{
    /**
     * Render the list of shows.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Show::class);

        return Inertia::render('shows/Index');
    }

    /**
     * Render the edit form of a single show.
     */
    public function edit(Show $show): Response
    {
        Gate::authorize('view', $show);

        return Inertia::render('shows/Edit', [
            'showId' => $show->id,
        ]);
    }
}
