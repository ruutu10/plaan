<?php

use App\Models\ClaudeReasoningLog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Who may read what the AI made of a Planka card. The reasoning is a
     * debugging aid written for the house's own people — it quotes the card
     * back, argues with itself, and is in no sense an announcement — so it goes
     * to the technicians and to the theatre's own staff, and to nobody who
     * merely has a show on the books.
     *
     * @var array<int, string>
     */
    private const ROLES = ['technician', 'staff'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => ClaudeReasoningLog::VIEW_PERMISSION]);

        foreach (self::ROLES as $name) {
            Role::findByName($name)->givePermissionTo($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Deleting the permission takes it off both roles with it.
        Permission::where('name', ClaudeReasoningLog::VIEW_PERMISSION)->delete();
    }
};
