<?php

use App\Enums\TechnicalPlanStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('technical_plans', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->enum('status', TechnicalPlanStatus::values())
                ->default(TechnicalPlanStatus::Draft->value);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performance_id')->nullable()->constrained()->nullOnDelete();

            $table->json('sound');
            $table->json('scenes');
            $table->json('equipment');
            $table->json('extra');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technical_plans');
    }
};
