<?php

use App\Actions\GrantStaffAccess;
use App\Models\Performance;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The roles that follow the whole house's bill: the crew who run every night
     * of it, and the house's own people, who read it without being handed the
     * power to change other groups' performances.
     *
     * @var array<int, string>
     */
    private const ROLES = ['technician', GrantStaffAccess::ROLE];

    /**
     * Who may open the house-wide overview of every performance. Split off from
     * the edit-all permission the crew already hold, the same way reading every
     * technical plan is split from confirming one: the staff role gains the
     * listing and nothing more.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => Performance::VIEW_ALL_PERMISSION]);

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
        Permission::where('name', Performance::VIEW_ALL_PERMISSION)->delete();
    }
};
