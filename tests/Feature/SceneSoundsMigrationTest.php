<?php

namespace Tests\Feature;

use App\Models\TechnicalPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * The migration that turned a scene's single link-or-file into an ordered list
 * of cues. Plans written before it are the whole point of the exercise: an
 * evening's music must survive the deploy, so this walks a plan through the
 * migration in both directions and checks that nothing on disk moved.
 *
 * @see database/migrations/2026_09_02_190826_move_scene_sound_into_a_sounds_list.php
 */
class SceneSoundsMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Write scenes straight to the column, bypassing the model: the point is to
     * put a plan there in a shape the application no longer produces.
     *
     * @param  array<int, array<string, mixed>>  $scenes
     */
    private function planWithStoredScenes(array $scenes): TechnicalPlan
    {
        $plan = TechnicalPlan::factory()->create();

        DB::table('technical_plans')
            ->where('id', $plan->id)
            ->update(['scenes' => json_encode($scenes)]);

        return $plan->refresh();
    }

    /**
     * Put a plan in the database as the version before this migration stored
     * it, then let the migration find it.
     *
     * The step back has to come first: `down()` rewrites every plan it sees, so
     * seeding the old shape while the migration is applied would only have it
     * flattened again before `up()` ever ran.
     *
     * @param  array<int, array<string, mixed>>  $scenes
     */
    private function upgradeStoredScenes(array $scenes): TechnicalPlan
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $plan = $this->planWithStoredScenes($scenes);

        $this->artisan('migrate')->assertSuccessful();

        return $plan;
    }

    public function test_an_uploaded_sound_file_survives_as_the_scenes_first_cue(): void
    {
        Storage::fake('local');

        $media = TechnicalPlan::factory()->create()
            ->addMedia(UploadedFile::fake()->create('avamuusika.mp3', 120, 'audio/mpeg'))
            ->toMediaCollection(TechnicalPlan::SOUND_COLLECTION);

        $path = $media->getPathRelativeToRoot();

        $upgraded = $this->upgradeStoredScenes([[
            'id' => 'stseen-1',
            'name' => 'Lavale tulek',
            'light' => 'üldvalgus',
            'soundUrl' => '',
            'soundFile' => ['id' => (string) $media->uuid, 'name' => 'avamuusika.mp3', 'size' => 122880],
            'sound' => 'Fade sisse',
            'notes' => '',
        ]]);

        $scene = TechnicalPlan::find($upgraded->id)->scenes[0];

        $this->assertSame([[
            'id' => 'heli-1',
            'url' => '',
            'file' => ['id' => (string) $media->uuid, 'name' => 'avamuusika.mp3', 'size' => 122880],
        ]], $scene['sounds']);

        // The rest of the scene is left exactly as it was, old keys gone.
        $this->assertSame('Fade sisse', $scene['sound']);
        $this->assertSame('Lavale tulek', $scene['name']);
        $this->assertArrayNotHasKey('soundFile', $scene);
        $this->assertArrayNotHasKey('soundUrl', $scene);

        // Nothing was moved, re-keyed or deleted on the way through.
        $this->assertSame(1, Media::count());
        Storage::disk('local')->assertExists($path);
    }

    public function test_a_sound_link_survives_as_the_scenes_first_cue(): void
    {
        $plan = $this->upgradeStoredScenes([[
            'id' => 'stseen-1',
            'name' => 'Väljaminek',
            'light' => '',
            'soundUrl' => 'https://example.com/outro.mp3',
            'soundFile' => null,
            'sound' => '',
            'notes' => '',
        ]]);

        $this->assertSame(
            [['id' => 'heli-1', 'url' => 'https://example.com/outro.mp3', 'file' => null]],
            TechnicalPlan::find($plan->id)->scenes[0]['sounds'],
        );
    }

    public function test_a_scene_without_sound_migrates_to_an_empty_list(): void
    {
        // Nulls rather than empty strings: plans written by earlier versions of
        // the wizard have them, and they are not a cue.
        $plan = $this->upgradeStoredScenes([[
            'id' => 'stseen-1',
            'name' => 'Stseenid',
            'light' => 'üldvalgus',
            'soundUrl' => null,
            'soundFile' => null,
            'sound' => null,
            'notes' => null,
        ]]);

        $this->assertSame([], TechnicalPlan::find($plan->id)->scenes[0]['sounds']);
    }

    public function test_rolling_back_returns_the_first_cue_to_the_old_shape(): void
    {
        $plan = $this->planWithStoredScenes([[
            'id' => 'stseen-1',
            'name' => 'Mitu heli',
            'light' => '',
            'sounds' => [
                ['id' => 'heli-1', 'url' => '', 'file' => ['id' => 'media-1', 'name' => 'sisse.mp3', 'size' => 10]],
                ['id' => 'heli-2', 'url' => 'https://example.com/valja.mp3', 'file' => null],
            ],
            'sound' => '',
            'notes' => '',
        ]]);

        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $scene = TechnicalPlan::find($plan->id)->scenes[0];

        // The old shape holds one cue, so the second is dropped — stated in
        // the migration's docblock, and asserted here so it stays deliberate.
        $this->assertSame(['id' => 'media-1', 'name' => 'sisse.mp3', 'size' => 10], $scene['soundFile']);
        $this->assertSame('', $scene['soundUrl']);
        $this->assertArrayNotHasKey('sounds', $scene);

        $this->artisan('migrate')->assertSuccessful();
    }
}
