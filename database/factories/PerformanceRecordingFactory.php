<?php

namespace Database\Factories;

use App\Models\Performance;
use App\Models\PerformanceRecording;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceRecording>
 */
class PerformanceRecordingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A link nobody has said otherwise about is one just pasted: the push has
     * not run yet, nothing has gone wrong, and nobody has been written to.
     *
     * `item_id` is left to the model, which derives it from the URL whenever
     * that is written — see PerformanceRecording::booted().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_id' => Performance::factory(),
            'url' => self::addressOf(self::itemId()),
            'synced_at' => null,
            'sync_error' => null,
            'announced_at' => null,
        ];
    }

    /**
     * Point the recording at a given episode, for a test that has to match what
     * it pasted against what was pushed.
     */
    public function forItem(string $itemId): static
    {
        return $this->state(fn (array $attributes): array => [
            'url' => self::addressOf($itemId),
        ]);
    }

    /**
     * Indicate that the link has been pushed to Jellyfin since it was written.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'synced_at' => now(),
            'sync_error' => null,
        ]);
    }

    /**
     * Indicate that the last push did not get through.
     */
    public function failed(string $error = 'HTTP request returned status code 500'): static
    {
        return $this->state(fn (array $attributes): array => [
            'synced_at' => null,
            'sync_error' => $error,
        ]);
    }

    /**
     * Indicate that the performers have already been told there is a video.
     */
    public function announced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'announced_at' => now(),
        ]);
    }

    /**
     * An episode's address as Jellyfin's own interface writes it.
     */
    private static function addressOf(string $itemId): string
    {
        return 'https://jellyfin.test/web/#/details?id='.$itemId.'&serverId='.self::itemId();
    }

    /**
     * A Jellyfin item id: 32 hex characters, which is the dash-less form the
     * API takes and the form PerformanceRecording stores.
     */
    private static function itemId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
