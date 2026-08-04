<?php

namespace App\Http\Controllers;

use App\Concerns\LogsModelActivity;
use App\Http\Resources\AuditLogEntry as AuditLogEntryResource;
use App\Models\Performance;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * The house's audit trail: every creation and state change
 * {@see LogsModelActivity} keeps for the application's main
 * models, newest first. Read-only, and open to the technicians alone — see
 * the route's `can:` middleware.
 */
class AuditLogController extends Controller
{
    /**
     * The permission — held by the "technician" role — that opens the audit
     * trail to its holder.
     */
    public const VIEW_PERMISSION = 'audit_log.view';

    /**
     * How many entries the feed shows. A running feed rather than an archive:
     * this is enough to see what has happened lately without the page needing
     * pagination of its own.
     */
    private const FEED_SIZE = 200;

    public function index(): Response
    {
        // Ordered by id rather than latest()'s created_at: two entries written
        // within the same second are not rare, and only id says which of them
        // actually came last.
        //
        // The subject rides along so the row can name the record rather than
        // just its id — see AuditLogEntry::subjectLabel(). A performance's own
        // format comes with it: an unnamed performance is labelled by its
        // format's name instead.
        $activities = Activity::query()
            ->with('causer', 'subject')
            ->orderByDesc('id')
            ->limit(self::FEED_SIZE)
            ->get()
            ->loadMorph('subject', [
                Performance::class => ['format'],
            ]);

        return Inertia::render('admin/audit-log/Index', [
            'entries' => AuditLogEntryResource::collection($activities)->resolve(),
        ]);
    }
}
