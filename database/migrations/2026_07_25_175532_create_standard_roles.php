<?php

use App\Models\Show;
use App\Models\TechnicalPlan;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $role = Role::create(['name' => 'technician']);

        // The technical crew runs the house: they read every plan that has been
        // handed in and keep every show's details straight, whoever staged it.
        foreach ([TechnicalPlan::VIEW_ALL_PERMISSION, Show::EDIT_ALL_PERMISSION] as $name) {
            $role->givePermissionTo(Permission::create(['name' => $name]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
