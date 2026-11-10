<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two more jobs a person can hold on a night: "fotograaf", which the Planka
 * import reads off a card like the rest, and "muu", a job outside the list,
 * entered by hand only.
 *
 * The values are spelt out rather than read off the enum, so this migration
 * still says the same thing once the enum has moved on.
 */
return new class extends Migration
{
    /**
     * The roles before this migration.
     *
     * @var list<string>
     */
    private const BEFORE = ['performer', 'host', 'technician', 'video-operator', 'ticket-seller', 'bar'];

    /**
     * The roles it adds.
     *
     * @var list<string>
     */
    private const ADDED = ['photographer', 'other'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('performance_staff', function (Blueprint $table) {
            $table->enum('role', [...self::BEFORE, ...self::ADDED])->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Rows in the roles being taken away have nowhere to go, and would stop
     * the column from narrowing, so they are dropped first.
     */
    public function down(): void
    {
        DB::table('performance_staff')->whereIn('role', self::ADDED)->delete();

        Schema::table('performance_staff', function (Blueprint $table) {
            $table->enum('role', self::BEFORE)->change();
        });
    }
};
