<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Inertia\Inertia;
use Inertia\Response;

class RolesController extends Controller
{
    /**
     * Show the user's own roles and permissions.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Roles', [
            'roles' => $this->translateNames('roles', $user->getRoleNames()),
            'permissions' => $this->translateNames('permissions', $user->getAllPermissions()->pluck('name')),
            'teams' => $user->toUserTeams(includeCurrent: true),
        ]);
    }

    /**
     * Translate role/permission slugs (e.g. `technical_plans.view_all`) to their
     * Estonian display label via `lang/et/{namespace}.php`, falling back to the
     * raw slug for anything not yet translated.
     *
     * @param  Collection<int, string>  $names
     * @return array<int, string>
     */
    private function translateNames(string $namespace, Collection $names): array
    {
        return $names
            ->map(fn (string $name) => Lang::has("$namespace.$name", 'et')
                ? __("$namespace.$name", locale: 'et')
                : $name)
            ->values()
            ->all();
    }
}
