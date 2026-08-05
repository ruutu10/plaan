<?php

use App\Actions\FindOrCreateUserByEmail;
use App\Enums\SignupSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens `signup_source` for accounts provisioned by being added straight to
 * a team, the same way {@see FindOrCreateUserByEmail} already
 * provisions one for an unrecognised magic-link address.
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
            ])->default(SignupSource::SignupForm->value)->change();
        });
    }
};
