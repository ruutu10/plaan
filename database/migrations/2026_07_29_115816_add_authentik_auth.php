<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\SignupSource;
use Illuminate\Support\Facades\DB;

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

         $values = implode(', ', array_map(
            fn (string $value): string => "'{$value}'",
            SignupSource::values(),
        ));

        DB::statement("ALTER TABLE users MODIFY signup_source ENUM({$values}) NOT NULL DEFAULT '".SignupSource::SignupForm->value."'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['authentik_id']);
            $table->dropColumn('authentik_id');
        });
        DB::statement("ALTER TABLE users MODIFY signup_source ENUM('anonymous-plan', 'signup-form') NOT NULL DEFAULT 'signup-form'");

    }
};
