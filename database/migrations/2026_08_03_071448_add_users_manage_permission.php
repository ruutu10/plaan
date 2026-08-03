<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Who keeps the accounts themselves straight: the names, the addresses and
     * the roles every one of them holds. It goes to the technicians alone —
     * handing out a role hands out every right that role carries, which is more
     * than the house's own staff are trusted with.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => User::MANAGE_PERMISSION]);

        Role::findByName('technician')->givePermissionTo($permission);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Deleting the permission takes it off the role with it.
        Permission::where('name', User::MANAGE_PERMISSION)->delete();
    }
};
