<?php

namespace App\Data;

use App\Models\Performance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Number;

/**
 * The video file behind a recording, as Jellyfin describes it: what it is
 * called, how big it is and when it was added to the library.
 */
final readonly class RecordingFile
{
    public function __construct(
        public string $name,
        public int $sizeBytes,
        public ?CarbonImmutable $addedAt,
    ) {}

    /**
     * Rebuild from {@see toArray()}, the shape it is cached in.
     *
     * @param  array{name: string, sizeBytes: int, addedAt: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['sizeBytes'],
            $data['addedAt'] === null ? null : CarbonImmutable::parse($data['addedAt']),
        );
    }

    /**
     * The size as a person reads it, e.g. "9.0 GB".
     */
    public function sizeLabel(): string
    {
        return Number::fileSize($this->sizeBytes, precision: 1);
    }

    /**
     * The day it was added, on the venue's calendar, e.g. "04.04.2026".
     */
    public function addedOnLabel(): ?string
    {
        return $this->addedAt?->setTimezone(Performance::venueTimezone())->format('d.m.Y');
    }

    /**
     * @return array{name: string, sizeBytes: int, addedAt: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sizeBytes' => $this->sizeBytes,
            'addedAt' => $this->addedAt?->toIso8601String(),
        ];
    }
}
