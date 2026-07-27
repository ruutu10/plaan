<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A deleted show or staging is put aside rather than destroyed: the plans
 * written for it, and the record that it was ever staged, outlive the row's
 * usefulness in the lists.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('performances', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('performances', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
