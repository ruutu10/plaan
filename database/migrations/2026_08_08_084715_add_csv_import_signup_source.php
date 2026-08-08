<?php

use App\Console\Commands\ImportUsers;
use App\Enums\SignupSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens `signup_source` for accounts nobody signed up for at all: the ones
 * {@see ImportUsers} creates from a list handed over as a file.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('signup_source', SignupSource::values())
                ->default(SignupSource::SignupForm->value)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('signup_source', [
                SignupSource::AnonymousPlan->value,
                SignupSource::SignupForm->value,
                SignupSource::AuthentikSso->value,
                SignupSource::TeamMember->value,
            ])->default(SignupSource::SignupForm->value)->change();
        });
    }
};
