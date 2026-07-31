<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A performance is a night at a time, not a day: the crew calls before the
 * doors and the reminders count backwards from the curtain, so the hour has to
 * be on the record rather than assumed.
 *
 * The column keeps its name — it is still the thing the app orders by and asks
 * "when is this?" of — but now carries a full moment. Like every other
 * timestamp here it is stored in UTC; `config('performance.timezone')` is the
 * wall clock it is written and read through.
 *
 * The performances already on the books have no hour to keep, so they are
 * moved to the venue's usual curtain-up rather than to midnight, which would
 * read as a real (and wrong) start time everywhere it was shown.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->dateTime('date')->change();
        });

        $this->shiftStoredDates(
            fn (string $date): string => Carbon::parse($date, $this->venueTimezone())
                ->setTimeFromTimeString($this->defaultStartTime())
                ->utc()
                ->toDateTimeString(),
        );
    }

    /**
     * Reverse the migrations.
     *
     * The hour is lost — a date column has nowhere to keep it — so each moment
     * is read back in the venue's zone first. A performance at 00:30 Tallinn
     * time is stored as the previous day in UTC, and truncating that to a bare
     * date without converting would move the night a day earlier.
     */
    public function down(): void
    {
        $this->shiftStoredDates(
            fn (string $date): string => Carbon::parse($date, 'UTC')
                ->setTimezone($this->venueTimezone())
                ->toDateString(),
        );

        Schema::table('performances', function (Blueprint $table) {
            $table->date('date')->change();
        });
    }

    /**
     * Rewrite every stored date through the given conversion. Soft-deleted
     * performances are included: they are restorable, and one brought back with
     * an unconverted date would be hours out.
     *
     * @param  callable(string): string  $convert
     */
    private function shiftStoredDates(callable $convert): void
    {
        DB::table('performances')
            ->select(['id', 'date'])
            ->orderBy('id')
            ->chunk(500, function ($performances) use ($convert): void {
                foreach ($performances as $performance) {
                    DB::table('performances')
                        ->where('id', $performance->id)
                        ->update(['date' => $convert((string) $performance->date)]);
                }
            });
    }

    /**
     * The wall clock the house runs on, falling back to the shipped default so
     * the migration does not depend on a variable being set in whatever
     * environment it happens to run in.
     */
    private function venueTimezone(): string
    {
        return (string) config('performance.timezone', 'Europe/Tallinn');
    }

    /**
     * The curtain-up the hourless performances are moved to.
     */
    private function defaultStartTime(): string
    {
        return (string) config('performance.default_start_time', '19:00');
    }
};
