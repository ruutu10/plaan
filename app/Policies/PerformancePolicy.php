<?php

namespace App\Policies;

use App\Models\Format;
use App\Models\Performance;
use App\Models\User;
use App\Services\JellyfinClient;
use App\Services\PlankaClient;

/**
 * Who may touch a format's dated performances: a member of the group that owns the
 * format, a member of the group playing the performance itself, or a technician
 * holding {@see Performance::EDIT_ALL_PERMISSION}. That permission is a right of
 * its own — it neither implies nor is implied by being allowed to edit the formats
 * themselves.
 *
 * Adding a performance is the exception: that stays with the format's own group,
 * so a guest troupe may correct its slot on somebody else's evening without
 * being able to put more of its own on the bill.
 */
class PerformancePolicy
{
    /**
     * Determine whether the user can list the performances of the given format:
     * a member of the format's own group, or of a group with a performance of its
     * own on the bill.
     */
    public function viewAny(User $user, Format $format): bool
    {
        return Performance::manageableFor($user, $format)
            || $format->performances()->editableBy($user)->exists();
    }

    /**
     * Determine whether the user can add a performance to the given format.
     */
    public function create(User $user, Format $format): bool
    {
        return Performance::manageableFor($user, $format);
    }

    /**
     * Determine whether the user can open the performance's own details page:
     * whoever may edit it, and whoever is on its staff — see
     * {@see Performance::viewableBy()}. Staff read the page; the edit, delete
     * and reminder buttons on it stay with {@see update()}.
     */
    public function view(User $user, Performance $performance): bool
    {
        return $performance->isViewableBy($user);
    }

    /**
     * Determine whether the user can update the performance.
     */
    public function update(User $user, Performance $performance): bool
    {
        return $performance->isEditableBy($user);
    }

    /**
     * Determine whether the user can delete the performance.
     */
    public function delete(User $user, Performance $performance): bool
    {
        return $performance->isEditableBy($user);
    }

    /**
     * Determine whether the user can set the Planka board being read again for
     * this performance.
     *
     * Narrower than editing it: the run is not confined to this performance, or
     * even to this format — it reads every card whose title matches and writes
     * whatever they announce — so it stays with the crew holding
     * {@see Performance::EDIT_ALL_PERMISSION} rather than with the group whose
     * night it happens to be. There is also nothing to read when no board is
     * configured, which is what keeps the button off a house without one.
     */
    public function reimportFromPlanka(User $user, Performance $performance): bool
    {
        return PlankaClient::isConfigured() && $user->can(Performance::EDIT_ALL_PERMISSION);
    }

    /**
     * Determine whether the user can say where this performance's recording is.
     *
     * Narrower than editing it, for the same reason as
     * {@see reimportFromPlanka()}: saying so writes to the house's own media
     * library, over metadata every group's recordings share a shelf with, so it
     * stays with the crew holding {@see Performance::EDIT_ALL_PERMISSION}
     * rather than with whichever group's night it happens to be. There is also
     * nothing to write to when no library is configured, which is what keeps
     * the field off a house without one.
     */
    public function linkRecording(User $user, Performance $performance): bool
    {
        return JellyfinClient::isConfigured() && $user->can(Performance::EDIT_ALL_PERMISSION);
    }

    /**
     * Determine whether the user can ask for this performance's plot summary to
     * be read off its recording's subtitles again.
     *
     * The crew's, like the link it is read through, and only for a recording
     * there is something to read: a linked item, and a model to read it with.
     */
    public function regeneratePlotSummary(User $user, Performance $performance): bool
    {
        return $this->linkRecording($user, $performance)
            && filled(config('services.anthropic.key'))
            && filled($performance->recording?->item_id);
    }

    /**
     * Determine whether the user can download this performance's recording.
     *
     * Wider than opening the night's page — which already lets in its whole
     * staff, the people on stage among them: the authors of its plans are
     * written to about the video too, and may be on no staff and in no team
     * of the night. Only an approved plan counts, since anybody with a login
     * can file one against any night.
     */
    public function downloadRecording(User $user, Performance $performance): bool
    {
        if (! JellyfinClient::isConfigured()) {
            return false;
        }

        return $this->view($user, $performance)
            || $performance->technicalPlans()->approved()->whereBelongsTo($user)->exists();
    }
}
