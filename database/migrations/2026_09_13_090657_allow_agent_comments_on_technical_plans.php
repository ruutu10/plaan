<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Not everything that writes on a plan has an account. The technician AI reads
 * every plan as it is submitted and says so on the plan itself when it finds
 * something that would stop the show — see
 * App\Listeners\ReviewSubmittedPlanWithAi — and it is not a person the
 * performer could write back to, so it gets a name rather than a user.
 *
 * The name is written down beside the comment rather than resolved from
 * somewhere later: it is how the remark is signed on the page for good, and a
 * comment should go on saying who said it however the agent is renamed.
 *
 * A comment by a real person still belongs to them and still goes when they do
 * — the foreign key is put back exactly as it was, only optional.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('technical_plan_comments', function (Blueprint $table) {
            // By columns rather than by name, so SQLite can rebuild the table.
            $table->dropForeign(['user_id']);
        });

        Schema::table('technical_plan_comments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('author_name')->nullable()->after('user_id');
        });

        Schema::table('technical_plan_comments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Whatever the agent said has no author to fall back on.
        DB::table('technical_plan_comments')->whereNull('user_id')->delete();

        Schema::table('technical_plan_comments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('technical_plan_comments', function (Blueprint $table) {
            $table->dropColumn('author_name');
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('technical_plan_comments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
