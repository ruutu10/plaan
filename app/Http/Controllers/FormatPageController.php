<?php

namespace App\Http\Controllers;

use App\Models\Format;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Inertia shells of the format-management screens. Neither carries any format
 * data: the pages fetch what they need from {@see FormatController}'s JSON API.
 * A shell is still refused up front when the user may not have the format, so a
 * forbidden link fails on the page rather than a step later in the browser.
 */
class FormatPageController extends Controller
{
    /**
     * Render the list of formats.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Format::class);

        return Inertia::render('formats/Index');
    }

    /**
     * Render the edit form of a single format.
     */
    public function edit(Format $format): Response
    {
        Gate::authorize('view', $format);

        return Inertia::render('formats/Edit', [
            'formatId' => $format->id,
        ]);
    }
}
