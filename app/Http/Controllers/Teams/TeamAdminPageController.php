<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Inertia shells of the team-management screens. Neither carries any team
 * data: the pages fetch what they need from {@see TeamAdminController}'s JSON
 * API. A shell is still refused up front when the user may not reach the team,
 * so a forbidden link fails on the page rather than a step later in the
 * browser.
 */
class TeamAdminPageController extends Controller
{
    /**
     * Render the list of teams.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Team::class);

        return Inertia::render('admin/teams/Index');
    }

    /**
     * Render the edit form of a single team.
     */
    public function edit(Team $team): Response
    {
        Gate::authorize('view', $team);

        return Inertia::render('admin/teams/Edit', [
            'teamId' => $team->id,
        ]);
    }
}
