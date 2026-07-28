<?php

namespace App\Services;

use Anthropic\Client;
use App\Http\Resources\TechnicalPlan as TechnicalPlanReviewResource;
use App\Models\TechnicalPlan;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelMarkdown\MarkdownRenderer;

class TechnicalPlanReviewer
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client(config('services.anthropic.key'));
    }

    /**
     * Ask the technician AI to review a plan and return its feedback.
     */
    public function review(TechnicalPlan $plan): string
    {
        $userPrompt = $this->buildUserPrompt($plan);

        $startedAt = microtime(true);

        Log::info('Requesting an AI review of a plan', [
            'plan_id' => $plan->id,
            'model' => config('services.anthropic.model'),
        ]);

        $message = $this->client->messages->create(
            maxTokens: config('services.anthropic.max_tokens'),
            messages: [
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
            model: config('services.anthropic.model'),
            system: $this->buildSystemPrompt(),
            temperature: 1,
            thinking: ['type' => 'disabled'],
        );
        // @phpstan-ignore-next-line property.notFound
        $aiResponse = trim((string) $message->content[0]->text);

        Log::debug('AI review for plan', [
            'id' => $plan->id,
            'userPrompt' => $userPrompt,
            'aiOutput' => $aiResponse,
        ]);

        // The call is the slow, paid-for part of the request; its duration is
        // what a report of "the review hangs" is checked against.
        Log::info('AI review returned', [
            'plan_id' => $plan->id,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'response_length' => mb_strlen($aiResponse),
        ]);

        if ($aiResponse === '') {
            Log::warning('AI review came back empty', ['plan_id' => $plan->id]);
        }

        return app(MarkdownRenderer::class)->toHtml($aiResponse);
    }

    /**
     * The reviewer persona and output instructions.
     */
    protected function buildSystemPrompt(): string
    {
        return view('technical-plan.ai-system-prompt')->render();
    }

    /**
     * The plan itself, rendered as JSON for the AI to reason about.
     */
    protected function buildUserPrompt(TechnicalPlan $plan): string
    {
        return json_encode(
            new TechnicalPlanReviewResource($plan),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: '{}';
    }
}
