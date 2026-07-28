<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The one-time links the app signs people in with. The composite index over
 * the visit counters is what the pruning job reads.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token', 255);
            $table->text('action');
            $table->unsignedTinyInteger('num_visits')->default(0);
            $table->unsignedTinyInteger('max_visits')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->string('access_code')->nullable();
            $table->timestamps();

            $table->index('available_at');
            $table->index(['max_visits', 'num_visits']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    /**
     * The table name, which the package lets the application rename.
     */
    private function table(): string
    {
        return config('magiclink.magiclink_table', 'magic_links');
    }
};
