<?php

namespace Tests\Feature;

use App\Models\PendingUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class PruneStaleAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Stage a fake upload and return its holder, optionally aged into the past.
     */
    private function stageUpload(?int $ageInHours = null): PendingUpload
    {
        $upload = PendingUpload::create();
        $upload->addMedia(UploadedFile::fake()->create('plaan.pdf', 20, 'application/pdf'))
            ->toMediaCollection($upload->attachmentsCollection());

        if ($ageInHours !== null) {
            $upload->forceFill(['created_at' => now()->subHours($ageInHours)])->save();
        }

        return $upload;
    }

    public function test_it_deletes_stale_uploads_from_disk_and_database(): void
    {
        Storage::fake('local');

        $stale = $this->stageUpload(ageInHours: 96);
        $stalePath = $stale->getFirstMedia($stale->attachmentsCollection())->getPathRelativeToRoot();

        Storage::disk('local')->assertExists($stalePath);

        $this->artisan('attachments:prune-stale')
            ->assertSuccessful();

        $this->assertDatabaseMissing('pending_uploads', ['id' => $stale->id]);
        $this->assertSame(0, Media::query()->count());
        Storage::disk('local')->assertMissing($stalePath);
    }

    public function test_it_keeps_recent_uploads(): void
    {
        Storage::fake('local');

        $recent = $this->stageUpload(ageInHours: 2);

        $this->artisan('attachments:prune-stale')
            ->assertSuccessful();

        $this->assertDatabaseHas('pending_uploads', ['id' => $recent->id]);
        $this->assertSame(1, Media::query()->count());
    }

    public function test_it_prunes_every_stale_upload_while_keeping_recent_ones(): void
    {
        Storage::fake('local');

        $stale = collect(range(1, 5))->map(fn (): PendingUpload => $this->stageUpload(ageInHours: 96));
        $recent = collect(range(1, 2))->map(fn (): PendingUpload => $this->stageUpload(ageInHours: 1));

        $this->artisan('attachments:prune-stale')
            ->assertSuccessful();

        foreach ($stale as $upload) {
            $this->assertDatabaseMissing('pending_uploads', ['id' => $upload->id]);
        }

        foreach ($recent as $upload) {
            $this->assertDatabaseHas('pending_uploads', ['id' => $upload->id]);
        }

        $this->assertSame($recent->count(), Media::query()->count());
    }

    public function test_the_age_threshold_is_configurable(): void
    {
        Storage::fake('local');

        $upload = $this->stageUpload(ageInHours: 5);

        $this->artisan('attachments:prune-stale', ['--hours' => 4])
            ->assertSuccessful();

        $this->assertDatabaseMissing('pending_uploads', ['id' => $upload->id]);
        $this->assertSame(0, Media::query()->count());
    }
}
