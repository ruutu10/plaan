<?php

namespace App\Http\Controllers;

use App\Events\PerformanceRecordingDownloadStarted;
use App\Models\Performance;
use App\Models\PerformanceRecording;
use App\Services\JellyfinClient;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response as UpstreamResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Hands a night's video to somebody with a Plaan login and no Jellyfin account.
 *
 * The bytes pass straight through and the library's key stays on the server.
 * The item is always the one the recording names, never anything the request
 * says, and ranges are passed along so a dropped ten-gigabyte download can be
 * picked up where it stopped.
 *
 * Every download holds a PHP worker until it finishes, so only so many may run
 * at once: {@see acquireSlot()}.
 */
class PerformanceRecordingController extends Controller
{
    /**
     * How long a slot outlives a worker that died without giving it back.
     */
    private const SLOT_LOCK_SECONDS = 6 * 60 * 60;

    private const CHUNK_BYTES = 64 * 1024;

    public function __construct(private JellyfinClient $jellyfin) {}

    /**
     * Stream the performance's recording to the browser as a file.
     */
    public function download(Request $request, Performance $performance): Response|StreamedResponse
    {
        Gate::authorize('downloadRecording', $performance);

        $recording = $performance->recording;

        abort_if($recording === null || $recording->item_id === null, 404);

        // A HEAD never runs the stream, so it must not take a slot it would
        // have no way of giving back.
        if ($request->isMethod('HEAD')) {
            return response('', 200, ['Accept-Ranges' => 'bytes']);
        }

        $slot = $this->acquireSlot();

        if ($slot === null) {
            Log::info('A recording download was turned away: all slots are busy', [
                'recording_id' => $recording->id,
                'user_id' => $request->user()->id,
            ]);

            return $this->unavailable(503, 'Praegu laaditakse salvestusi alla liiga palju korraga. Proovi mõne minuti pärast uuesti.')
                ->header('Retry-After', '120');
        }

        $range = $this->validRange($request->header('Range'));

        try {
            $upstream = $this->jellyfin->download($recording->item_id, $range, $request->header('If-Range'));
        } catch (HttpClientException|RuntimeException $exception) {
            $slot->release();

            return $this->libraryFailed($recording, $exception->getMessage());
        }

        if ($upstream->status() === 416) {
            $slot->release();

            return response('', 416, array_filter(['Content-Range' => $upstream->header('Content-Range')]));
        }

        if (! in_array($upstream->status(), [200, 206], true)) {
            $slot->release();

            return $this->libraryFailed($recording, 'Jellyfin answered with status '.$upstream->status());
        }

        if ($range === null || str_starts_with($range, 'bytes=0-')) {
            PerformanceRecordingDownloadStarted::dispatch($recording, $request->user(), $request->ip());
        }

        $body = $upstream->toPsrResponse()->getBody();

        return response()->stream(
            fn () => $this->relay($body, $slot, $recording),
            $upstream->status(),
            $this->downloadHeaders($upstream, $performance),
        );
    }

    /**
     * Take one of the limited download places, or null when they are all gone.
     */
    private function acquireSlot(): ?Lock
    {
        $slots = max(1, (int) config('services.jellyfin.max_concurrent_downloads'));

        for ($slot = 0; $slot < $slots; $slot++) {
            $lock = Cache::lock('recording-download-slot-'.$slot, self::SLOT_LOCK_SECONDS);

            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }

    /**
     * Copy the library's answer to the browser, and always give the slot back.
     *
     * Told to carry on past a disconnect, because PHP would otherwise end the
     * script at the first failed write and the slot would stay taken for hours.
     * The disconnect is noticed here instead.
     */
    private function relay(StreamInterface $body, Lock $slot, PerformanceRecording $recording): void
    {
        ignore_user_abort(true);
        set_time_limit(0);

        try {
            while (! $body->eof() && connection_aborted() === 0) {
                echo $body->read(self::CHUNK_BYTES);
                flush();
            }
        } catch (Throwable $exception) {
            // The headers are long gone, so the browser can only be left with
            // a short file — which it reports as failed and can resume.
            Log::warning('A recording download was cut off by the library', [
                'recording_id' => $recording->id,
                'error' => $exception->getMessage(),
            ]);
        } finally {
            $body->close();
            $slot->release();
        }
    }

    /**
     * The one shape of range worth forwarding: a single span of bytes.
     */
    private function validRange(?string $range): ?string
    {
        return $range !== null && preg_match('/^bytes=\d*-\d*$/', $range) === 1 ? $range : null;
    }

    /**
     * The response headers: the library's facts about the file, and this app's
     * name for it. Marked uncacheable and untransformable because a CDN sits in
     * front, and this file is for signed-in people only.
     *
     * @return array<string, string>
     */
    private function downloadHeaders(UpstreamResponse $upstream, Performance $performance): array
    {
        $type = $upstream->header('Content-Type');

        return array_filter([
            'Content-Type' => str_starts_with($type, 'video/') ? $type : 'application/octet-stream',
            'Content-Length' => $upstream->header('Content-Length'),
            'Content-Range' => $upstream->header('Content-Range'),
            'Last-Modified' => $upstream->header('Last-Modified'),
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => $this->disposition($upstream, $performance),
            'Cache-Control' => 'private, no-store, no-transform',
            'X-Accel-Buffering' => 'no',
            'X-Content-Type-Options' => 'nosniff',
        ], fn (string $value): bool => $value !== '');
    }

    /**
     * Named for what was played and when, keeping the library's file extension.
     */
    private function disposition(UpstreamResponse $upstream, Performance $performance): string
    {
        $extension = preg_match('/filename="?[^";]*\.([A-Za-z0-9]{2,5})"?/', $upstream->header('Content-Disposition'), $matches) === 1
            ? strtolower($matches[1])
            : 'mp4';

        $name = Str::of($performance->format->name.' '.$performance->startsAt()->format('Y-m-d'))
            ->replaceMatches('/[\/\\\\%"]+/', ' ')
            ->squish()
            ->append('.'.$extension)
            ->toString();

        $fallback = preg_replace('/[^A-Za-z0-9 ._-]+/', '_', Str::ascii($name)) ?? 'salvestus.'.$extension;

        return HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name, $fallback);
    }

    private function libraryFailed(PerformanceRecording $recording, string $reason): Response
    {
        Log::error('Could not open a recording for download', [
            'recording_id' => $recording->id,
            'item_id' => $recording->item_id,
            'error' => $reason,
        ]);

        return $this->unavailable(502, 'Salvestust ei õnnestunud meediakogust kätte saada. Proovi hiljem uuesti.');
    }

    private function unavailable(int $status, string $message): Response
    {
        return response()->view('errors.recording-unavailable', ['message' => $message], $status);
    }
}
