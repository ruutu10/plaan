<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TeamSeeder extends Seeder
{
    /**
     * The example performing groups used across the demo data.
     *
     * @var list<string>
     */
    private const TEAMS = [
        'Märold',
        'Jaanuar',
        'Improgrupp Kolm',
        'Must Kass',
        'Vaba Ansambel',
        'Öökullid',
    ];

    /**
     * Seed a handful of performing groups to attach performances to.
     */
    public function run(): void
    {
        foreach (self::TEAMS as $name) {
            Team::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name), 'is_personal' => false],
            );
        }
    }
}
