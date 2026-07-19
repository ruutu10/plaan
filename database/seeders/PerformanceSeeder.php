<?php

namespace Database\Seeders;

use App\Models\Performance;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PerformanceSeeder extends Seeder
{
    /**
     * Example performances keyed by performing group name. Positive day offsets
     * are upcoming (and therefore selectable in the wizard); negative offsets
     * are in the past.
     *
     * @var list<array{team: string, show_name: string, days: int, duration: int, description: string}>
     */
    private const PERFORMANCES = [
        ['team' => 'Improteater Ruutu10', 'show_name' => 'Hooaja avaetendus', 'days' => 7, 'duration' => 75, 'description' => 'Täispikk improetendus publiku ettepanekute põhjal, kahes vaatuses.'],
        ['team' => 'Improteater Ruutu10', 'show_name' => 'Öine impro', 'days' => 21, 'duration' => 60, 'description' => 'Hilisõhtune vabas vormis kava täiskasvanud publikule.'],
        ['team' => 'Jaanuar', 'show_name' => 'Talvefestival 2026', 'days' => 14, 'duration' => 45, 'description' => 'Lühivorm festivali raames, kiire tempo ja muusikaline saade.'],
        ['team' => 'Improgrupp Kolm', 'show_name' => 'Kolm lugu', 'days' => 30, 'duration' => 50, 'description' => 'Kolmest omavahel põimuvast loost koosnev improetendus.'],
        ['team' => 'Must Kast', 'show_name' => 'Pimeduse proov', 'days' => 45, 'duration' => 90, 'description' => 'Atmosfääriline lavastus minimaalse valguse ja tugeva helikujundusega.'],
        ['team' => 'Vaba Lava Ansambel', 'show_name' => 'Suvelavastus', 'days' => 60, 'duration' => 80, 'description' => 'Vabaõhuetendus, vajab tugevat üldvalgust ja juhtmevabu mikrofone.'],
        ['team' => 'Öökullid', 'show_name' => 'Möödunud hooaja parimad', 'days' => -10, 'duration' => 70, 'description' => 'Juba toimunud kokkuvõttev etendus (näidisandmete arhiiv).'],
    ];

    /**
     * Seed example performances for the seeded performing groups.
     */
    public function run(): void
    {
        foreach (self::PERFORMANCES as $performance) {
            $team = Team::where('name', $performance['team'])->first();

            if ($team === null) {
                continue;
            }

            Performance::firstOrCreate(
                [
                    'team_id' => $team->id,
                    'show_name' => $performance['show_name'],
                ],
                [
                    'show_date' => Carbon::today()->addDays($performance['days'])->toDateString(),
                    'duration' => $performance['duration'],
                    'description' => $performance['description'],
                ],
            );
        }
    }
}
