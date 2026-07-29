<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Sentry\Event;
use Sentry\State\HubInterface;
use Tests\TestCase;

class SentryIntegrationTest extends TestCase
{
    /**
     * Events the SDK tried to send during the test, collected via `before_send`.
     *
     * @var list<Event>
     */
    private array $capturedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->capturedEvents = [];

        config()->set('sentry.dsn', 'https://publickey@sentry.test/1');
        config()->set('sentry.traces_sample_rate', 1.0);
        config()->set('sentry.before_send', function (Event $event): ?Event {
            $this->capturedEvents[] = $event;

            // Returning null discards the event so nothing leaves the test suite...
            return null;
        });

        // The SDK boots before the config above is applied, so rebuild the hub. Resolving it
        // also re-registers it as the current hub used by the `Sentry\*` helper functions...
        $this->app->forgetInstance(HubInterface::class);
        $this->app->make(HubInterface::class);
    }

    public function test_unhandled_exceptions_are_reported_to_sentry(): void
    {
        Route::get('/test-sentry-reporting', function () {
            throw new RuntimeException('Exception from the Sentry integration test.');
        });

        $this->get('/test-sentry-reporting')->assertServerError();

        $messages = array_map(
            fn (Event $event) => $event->getExceptions()[0]?->getValue(),
            $this->capturedEvents
        );

        $this->assertContains('Exception from the Sentry integration test.', $messages);
    }

    public function test_the_sdk_captures_messages(): void
    {
        \Sentry\captureMessage('Message from the Sentry integration test.');

        $messages = array_map(fn (Event $event) => $event->getMessage(), $this->capturedEvents);

        $this->assertContains('Message from the Sentry integration test.', $messages);
    }

    public function test_pages_render_tracing_meta_tags_for_the_browser_sdk(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('name="sentry-trace"', false);
        $response->assertSee('name="baggage"', false);
    }
}
