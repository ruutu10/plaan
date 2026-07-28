<?php

namespace Tests\Feature;

use App\Http\Resources\PlanDocument;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The plan document is rendered twice — here for the mail, and in
 * `presentPlan.ts` for the wizard's review page, the printout and the
 * technician's playback view. Both are asserted against the same fixture, so a
 * display rule changed on one side alone fails a test rather than quietly
 * shipping a mail that disagrees with the printout.
 *
 * The matching TypeScript suite is
 * `resources/js/components/technical-plan/presentPlan.test.ts`.
 */
class PlanDocumentTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function fixture(): array
    {
        $path = dirname(__DIR__).'/fixtures/plan-document.json';

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function caseProvider(): array
    {
        $cases = [];

        foreach (self::fixture()['cases'] as $case) {
            $cases[$case['name']] = [$case];
        }

        return $cases;
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[DataProvider('caseProvider')]
    public function test_it_presents_a_plan_as_the_document_the_reader_sees(array $case): void
    {
        $document = PlanDocument::make($case['plan'])
            ->withContact($case['contact'])
            ->resolve(Request::create('/'));

        $this->assertSame($case['expected'], $document);
    }

    public function test_it_formats_file_sizes_the_same_way_on_both_sides(): void
    {
        foreach (self::fixture()['fileSizes'] as $bytes => $expected) {
            $this->assertSame($expected, PlanDocument::fileSize($bytes), "for {$bytes} bytes");
        }
    }

    public function test_it_labels_every_status_and_falls_back_to_draft(): void
    {
        foreach (self::fixture()['statusLabels'] as $status => $expected) {
            $this->assertSame($expected, PlanDocument::statusLabel($status), "for status '{$status}'");
        }
    }
}
