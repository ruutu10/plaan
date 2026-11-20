<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Models\TechnicalPlan;
use App\Services\TechnicalPlanCriticalFindings;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * The second pass over a submitted plan's review: what it is shown, and what
 * it makes of the answer.
 */
class TechnicalPlanCriticalFindingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The bodies sent to the API, in order.
     *
     * @var list<array<string, mixed>>
     */
    private array $sentBodies = [];

    public function test_the_second_pass_is_shown_the_plan_alongside_the_review(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create([
            'scenes' => [
                ['id' => 'stseen-1', 'name' => 'Suur finaal', 'light' => '', 'sounds' => [], 'sound' => 'Lõpumuusika', 'notes' => ''],
            ],
        ]);

        $this->siftingAnswering('{"criticalFindings": []}')
            ->findIn($plan, 'Stseenil „Suur finaal" pole helifaili.');

        $userMessage = $this->sentBodies[0]['messages'][0]['content'];

        $this->assertStringContainsString('<plan>', $userMessage);
        $this->assertStringContainsString('"name": "Suur finaal"', $userMessage);
        $this->assertStringContainsString('"sound": "Lõpumuusika"', $userMessage);
        $this->assertStringContainsString("<review>\nStseenil „Suur finaal\" pole helifaili.\n</review>", $userMessage);
    }

    public function test_the_findings_come_back_as_a_list_of_lines(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $findings = $this->siftingAnswering((string) json_encode([
            'criticalFindings' => ['Lisa „Finaali" helifail.', '  ', 3],
        ]))
            ->findIn($plan, 'ülevaatus');

        $this->assertSame(['Lisa „Finaali" helifail.', '3'], $findings);
    }

    /**
     * Asking the model to write out why it kept or dropped each finding got an
     * answer declined as reasoning extraction on 2026-11-20. The reasons are
     * not asked for in the answer; the API's summary of the model's thinking
     * is logged in their place.
     */
    public function test_the_model_is_not_asked_to_write_out_its_reasons_and_its_thinking_is_logged_instead(): void
    {
        Log::spy();

        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->siftingAnswering('', message: [
            'content' => [
                ['type' => 'thinking', 'thinking' => 'Suits on plaanis, aga keelatud.', 'signature' => 'sig'],
                ['type' => 'text', 'text' => '{"criticalFindings": ["Eemalda suits."]}'],
            ],
        ])->findIn($plan, 'ülevaatus');

        $schema = $this->sentBodies[0]['output_config']['format']['schema'];

        $this->assertSame(['criticalFindings'], array_keys($schema['properties']));
        $this->assertSame(['criticalFindings'], $schema['required']);
        $this->assertStringNotContainsString('reasoningNotes', json_encode($this->sentBodies[0]['system']) ?: '');

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context = []): bool => $message === 'Sifted an AI review for show-stoppers'
                && $context['findings'] === 1
                && $context['thinking'] === 'Suits on plaanis, aga keelatud.'
                && ! array_key_exists('reasoningNotes', $context),
        )->once();
    }

    public function test_an_empty_review_is_not_sent_at_all(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->assertSame([], $this->siftingAnswering('{}')->findIn($plan, '   '));
        $this->assertSame([], $this->sentBodies);
    }

    public function test_an_unreadable_answer_is_thrown_rather_than_read_as_nothing_found(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->expectException(RuntimeException::class);

        $this->siftingAnswering('not json')->findIn($plan, 'ülevaatus');
    }

    public function test_an_unreadable_answer_is_not_cached(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();
        $sifting = $this->siftingAnswering('{"criticalFindings": ["Lisa', cached: true);

        foreach ([1, 2] as $attempt) {
            try {
                $sifting->findIn($plan, 'ülevaatus');
                $this->fail("An unreadable answer should have been thrown on attempt {$attempt}.");
            } catch (RuntimeException) {
                //
            }
        }

        $this->assertCount(2, $this->sentBodies, 'A retry must ask the model again, not get the same broken answer.');
    }

    public function test_a_readable_answer_is_cached(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();
        $sifting = $this->siftingAnswering('{"criticalFindings": []}', cached: true);

        $sifting->findIn($plan, 'ülevaatus');
        $sifting->findIn($plan, 'ülevaatus');

        $this->assertCount(1, $this->sentBodies);
    }

    /**
     * What happened on 2026-11-20: the findings were written out in full, the
     * answer was declined part-way through the notes after them, and the cut-off
     * JSON was read as "nothing show-stopping". The partial text is not handed
     * on or cached, and the sifting fails out loud.
     */
    public function test_a_declined_answer_fails_and_is_asked_again_even_with_findings_written_before_the_decline(): void
    {
        Log::spy();
        Exceptions::fake();

        $plan = TechnicalPlan::factory()->submitted()->create();
        $partial = '{"criticalFindings":["Suitsumasin on valitud, aga suitsu kasutada ei saa."],"reasoningNotes":["Suits: smoke=yes on plaan';

        $sifting = $this->siftingAnswering($partial, cached: true, message: [
            'stop_reason' => 'refusal',
            'stop_details' => ['type' => 'refusal', 'category' => 'reasoning_extraction', 'explanation' => 'Blocked.'],
        ]);

        foreach ([1, 2] as $attempt) {
            try {
                $sifting->findIn($plan, 'ülevaatus');
                $this->fail("A declined answer should have been thrown on attempt {$attempt}.");
            } catch (RuntimeException) {
                //
            }
        }

        $this->assertCount(2, $this->sentBodies, 'A declined answer is not cached.');

        Log::shouldHaveReceived('warning')->with('Claude declined to answer', [
            'service' => TechnicalPlanCriticalFindings::class,
            'model' => 'claude-sonnet-5-5',
            'category' => 'reasoning_extraction',
            'explanation' => 'Blocked.',
            'discarded_chars' => mb_strlen($partial),
        ])->twice();

        // The partial text never reaches the parser: it sees no answer at all.
        Log::shouldHaveReceived('warning')->with('The second pass over an AI review came back unreadable', ['aiOutput' => ''])->twice();

        // Reported, so it reaches Sentry and can be alerted on.
        Exceptions::assertReportedCount(2);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage()
            === 'Claude declined to answer for TechnicalPlanCriticalFindings (reasoning_extraction)');
    }

    public function test_an_answered_question_reports_nothing(): void
    {
        Exceptions::fake();

        $plan = TechnicalPlan::factory()->submitted()->create();

        $this->siftingAnswering('{"criticalFindings": []}')->findIn($plan, 'ülevaatus');

        Exceptions::assertNothingReported();
    }

    /**
     * A real second pass talking to a transport that always answers with the
     * given text, recording what it was sent.
     *
     * @param  bool  $cached  Whether the answer cache stays on, as in production.
     * @param  array<string, mixed>  $message  Message fields laid over an ordinary finished answer.
     */
    private function siftingAnswering(string $text, bool $cached = false, array $message = []): TechnicalPlanCriticalFindings
    {
        $sentBodies = &$this->sentBodies;

        $transporter = new class($text, $message, $sentBodies) implements ClientInterface
        {
            /**
             * @param  array<string, mixed>  $message
             * @param  list<array<string, mixed>>  $sentBodies
             */
            public function __construct(private string $text, private array $message, private array &$sentBodies)
            {
                //
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                /** @var array<string, mixed> $body */
                $body = json_decode((string) $request->getBody(), true) ?: [];
                $this->sentBodies[] = $body;

                return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                    'id' => 'msg_test',
                    'type' => 'message',
                    'role' => 'assistant',
                    'model' => 'claude-sonnet-5-5',
                    'content' => [['type' => 'text', 'text' => $this->text]],
                    'stop_reason' => 'end_turn',
                    'stop_sequence' => null,
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                    ...$this->message,
                ]));
            }
        };

        $sifting = new TechnicalPlanCriticalFindings(
            new Client(apiKey: 'test-key', requestOptions: ['transporter' => $transporter]),
        );

        return $cached ? $sifting : $sifting->withoutClaudeCache();
    }
}
