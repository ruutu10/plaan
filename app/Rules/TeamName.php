<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Routing\Route as RouteElement;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A team's name becomes its slug, and a slug sits at the root of the URL space
 * — so a team may not be called something the application already answers to.
 *
 * The names come from two places: the routes this application actually
 * registers, gathered at runtime so a new route is covered without anyone
 * remembering to add it, and the fixed list in `config/teams.php`.
 */
class TeamName implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = strtolower(trim($value));

        if (in_array($name, $this->reservedNames(), true)) {
            $fail(__('This team name is reserved and cannot be used.'));
        }
    }

    /**
     * Get a list of all reserved names.
     *
     * @return array<int, string>
     */
    protected function reservedNames(): array
    {
        return once(fn () => collect($this->routesPrefixes())
            ->merge(Config::array('teams.reserved_names'))
            ->unique()
            ->sort()
            ->values()
            ->toArray());
    }

    /**
     * Get a list of reserved names from the application's route prefixes.
     *
     * @return array<int, string>
     */
    protected function routesPrefixes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->map(fn (RouteElement $route) => $route->uri)
            ->map(fn (string $uri) => explode('/', $uri)[0])
            ->reject(fn (string $uri) => str_contains($uri, '{'))
            ->filter(fn (string $uri) => $uri !== '')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}
