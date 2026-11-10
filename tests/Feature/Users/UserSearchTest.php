<?php

namespace Tests\Feature\Users;

use App\Http\Controllers\Users\UserAdminController;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Finding an account by name — {@see UserAdminController::search()} — for any
 * screen that adds a person to something.
 */
class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_search_finds_accounts_by_part_of_their_name(): void
    {
        $arne = User::factory()->create(['name' => 'Arne Tamm', 'email' => 'arne@example.com']);
        User::factory()->create(['name' => 'Berit Kask']);

        $this->actingAs($this->teamMember())
            ->getJson(route('api.users.search', ['q' => 'tamm']))
            ->assertOk()
            ->assertExactJson(['data' => [['id' => $arne->id, 'name' => 'Arne Tamm']]]);
    }

    public function test_every_word_of_the_search_must_match_in_any_order(): void
    {
        $arne = User::factory()->create(['name' => 'Arne Tamm']);
        User::factory()->create(['name' => 'Arne Kask']);

        $this->actingAs($this->teamMember())
            ->getJson(route('api.users.search', ['q' => 'TAMM arn']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $arne->id);
    }

    public function test_names_beginning_with_the_search_come_first(): void
    {
        User::factory()->create(['name' => 'Aaron Mart']);
        $mart = User::factory()->create(['name' => 'Marti Kask']);

        $this->actingAs($this->teamMember())
            ->getJson(route('api.users.search', ['q' => 'mart']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $mart->id);
    }

    public function test_like_wildcards_in_the_search_match_only_themselves(): void
    {
        User::factory()->create(['name' => 'Arne Tamm']);
        $searcher = $this->teamMember();

        $this->actingAs($searcher)
            ->getJson(route('api.users.search', ['q' => '%%%%']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($searcher)
            ->getJson(route('api.users.search', ['q' => 'A___']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function injectionPayloads(): array
    {
        return [
            'tautology' => ["' OR '1'='1"],
            'comment out the rest' => ["x' OR 1=1 -- "],
            'stacked statement' => ["'; DROP TABLE users; --"],
            'union' => ["' UNION SELECT id, email FROM users --"],
            'escape character' => ["!' OR '1'='1"],
            'backslash quote' => ["\\' OR 1=1 #"],
        ];
    }

    #[DataProvider('injectionPayloads')]
    public function test_the_search_term_reaches_the_database_only_as_a_bound_value(string $payload): void
    {
        User::factory()->create(['name' => 'Arne Tamm']);
        $searcher = $this->teamMember();

        $words = preg_split('/\s+/u', trim($payload), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        // An innocent search with the same number of words: the statement may
        // only ever change shape with how many words there are, never with
        // what they say.
        $innocent = implode(' ', array_fill(0, count($words), 'arne'));

        $malicious = $this->searchQuery($searcher, $payload);
        $baseline = $this->searchQuery($searcher, $innocent);

        // What was typed made no difference to the statement itself…
        $this->assertSame($baseline['sql'], $malicious['sql']);

        // …it travelled as data alongside it, one bound value per word plus
        // the ranking's own — and still reads as what was typed once the LIKE
        // escapes are taken off.
        $this->assertCount(count($words) + 1, $malicious['bindings']);
        $unescape = fn (string $bound): string => preg_replace('/!(.)/u', '$1', trim($bound, '%'));
        $this->assertSame(
            array_map(fn (string $word): string => mb_strtolower($word), $words),
            array_map($unescape, array_slice($malicious['bindings'], 0, count($words))),
        );

        // And the table is still there, every row in it.
        $this->assertSame(2, User::query()->count());
    }

    /**
     * Run a search and hand back the statement it sent and the values bound
     * to it, after checking that the payload, read as a name, found nobody.
     *
     * @return array{sql: string, bindings: list<string>}
     */
    private function searchQuery(User $searcher, string $term): array
    {
        $issued = null;

        DB::listen(function (QueryExecuted $query) use (&$issued): void {
            if (str_contains($query->sql, 'LIKE')) {
                $issued = ['sql' => $query->sql, 'bindings' => array_map('strval', $query->bindings)];
            }
        });

        $response = $this->actingAs($searcher)
            ->getJson(route('api.users.search', ['q' => $term]))
            ->assertOk();

        if (! str_starts_with($term, 'arne')) {
            $response->assertJsonCount(0, 'data');
        }

        $this->assertNotNull($issued, 'The search query was not issued.');

        return $issued;
    }

    public function test_the_search_answers_with_a_limited_number_of_accounts(): void
    {
        User::factory()->count(UserAdminController::SEARCH_LIMIT + 5)->sequence(
            fn ($sequence) => ['name' => 'Kaspar '.$sequence->index],
        )->create();

        $this->actingAs($this->teamMember())
            ->getJson(route('api.users.search', ['q' => 'kaspar']))
            ->assertOk()
            ->assertJsonCount(UserAdminController::SEARCH_LIMIT, 'data');
    }

    public function test_the_search_needs_at_least_four_characters(): void
    {
        User::factory()->create(['name' => 'Ann']);
        $searcher = $this->teamMember();

        $this->actingAs($searcher)
            ->getJson(route('api.users.search', ['q' => 'Ann']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);

        $this->actingAs($searcher)
            ->getJson(route('api.users.search'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    public function test_a_technician_may_search(): void
    {
        $technician = $this->technician();
        $technician->teams()->detach();

        $this->actingAs($technician)
            ->getJson(route('api.users.search', ['q' => 'arne']))
            ->assertOk();
    }

    public function test_an_account_in_no_group_and_holding_no_role_may_not_search(): void
    {
        $user = User::factory()->create();
        $user->teams()->detach();

        $this->actingAs($user)
            ->getJson(route('api.users.search', ['q' => 'arne']))
            ->assertForbidden();
    }

    public function test_guests_may_not_search(): void
    {
        $this->getJson(route('api.users.search', ['q' => 'arne']))
            ->assertUnauthorized();
    }

    /**
     * An account with no role, standing in a group — the plainest one allowed
     * to search.
     */
    private function teamMember(): User
    {
        return User::factory()->create();
    }
}
