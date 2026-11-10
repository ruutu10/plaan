<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff can now be put on a performance by hand as well as read off its Planka
 * card. The flag says which one a row is: the import rewrites only its own rows
 * and leaves the hand-entered ones alone. Every row already here came from the
 * import, which is what the default says of them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('performance_staff', function (Blueprint $table) {
            $table->boolean('manually_added')->default(false)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performance_staff', function (Blueprint $table) {
            $table->dropColumn('manually_added');
        });
    }
};
