<?php

use App\Enums\PerformanceStaffRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who staffs a performance — on stage or behind it — as a Planka card names
 * them. Nobody enters this by hand: it exists only because the import can read
 * it, and it is wholly replaced every time the import reads the card again, so
 * there is nothing here worth softly deleting or auditing row by row.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('performance_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', PerformanceStaffRole::values());
            $table->timestamps();

            $table->unique(['performance_id', 'user_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_staff');
    }
};
