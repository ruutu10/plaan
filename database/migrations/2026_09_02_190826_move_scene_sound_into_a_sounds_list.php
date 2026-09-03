<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A scene used to hold at most one cue — either a link or an uploaded file,
 * never both. Real shows do not fit that: one scene routinely wants an entry
 * sting and an exit track. Each scene's single cue therefore becomes the first
 * entry of an ordered `sounds` list.
 *
 * Only the JSON pointing at the files is rewritten. The `media` rows, and the
 * files themselves on disk, are not touched — an existing plan keeps every
 * sound it had, under the same handle.
 *
 * The rows are walked through the query builder rather than the model, so the
 * array casts and the activity log stay out of it and this migration does not
 * rot when App\Models\TechnicalPlan's shape moves on again.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rewriteScenes(function (array $scene): array {
            $file = $scene['soundFile'] ?? null;
            $url = trim((string) ($scene['soundUrl'] ?? ''));

            unset($scene['soundFile'], $scene['soundUrl']);

            $scene['sounds'] = match (true) {
                filled($file['id'] ?? null) => [['id' => 'heli-1', 'url' => '', 'file' => $file]],
                $url !== '' => [['id' => 'heli-1', 'url' => $url, 'file' => null]],
                default => [],
            };

            return $scene;
        });
    }

    /**
     * Reverse the migrations.
     *
     * The old shape holds one cue per scene, so a scene that has gained further
     * sounds keeps only its first on the way back. Nothing is deleted here: the
     * media the dropped entries named survive until the next save, where
     * App\Concerns\HasAttachments::syncAttachments() collects whatever no scene
     * refers to any more.
     */
    public function down(): void
    {
        $this->rewriteScenes(function (array $scene): array {
            $sound = $scene['sounds'][0] ?? null;

            unset($scene['sounds']);

            $scene['soundFile'] = $sound['file'] ?? null;
            $scene['soundUrl'] = (string) ($sound['url'] ?? '');

            return $scene;
        });
    }

    /**
     * Apply the given rewrite to every scene of every plan.
     *
     * Decoding and re-encoding in PHP keeps this working on both databases the
     * app runs against — MariaDB locally, SQLite in CI — neither of which would
     * share a JSON-function dialect.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $rewrite
     */
    private function rewriteScenes(callable $rewrite): void
    {
        DB::table('technical_plans')
            ->select(['id', 'scenes'])
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $plan) use ($rewrite): void {
                $scenes = json_decode((string) $plan->scenes, true);

                if (! is_array($scenes)) {
                    return;
                }

                $rewritten = array_map(
                    fn (mixed $scene): mixed => is_array($scene) ? $rewrite($scene) : $scene,
                    $scenes,
                );

                DB::table('technical_plans')
                    ->where('id', $plan->id)
                    ->update(['scenes' => json_encode(array_values($rewritten))]);
            });
    }
};
