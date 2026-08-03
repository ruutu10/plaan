<?php

namespace App\Http\Middleware;

use App\Models\Performance;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'appVersion' => config('app.version'),
            'contactEmail' => Str::reverse(config('technical_plan.tech_email')), // UTF RTL protection for email scraping
            'auth' => [
                'user' => $user,
                // What the signed-in user is allowed to do beyond their own
                // teams — the frontend hides what the backend would refuse.
                'can' => fn (): array => [
                    'viewAllTechnicalPlans' => (bool) $user?->can(TechnicalPlan::VIEW_ALL_PERMISSION),
                    'editAllTechnicalPlans' => (bool) $user?->can(TechnicalPlan::EDIT_ALL_PERMISSION),
                    'manageAllTeams' => (bool) $user?->can(Team::EDIT_ALL_PERMISSION),
                    'manageAllPerformances' => (bool) $user?->can(Performance::EDIT_ALL_PERMISSION),
                    'manageUsers' => (bool) $user?->can(User::MANAGE_PERMISSION),
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
        ];
    }
}
