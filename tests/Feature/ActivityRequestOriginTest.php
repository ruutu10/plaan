<?php

namespace Tests\Feature;

use App\Http\Middleware\CaptureRequestOrigin;
use App\Http\Middleware\TrustCloudflareConnectingIp;
use App\Models\Format;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Every audit-trail entry says which address and browser the request behind it
 * came from — and an entry with no request behind it says nothing of the kind.
 */
class ActivityRequestOriginTest extends TestCase
{
    use RefreshDatabase;

    private const USER_AGENT = 'Mozilla/5.0 (Macintosh) Firefox/140.0';

    public function test_an_entry_written_during_a_request_carries_its_ip_and_user_agent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeader('User-Agent', self::USER_AGENT)
            ->postJson(route('api.formats.store'), [
                'team_id' => $this->teamOf($user)->id,
                'name' => 'Hooaja avaetendus',
            ])
            ->assertCreated();

        $format = Format::query()->where('name', 'Hooaja avaetendus')->firstOrFail();
        $activity = Activity::query()->forSubject($format)->forEvent('created')->sole();

        $this->assertSame('203.0.113.7', $activity->getProperty('ip'));
        $this->assertSame(self::USER_AGENT, $activity->getProperty('user_agent'));
    }

    public function test_a_request_through_the_cluster_proxy_is_recorded_with_the_visitors_ip(): void
    {
        $activity = $this->postFormatFrom('10.42.0.142', forwardedFor: '203.0.113.7');

        $this->assertSame('203.0.113.7', $activity->getProperty('ip'));
    }

    public function test_a_forwarded_ip_from_outside_the_cluster_is_not_believed(): void
    {
        $activity = $this->postFormatFrom('198.51.100.4', forwardedFor: '203.0.113.7');

        $this->assertSame('198.51.100.4', $activity->getProperty('ip'));
    }

    public function test_a_request_through_cloudflare_is_recorded_with_the_connecting_ip(): void
    {
        $activity = $this->postFormatFrom('10.42.0.142', forwardedFor: '172.70.1.1', cloudflareConnectingIp: '203.0.113.7');

        $this->assertSame('203.0.113.7', $activity->getProperty('ip'));
    }

    public function test_an_ipv6_cloudflare_connecting_ip_is_recorded(): void
    {
        $activity = $this->postFormatFrom('10.42.0.142', forwardedFor: '172.70.1.1', cloudflareConnectingIp: '2001:db8::7');

        $this->assertSame('2001:db8::7', $activity->getProperty('ip'));
    }

    public function test_a_cloudflare_connecting_ip_from_outside_the_cluster_is_not_believed(): void
    {
        $activity = $this->postFormatFrom('198.51.100.4', forwardedFor: '172.70.1.1', cloudflareConnectingIp: '203.0.113.7');

        $this->assertSame('198.51.100.4', $activity->getProperty('ip'));
    }

    public function test_a_malformed_cloudflare_connecting_ip_falls_back_to_the_forwarded_ip(): void
    {
        $activity = $this->postFormatFrom('10.42.0.142', forwardedFor: '203.0.113.7', cloudflareConnectingIp: 'not-an-ip');

        $this->assertSame('203.0.113.7', $activity->getProperty('ip'));
    }

    public function test_the_origin_is_added_alongside_the_entrys_own_properties(): void
    {
        $this->withRequestOrigin('198.51.100.4', self::USER_AGENT);

        activity()
            ->performedOn(Performance::factory()->create())
            ->event('technical_plan_reminder_sent')
            ->withProperties(['recipients' => 2])
            ->log('Technical plan reminder sent');

        $activity = Activity::query()->forEvent('technical_plan_reminder_sent')->sole();

        $this->assertSame(2, $activity->getProperty('recipients'));
        $this->assertSame('198.51.100.4', $activity->getProperty('ip'));
        $this->assertSame(self::USER_AGENT, $activity->getProperty('user_agent'));
    }

    public function test_an_ip_the_entry_already_carries_is_kept(): void
    {
        $this->withRequestOrigin('198.51.100.4', self::USER_AGENT);

        activity()
            ->event('recording_downloaded')
            ->withProperties(['ip' => '192.0.2.1'])
            ->log('Performance recording downloaded');

        $activity = Activity::query()->forEvent('recording_downloaded')->sole();

        $this->assertSame('192.0.2.1', $activity->getProperty('ip'));
        $this->assertSame(self::USER_AGENT, $activity->getProperty('user_agent'));
    }

    public function test_a_request_without_a_user_agent_is_recorded_as_having_none(): void
    {
        $this->withRequestOrigin('198.51.100.4', null);

        activity()->event('mail_sent')->log('E-mail sent');

        $activity = Activity::query()->forEvent('mail_sent')->sole();

        $this->assertSame('198.51.100.4', $activity->getProperty('ip'));
        $this->assertTrue($activity->properties->has('user_agent'));
        $this->assertNull($activity->getProperty('user_agent'));
    }

    public function test_an_entry_with_no_request_behind_it_carries_no_origin(): void
    {
        // No request at all, the way a console import runs.
        $format = Format::factory()->create();

        $activity = Activity::query()->forSubject($format)->forEvent('created')->sole();

        $this->assertFalse($activity->properties?->has('ip') ?? false);
        $this->assertFalse($activity->properties?->has('user_agent') ?? false);
    }

    /**
     * Create a format over HTTP from the given peer address, claiming to be
     * forwarded on behalf of another — and, optionally, that Cloudflare saw it
     * connect from a third — and return the entry it was logged with.
     */
    private function postFormatFrom(string $remoteAddress, string $forwardedFor, ?string $cloudflareConnectingIp = null): Activity
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->withHeader('X-Forwarded-For', $forwardedFor)
            ->withHeaders(array_filter([TrustCloudflareConnectingIp::HEADER => $cloudflareConnectingIp]))
            ->postJson(route('api.formats.store'), [
                'team_id' => $this->teamOf($user)->id,
                'name' => 'Hooaja avaetendus',
            ])
            ->assertCreated();

        $format = Format::query()->where('name', 'Hooaja avaetendus')->firstOrFail();

        return Activity::query()->forSubject($format)->forEvent('created')->sole();
    }

    /**
     * What {@see CaptureRequestOrigin} leaves behind for a request, and what a
     * job queued by that request has hydrated back on the worker.
     */
    private function withRequestOrigin(string $ip, ?string $userAgent): void
    {
        Context::addHidden([
            CaptureRequestOrigin::IP => $ip,
            CaptureRequestOrigin::USER_AGENT => $userAgent,
        ]);
    }
}
