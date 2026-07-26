<?php

namespace Database\Seeders;

use App\Models\Show;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PerformanceSeeder extends Seeder
{
    /**
     * The example stagings of each seeded show, keyed by the show's name.
     * Positive day offsets are upcoming (and therefore selectable in the
     * wizard); negative offsets are in the past. A show staged more than once is
     * what lets the wizard offer an earlier staging's plan as the basis for the
     * next one.
     *
     * @var array<string, list<array{days: int, duration: int}>>
     */
    private const PERFORMANCES = [
        'Hooaja avaetendus' => [
            ['days' => -35, 'duration' => 75],
            ['days' => 7, 'duration' => 75],
        ],
        'Öine impro' => [['days' => 21, 'duration' => 60]],
        'Talvefestival 2026' => [['days' => 14, 'duration' => 45]],
        'Kolm lugu' => [['days' => 30, 'duration' => 50]],
        'Pimeduse proov' => [['days' => 45, 'duration' => 90]],
        'Suvelavastus' => [['days' => 60, 'duration' => 80]],
        'Möödunud hooaja parimad' => [['days' => -10, 'duration' => 70]],
    ];

    /**
     * Seed the example performances for the seeded shows. The dates are
     * relative to the day of seeding, so a show that already has its stagings
     * is left alone rather than given a second set a few days apart.
     */
    public function run(): void
    {
        foreach (self::PERFORMANCES as $showName => $stagings) {
            $show = Show::where('name', $showName)->first();

            if ($show === null || $show->performances()->exists()) {
                continue;
            }

            foreach ($stagings as $staging) {
                $show->performances()->create([
                    'date' => Carbon::today()->addDays($staging['days'])->toDateString(),
                    'duration' => $staging['duration'],
                ]);
            }
        }
    }
}
