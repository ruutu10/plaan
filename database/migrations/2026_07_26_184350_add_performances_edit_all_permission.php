<?php

use App\Models\Performance;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Managing a show's dated stagings house-wide is its own right, held by the
 * technical crew alongside the ones they already have.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => Performance::EDIT_ALL_PERMISSION]);

        Role::where('name', 'technician')->first()?->givePermissionTo($permission);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', Performance::EDIT_ALL_PERMISSION)->delete();
    }
};
