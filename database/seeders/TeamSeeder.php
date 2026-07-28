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
        'Ruutu10',
        'Märold',
        'Tšikid Reas',
        'Kanade Mäss',
        'Poti Kuningas',
        'Ehatäht',
    ];

    /**
     * Seed a handful of performing groups to attach shows to.
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
