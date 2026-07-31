<?php

namespace Database\Seeders;

use App\Models\Performance;
use App\Models\Show;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PerformanceSeeder extends Seeder
{
    /**
     * The example performances of each seeded show, keyed by the show's name.
     * Positive day offsets are upcoming (and therefore selectable in the
     * wizard); negative offsets are in the past. A show staged more than once is
     * what lets the wizard offer an earlier performance's plan as the basis for the
     * next one.
     *
     * @var array<string, list<array{days: int, at: string, duration: int}>>
     */
    private const PERFORMANCES = [
        'Hooaja avaetendus' => [
            ['days' => -35, 'at' => '19:00', 'duration' => 75],
            ['days' => 7, 'at' => '19:00', 'duration' => 75],
        ],
        'Öine impro' => [['days' => 21, 'at' => '22:30', 'duration' => 60]],
        'Talvefestival 2026' => [['days' => 14, 'at' => '18:00', 'duration' => 45]],
        'Kolm lugu' => [['days' => 30, 'at' => '19:00', 'duration' => 50]],
        'Pimeduse proov' => [['days' => 45, 'at' => '20:00', 'duration' => 90]],
        'Suvelavastus' => [['days' => 60, 'at' => '19:30', 'duration' => 80]],
        'Möödunud hooaja parimad' => [['days' => -10, 'at' => '19:00', 'duration' => 70]],
    ];

    /**
     * Seed the example performances for the seeded shows. The dates are
     * relative to the day of seeding, so a show that already has its performances
     * is left alone rather than given a second set a few days apart.
     */
    public function run(): void
    {
        foreach (self::PERFORMANCES as $showName => $performances) {
            $show = Show::where('name', $showName)->first();

            if ($show === null || $show->performances()->exists()) {
                continue;
            }

            foreach ($performances as $performance) {
                $show->performances()->create([
                    'date' => Performance::momentFrom(
                        Carbon::today(Performance::venueTimezone())
                            ->addDays($performance['days'])
                            ->toDateString(),
                        $performance['at'],
                    ),
                    'duration' => $performance['duration'],
                ]);
            }
        }
    }
}
