<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The help page behind the header's "Abi" icon: docs/MANUAL.md, rendered.
 */
class ManualTest extends TestCase
{
    public function test_anyone_may_read_the_manual(): void
    {
        $this->get(route('manual'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manual')
                ->where('html', fn (string $html) => str_contains($html, '<h1'))
            );
    }

    public function test_the_manual_is_rendered_from_markdown_with_tables(): void
    {
        $response = $this->get(route('manual'));

        /** @var string $html */
        $html = $response->viewData('page')['props']['html'];

        // A table of the manual's own, proving the GFM extension is on: without
        // it the row would reach the reader as a line of pipes.
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringNotContainsString('| --- |', $html);
    }
}
