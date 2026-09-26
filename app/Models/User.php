<?php

namespace App\Models;

use App\Actions\GrantStaffAccess;
use App\Concerns\HasTeams;
use App\Concerns\LogsModelActivity;
use App\Enums\SignupSource;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
     * Create an account nobody signed up for by hand: one that will be got
     * into by a magic link, an SSO login or a password reset, and so carries a
     * password nobody is ever told.
     *
     * Every door but the registration form comes through here, so an address
     * is stored the same way whichever of them it arrived by. Whether the door
     * also vouches for the address is the caller's to say: an SSO provider or
     * a hand-compiled import list does, a typed-in address does not.
     *
     * @param  string|null  $name  when none is known, the address's local part
     *                             stands in until the owner corrects it
     */
    public static function provision(
        string $email,
        SignupSource $signupSource,
        ?string $name = null,
        bool $verified = false,
    ): self {
        $email = self::normalizeEmail($email);

        $user = new self([
            'name' => filled($name) ? $name : self::nameFromEmail($email),
            'email' => $email,
            'password' => Str::random(40),
            'signup_source' => $signupSource,
        ]);

        if ($verified) {
            $user->forceFill(['email_verified_at' => now()]);
        }

        $user->save();

        return $user;
    }

    /**
     * An address the way accounts are stored and looked up by: trimmed and
     * lowercased, so the same mailbox typed two ways is one account.
     */
    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * A stand-in name for an account nobody has named yet.
     */
    private static function nameFromEmail(string $email): string
    {
        return Str::of($email)->before('@')->trim()->value() ?: 'Esineja';
    }

    /**
     * Whether this account is one of the house's own — its address sits on a
     * domain the theatre runs, rather than on whatever a visiting performer
     * signed up with.
     *
     * This is about the address and nothing else. Holding the staff *role* is a
     * further step that also wants the address proven — see
     * {@see GrantStaffAccess} — so an account can be the house's
     * by this and still be waiting on its verification mail.
     */
    public function isHouseStaff(): bool
    {
        return self::isHouseAddress($this->email);
    }

    /**
     * Whether the given address belongs to one of the theatre's own domains.
     *
     * Static because the question is asked of addresses that have no account
     * behind them yet: the Planka import reads names off a card and has to work
     * out which of them the house could possibly mean.
     */
    public static function isHouseAddress(?string $email): bool
    {
        if ($email === null || ! str_contains($email, '@')) {
            return false;
        }

        $domain = Str::of($email)->afterLast('@')->trim()->lower()->value();

        /** @var array<int, string> $houseDomains */
        $houseDomains = config('mail.verified_email_domains', []);

        return in_array($domain, array_map(strtolower(...), $houseDomains), true);
    }

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
     * Every write of the address goes through {@see normalizeEmail()}, whichever
     * form or import it came from.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalizeEmail($value));
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
