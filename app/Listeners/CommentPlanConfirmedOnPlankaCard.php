<?php

namespace App\Listeners;

use App\Enums\TechnicalPlanStatus;
use App\Events\TechnicalPlanStatusChanged;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Services\PlankaClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Leave a word on the night's Planka card once a technician has confirmed its
 * plan — the crew plans the evening on the board, not in here. Only the
 * submitted-to-received move earns a comment; a performance kept by hand
 * rather than imported has no card to write on and is passed over.
 *
 * Whether we have spoken already is asked of the card itself:
 * the comment carrying this plan's share link is the record. That
 * keeps the board the single source of truth — a comment deleted there can be
 * written again — and it means a confirmation that failed to reach Planka is
 * simply retried the next time the plan is confirmed.
 *
 * Only production writes to the board at all: it is the house's real board,
 * shared with people who do not know a staging plan from a real one, and a
 * comment left there cannot be taken back by whoever was trying something out.
 */
class CommentPlanConfirmedOnPlankaCard
{
    public function __construct(protected PlankaClient $planka) {}

    public function handle(TechnicalPlanStatusChanged $event): void
    {
        if ($event->previousStatus !== TechnicalPlanStatus::Submitted || $event->newStatus !== TechnicalPlanStatus::Received) {
            return;
        }

        if (! app()->isProduction() || ! PlankaClient::isConfigured()) {
            return;
        }

        $plan = $event->plan;
        $cardId = $plan->performance?->planka_card_id;

        if (blank($cardId)) {
            Log::info('A confirmed plan has no Planka card to comment on', [
                'plan_id' => $plan->id,
                'performance_id' => $plan->performance_id,
            ]);

            return;
        }

        $planUrl = route('technical-plan.public', $plan);

        try {
            foreach ($this->planka->commentTexts($cardId) as $text) {
                if (str_contains($text, $planUrl)) {
                    Log::info('A confirmed plan was already announced on its Planka card', [
                        'plan_id' => $plan->id,
                        'card_id' => $cardId,
                    ]);

                    return;
                }
            }

            $this->planka->comment($cardId, $this->text($plan, $event->changedBy, $planUrl));
        } catch (Throwable $e) {
            Log::error('Could not comment a confirmed plan on its Planka card', [
                'plan_id' => $plan->id,
                'card_id' => $cardId,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        Log::info('Announced a confirmed plan on its Planka card', [
            'plan_id' => $plan->id,
            'card_id' => $cardId,
        ]);
    }

    /**
     * What the card is told: who wrote the plan, who confirmed it, and where
     * to read it. The share link comes last and unpunctuated — it is both the
     * thing a reader clicks and the mark this listener looks for before
     * writing, so nothing may run up against it.
     */
    protected function text(TechnicalPlan $plan, User $confirmedBy, string $planUrl): string
    {
        return sprintf(
            'Tehnikaplaan on esitatud (esitas %s) ja tehniku (%s) poolt kinnitatud. Plaani link: %s',
            $plan->user->name ?? $plan->performance->performerName() ?? 'teadmata',
            $confirmedBy->name,
            $planUrl,
        );
    }
}
