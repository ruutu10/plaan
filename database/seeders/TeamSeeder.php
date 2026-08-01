<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TeamSeeder extends Seeder
{
    /**
     * Seed a handful of performing groups to attach shows to.
     */
    public function run(): void
    {
        $teams = [
            config('teams.theatre_team_name'),
            'Märold',
            'Tšikid Reas',
            'Kanade Mäss',
            'Poti Kuningas',
            'Ehatäht',
        ];
        foreach ($teams as $name) {
            Team::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name), 'is_personal' => false],
            );
        }
    }
}
