<?php

use App\Http\Controllers\AuditLogController;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Who may read the application's audit trail — every account, format,
     * performance, team and plan change spatie/laravel-activitylog keeps.
     * It goes to the technicians alone: the same crew who already run every
     * other part of the house.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => AuditLogController::VIEW_PERMISSION]);

        Role::findByName('technician')->givePermissionTo($permission);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Deleting the permission takes it off the role with it.
        Permission::where('name', AuditLogController::VIEW_PERMISSION)->delete();
    }
};
