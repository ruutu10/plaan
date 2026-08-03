<?php

namespace Tests\Feature;

use App\Models\Performance;
use App\Models\Show;
use Database\Seeders\PerformanceSeeder;
use Database\Seeders\ShowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stand-in show and its one performance: where the plans go whose night is
 * not on the books yet. Every plan names a performance, so this is what makes
 * "the show is not in the list" answerable without leaving a plan nameless.
 */
class PlaceholderPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeders_register_the_stand_in_show_and_its_performance(): void
    {
        $this->seed([ShowSeeder::class, PerformanceSeeder::class]);

        $show = Show::where('name', Show::PLACEHOLDER_NAME)->firstOrFail();

        $this->assertNull($show->team_id);
        $this->assertTrue($show->isPlaceholder());
        $this->assertSame(1, $show->performances()->count());
        $this->assertTrue($show->performances()->first()->isPlaceholder());
    }

    public function test_the_stand_in_performance_is_dated_far_enough_ahead_to_stay_on_offer(): void
    {
        // The picker only offers what is still to come, and nothing about this
        // one should ever read as a night somebody is playing.
        $performance = Performance::placeholder();

        $this->assertTrue($performance->date->isAfter(now()->addYears(4)));
    }

    public function test_asking_for_the_stand_in_twice_registers_it_once(): void
    {
        $first = Performance::placeholder();
        $second = Performance::placeholder();

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Show::where('name', Show::PLACEHOLDER_NAME)->count());
        $this->assertSame(1, Performance::count());
    }

    public function test_the_stand_in_is_brought_back_rather_than_replaced(): void
    {
        // A second one under the same name would split the plans filed without
        // a night between two drawers.
        $original = Performance::placeholder();
        $original->show->delete();

        $restored = Performance::placeholder();

        $this->assertTrue($restored->is($original));
        $this->assertFalse($restored->trashed());
        $this->assertSame(1, Show::withTrashed()->where('name', Show::PLACEHOLDER_NAME)->count());
    }

}
