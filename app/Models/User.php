<?php

namespace App\Models;

use App\Concerns\HasTeams;
use App\Concerns\LogsModelActivity;
use App\Enums\SignupSource;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $authentik_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property SignupSource $signup_source
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 */
#[Fillable(['name', 'email', 'authentik_id', 'password', 'current_team_id', 'signup_source'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsModelActivity, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    use HasRoles, HasTeams {
        HasTeams::teams insteadof HasRoles;
        HasRoles::teams as spatieTeams;
    }

    /**
     * The permission — held by the "technician" role — that opens the account
     * management screens: every account in the house, what it is called, and
     * which roles it holds.
     */
    public const MANAGE_PERMISSION = 'users.manage';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'signup_source' => SignupSource::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * The properties worth an audit trail. Credentials and two-factor secrets
     * are left out on purpose — they change often and never need explaining.
     *
     * @return array<int, string>
     */
    protected function activityLogAttributes(): array
    {
        return ['name', 'email'];
    }
}
