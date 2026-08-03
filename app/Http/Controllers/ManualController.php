<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\Extension\Table\TableExtension;
use Spatie\LaravelMarkdown\MarkdownRenderer;

/**
 * The user manual, served straight from the MANUAL.md that ships with the
 * source. Keeping one copy means the document the developers keep current is
 * the document the house reads — there is nothing to publish or synchronise.
 */
class ManualController extends Controller
{
    /**
     * Render the manual as HTML.
     */
    public function __invoke(MarkdownRenderer $renderer): Response
    {
        // The manual leans on GFM tables (who does what, which reminder goes
        // out when); CommonMark on its own would print their pipes verbatim.
        $html = $renderer
            ->addExtension(new TableExtension)
            ->toHtml((string) file_get_contents(base_path('MANUAL.md')));

        return Inertia::render('Manual', [
            'html' => $html,
        ]);
    }
}
