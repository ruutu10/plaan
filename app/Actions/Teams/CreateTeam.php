<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateTeam
{
    /**
     * Create a new team and add the user as owner.
     *
     * @param  bool  $switch  Whether to move the user into the new team. The
     *                        management screens start teams the user is not
     *                        necessarily going to work in, and leave them where
     *                        they are.
     */
    public function handle(User $user, string $name, bool $isPersonal = false, bool $switch = true): Team
    {
        return DB::transaction(function () use ($user, $name, $isPersonal, $switch) {
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            if ($switch) {
                $user->switchTeam($team);
            }

            Log::info('Team created', [
                'team_id' => $team->id,
                'slug' => $team->slug,
                'is_personal' => $isPersonal,
                'owner_id' => $user->id,
                'switched' => $switch,
            ]);

            return $team;
        });
    }
}
