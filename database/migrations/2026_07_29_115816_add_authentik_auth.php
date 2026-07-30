<?php

use App\Enums\SignupSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a user to their identity at the external Authentik IdP, so a
 * returning SSO login is matched by subject rather than by the mutable
 * e-mail address. Nullable: password/passkey-only accounts never get one.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('authentik_id')->nullable()->unique()->after('email');
        });

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
            ])->default(SignupSource::SignupForm->value)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['authentik_id']);
            $table->dropColumn('authentik_id');
        });
    }
};
