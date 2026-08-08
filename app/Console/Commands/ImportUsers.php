<?php

namespace App\Console\Commands;

use App\Enums\SignupSource;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
 * that already has an account is passed over untouched, so re-importing adds
 * only what is new. That also means it will not repair an existing account, nor
 * put one into the team named by `--team`; an account already here is somebody
 * else's to manage.
 *
 * A row that cannot be read costs only that row. The run reports it and carries
 * on, because a single typo three hundred lines down should not send the whole
 * list back to be fixed and started over.
 */
#[Signature('user:import
    {path : Path to the CSV file, one "name,email" row per account}
    {--team= : Slug of the team the imported accounts join as members}')]
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

        // Settled before a single account is created: a misspelt slug that
        // surfaced halfway through would leave the first half of the list
        // imported into no team and the rest not imported at all.
        $team = $this->resolveTeam();

        if ($team === false) {
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Could not open the file {$path}.");

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $unreadable = 0;
        $line = 0;

        Log::info('User import started', [
            'path' => $path,
            'team_id' => $team?->id,
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

            if ($this->alreadyHasAnAccount($email)) {
                $this->comment("  Line {$line}: passing over {$email}, which already has an account.");
                $skipped++;

                continue;
            }

            $this->import($name, $email, $team);
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

        Log::info('User import finished', [
            'path' => $path,
            'team_id' => $team?->id,
            'imported' => $imported,
            'skipped' => $skipped,
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
     * Whether the address is already spoken for. Asked of the database row by
     * row rather than once up front, so the same address twice in one file
     * lands as one account: the second reading finds the first's.
     */
    private function alreadyHasAnAccount(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    /**
     * Create one account, and seat it in the team if one was named.
     */
    private function import(string $name, string $email, ?Team $team): void
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

        Log::info('Imported a user account from a file', [
            'user_id' => $user->id,
            'team_id' => $team?->id,
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
