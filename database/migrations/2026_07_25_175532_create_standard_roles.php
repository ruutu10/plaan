<?php

use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Models\TechnicalPlan;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The technical crew runs the house: they read every plan that has been
     * handed in, and keep every show, every performance of it and every group
     * that staged it straight, whoever it belongs to. Each is a right of its
     * own — none implies any of the others.
     *
     * @var array<int, string>
     */
    private const TECHNICIAN_PERMISSIONS = [
        TechnicalPlan::VIEW_ALL_PERMISSION,
        Show::EDIT_ALL_PERMISSION,
        Performance::EDIT_ALL_PERMISSION,
        Team::EDIT_ALL_PERMISSION,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::create(['name' => 'technician']);

        foreach (self::TECHNICIAN_PERMISSIONS as $name) {
            $role->givePermissionTo(Permission::create(['name' => $name]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', self::TECHNICIAN_PERMISSIONS)->delete();
        Role::where('name', 'technician')->delete();
    }
};
