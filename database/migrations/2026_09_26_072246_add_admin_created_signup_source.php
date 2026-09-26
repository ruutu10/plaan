<?php

use App\Enums\SignupSource;
use App\Http\Controllers\Users\UserAdminController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens `signup_source` for accounts a technician creates by hand from the
 * account management screen; see {@see UserAdminController::store()}.
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
                SignupSource::CsvImport->value,
            ])->default(SignupSource::SignupForm->value)->change();
        });
    }
};
