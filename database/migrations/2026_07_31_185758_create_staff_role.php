<?php

use App\Models\TechnicalPlan;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The house's own people: an account signing up from one of the theatre's
     * own e-mail domains reads every plan that has been handed in, but holds
     * none of the technician's further rights over other groups' shows,
     * performances and teams.
     *
     * @var array<int, string>
     */
    private const STAFF_PERMISSIONS = [
        TechnicalPlan::VIEW_ALL_PERMISSION,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::create(['name' => 'staff']);

        foreach (self::STAFF_PERMISSIONS as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name]));
        }
    }

    /**
     * Reverse the migrations.
     *
     * The permissions themselves stay behind: the technician role was here
     * first and still holds them.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'staff')->delete();
    }
};
