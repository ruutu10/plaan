<?php

namespace Database\Seeders;

use App\Models\Show;
use App\Models\Team;
use Illuminate\Database\Seeder;

class ShowSeeder extends Seeder
{
    /**
     * Example shows, each owned by one of the seeded performing groups. Their
     * dated performances are seeded by {@see PerformanceSeeder}.
     *
     * @var list<array{team: string, name: string, description: string}>
     */
    private const SHOWS = [
        ['team' => 'Improteater Ruutu10', 'name' => 'Hooaja avaetendus', 'description' => 'Täispikk improetendus publiku ettepanekute põhjal, kahes vaatuses.'],
        ['team' => 'Improteater Ruutu10', 'name' => 'Öine impro', 'description' => 'Hilisõhtune vabas vormis kava täiskasvanud publikule.'],
        ['team' => 'Jaanuar', 'name' => 'Talvefestival 2026', 'description' => 'Lühivorm festivali raames, kiire tempo ja muusikaline saade.'],
        ['team' => 'Improgrupp Kolm', 'name' => 'Kolm lugu', 'description' => 'Kolmest omavahel põimuvast loost koosnev improetendus.'],
        ['team' => 'Must Kast', 'name' => 'Pimeduse proov', 'description' => 'Atmosfääriline lavastus minimaalse valguse ja tugeva helikujundusega.'],
        ['team' => 'Vaba Lava Ansambel', 'name' => 'Suvelavastus', 'description' => 'Vabaõhuetendus, vajab tugevat üldvalgust ja juhtmevabu mikrofone.'],
        ['team' => 'Öökullid', 'name' => 'Möödunud hooaja parimad', 'description' => 'Juba toimunud kokkuvõttev etendus (näidisandmete arhiiv).'],
    ];

    /**
     * Seed the example shows for the seeded performing groups, along with the
     * stand-in show every environment needs — it is not example data but part
     * of how plans are filed, so it is registered whatever else is seeded.
     */
    public function run(): void
    {
        Show::placeholder();

        foreach (self::SHOWS as $show) {
            $team = Team::where('name', $show['team'])->first();

            if ($team === null) {
                continue;
            }

            Show::firstOrCreate(
                [
                    'team_id' => $team->id,
                    'name' => $show['name'],
                ],
                [
                    'description' => $show['description'],
                ],
            );
        }
    }
}
