<?php

namespace App\Console\Commands;

use App\Enums\SignupSource;
use App\Enums\TeamRole;
use App\Http\Requests\Users\AssignRoleRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Create accounts in bulk from a list handed over as a file — a season's cast,
 * a workshop's sign-up sheet — without any of them being written by hand.
 *
 * The file is a plain CSV of `name,email`, one account per row. Nothing is sent
 * to anybody: an imported account is not told it exists, its address is taken
 * as proven because whoever compiled the list vouched for it, and its password
 * is a random string nobody keeps — the way in is a password reset or a magic
 * link, asked for by the person themselves.
 *
 * The command is meant to be run again over a list that has grown: an address
 * that already has an account keeps the account it has, so re-importing adds
 * only what is new. It will not repair an existing account, nor put one into
 * the team named by `--team` — an account already here is somebody else's to
 * manage.
 *
 * The role named by `--role` is the one exception, and deliberately so: a list
 * of the people who are to hold a right is a list of all of them, not only the
 * ones who happen to be new. So an account that is already here is granted it
 * too, if it does not hold it already.
 *
 * A row that cannot be read costs only that row. The run reports it and carries
 * on, because a single typo three hundred lines down should not send the whole
 * list back to be fixed and started over.
 */
#[Signature('user:import
    {path : Path to the CSV file, one "name,email" row per account}
    {--team= : Slug of the team the imported accounts join as members}
    {--role= : Name of the role granted to every account in the file, new or not}')]
#[Description('Create user accounts from a CSV file of names and e-mail addresses.')]
class ImportUsers extends Command
{
    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Could not read the file {$path}.");

            return self::FAILURE;
        }

        // Both settled before a single account is created: a misspelt name that
        // surfaced halfway through would leave the first half of the list
        // imported without its team or role and the rest not imported at all.
        $team = $this->resolveTeam();

        if ($team === false) {
            return self::FAILURE;
        }

        $role = $this->resolveRole();

        if ($role === false) {
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Could not open the file {$path}.");

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $granted = 0;
        $unreadable = 0;
        $line = 0;

        Log::info('User import started', [
            'path' => $path,
            'team_id' => $team?->id,
            'role' => $role?->name,
        ]);

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $line++;

            // A blank line, or the trailing newline the file ends on.
            if ($row === [null] || $this->isBlank($row)) {
                continue;
            }

            if ($line === 1 && $this->isHeader($row)) {
                continue;
            }

            $fields = $this->readRow($row, $line);

            if ($fields === null) {
                $unreadable++;

                continue;
            }

            [$name, $email] = $fields;

            if ($existing = $this->accountFor($email)) {
                $this->comment("  Line {$line}: passing over {$email}, which already has an account.");
                $skipped++;

                if ($this->grant($existing, $role)) {
                    $this->info("  Line {$line}: granted {$existing->email} the {$role?->name} role.");
                    $granted++;
                }

                continue;
            }

            $this->import($name, $email, $team, $role);
            $this->info("  Line {$line}: created an account for {$email}.");
            $imported++;
        }

        fclose($handle);

        $this->info(sprintf(
            'Imported %d account(s), passed over %d that already existed, could not read %d row(s).',
            $imported,
            $skipped,
            $unreadable,
        ));

        if ($role !== null) {
            $this->info(sprintf(
                'Granted the %s role to %d account(s) that already existed%s.',
                $role->name,
                $granted,
                $imported === 0 ? '' : ", and to every one of the {$imported} imported",
            ));
        }

        Log::info('User import finished', [
            'path' => $path,
            'team_id' => $team?->id,
            'role' => $role?->name,
            'imported' => $imported,
            'skipped' => $skipped,
            'granted_to_existing' => $granted,
            'unreadable' => $unreadable,
        ]);

        return self::SUCCESS;
    }

    /**
     * The team the imported accounts join, if one was named.
     *
     * @return Team|null|false The team, null if none was named, or false if the
     *                         slug names no team — which stops the run.
     */
    private function resolveTeam(): Team|null|false
    {
        $slug = $this->option('team');

        if (blank($slug)) {
            return null;
        }

        $team = Team::query()->where('slug', $slug)->first();

        if ($team === null) {
            $this->error("There is no team with the slug \"{$slug}\".");

            Log::warning('User import aborted: the named team does not exist', [
                'slug' => $slug,
            ]);

            return false;
        }

        return $team;
    }

    /**
     * The role every account in the file is to hold, if one was named.
     *
     * Which roles exist is read from the table rather than listed here: they
     * are created by migrations, so a name that is not in it is a typo, not a
     * new right — the same reading {@see AssignRoleRequest} takes of a name
     * typed into the management screen.
     *
     * @return Role|null|false The role, null if none was named, or false if the
     *                         name names no role — which stops the run.
     */
    private function resolveRole(): Role|null|false
    {
        $name = $this->option('role');

        if (blank($name)) {
            return null;
        }

        $role = Role::query()->where('name', $name)->first();

        if ($role === null) {
            $this->error(sprintf(
                'There is no role named "%s". The roles that exist are: %s.',
                $name,
                Role::query()->orderBy('name')->pluck('name')->implode(', '),
            ));

            Log::warning('User import aborted: the named role does not exist', [
                'role' => $name,
            ]);

            return false;
        }

        return $role;
    }

    /**
     * Hand an account the named role, if there is one and it does not hold it
     * already. Reports whether anything was actually granted, so a run over a
     * list of people who all hold it already says so plainly rather than
     * claiming to have handed out what was already there.
     */
    private function grant(User $user, ?Role $role): bool
    {
        if ($role === null || $user->hasRole($role)) {
            return false;
        }

        $user->assignRole($role);

        // A role carries rights, so every grant is worth a line of its own —
        // the same notice the management screen writes when one is handed out
        // by hand, with the file standing in for the person who would have.
        Log::notice('Role granted by the user import', [
            'user_id' => $user->id,
            'role' => $role->name,
        ]);

        return true;
    }

    /**
     * Read one row into a name and an address, or report why it cannot be read.
     *
     * @param  list<string|null>  $row
     * @return array{0: string, 1: string}|null
     */
    private function readRow(array $row, int $line): ?array
    {
        $name = trim((string) ($row[0] ?? ''));
        $email = mb_strtolower(trim((string) ($row[1] ?? '')));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255']],
        );

        if ($validator->fails()) {
            $this->warn(sprintf(
                '  Line %d: %s',
                $line,
                implode(' ', $validator->errors()->all()),
            ));

            return null;
        }

        return [$name, $email];
    }

    /**
     * The account the address already has, if it has one. Asked of the database
     * row by row rather than once up front, so the same address twice in one
     * file lands as one account: the second reading finds the first's.
     */
    private function accountFor(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    /**
     * Create one account, seat it in the team if one was named, and hand it the
     * role if one was named.
     */
    private function import(string $name, string $email, ?Team $team, ?Role $role): void
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            // Nobody will ever be told this, and nobody needs to be: the way in
            // is a reset or a magic link, both of which replace it.
            'password' => Hash::make(Str::random(40)),
            'signup_source' => SignupSource::CsvImport->value,
        ]);

        // Taken as proven on the word of whoever compiled the list. The
        // alternative — an unverified address on an account that is never
        // e-mailed — would leave every imported account permanently unusable.
        $user->forceFill(['email_verified_at' => now()])->save();

        if ($team !== null) {
            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Member,
            ]);

            // The account has no team of its own — nothing here makes one — so
            // without this it would sign in and land nowhere.
            $user->switchTeam($team);
        }

        $this->grant($user, $role);

        Log::info('Imported a user account from a file', [
            'user_id' => $user->id,
            'team_id' => $team?->id,
            'role' => $role?->name,
            'signup_source' => SignupSource::CsvImport->value,
        ]);
    }

    /**
     * Whether the row is the file's header rather than an account. Only the
     * literal column names are taken for one: a row that reads anything else
     * is somebody's name and address, and passing it over silently would drop
     * an account without saying so.
     *
     * @param  list<string|null>  $row
     */
    private function isHeader(array $row): bool
    {
        return mb_strtolower(trim((string) ($row[0] ?? ''))) === 'name'
            && mb_strtolower(trim((string) ($row[1] ?? ''))) === 'email';
    }

    /**
     * Whether the row holds nothing at all.
     *
     * @param  list<string|null>  $row
     */
    private function isBlank(array $row): bool
    {
        foreach ($row as $field) {
            if (trim((string) $field) !== '') {
                return false;
            }
        }

        return true;
    }
}
