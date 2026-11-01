<?php

namespace Tests\Feature;

use App\Enums\PerformanceStaffRole;
use App\Http\Resources\PlanDocument;
use App\Models\Format;
use App\Models\Performance;
use App\Models\PerformanceRecording;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\TechnicalPlanComment;
use App\Models\User;
use App\Notifications\PerformanceRecordingAvailable;
use App\Notifications\TechnicalPlanCommented;
use App\Notifications\TechnicalPlanMissing;
use App\Notifications\TechnicalPlanReceived;
use App\Notifications\TechnicalPlanSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Etendus" table every mail about a night carries. It is one component,
 * so every letter names the night by the same rows in the same order — and a
 * letter's own rows (who commented, who confirmed) come after them.
 */
class PerformanceDetailsMailTest extends TestCase
{
    use RefreshDatabase;

    private const COMMON_ROWS = ['Formaat', 'Esineja', 'Tehnik', 'Kuupäev', 'Algus', 'Kestus', 'Asukoht'];

    private Performance $performance;

    private TechnicalPlan $plan;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->performance = Performance::factory()
            ->for(Format::factory()->state(['name' => 'Improõhtu']))
            ->performedBy(Team::factory()->create(['name' => 'Trupp Tähed']))
            ->startingAt('2026-05-01', '19:30')
            ->playedAt('Väike saal')
            ->create(['duration' => 75]);

        $this->performance->staff()->attach(
            User::factory()->create(['name' => 'Tiit Tehnik']),
            ['role' => PerformanceStaffRole::Technician->value],
        );

        $this->author = User::factory()->create();
        $this->plan = TechnicalPlan::factory()->submitted()
            ->for($this->performance)
            ->for($this->author)
            ->create();
    }

    public function test_every_mail_about_a_night_carries_the_same_details(): void
    {
        foreach ($this->renderedMails() as $name => $html) {
            $this->assertInOrder($html, self::COMMON_ROWS, $name);

            foreach (['Improõhtu', 'Trupp Tähed', 'Tiit Tehnik', '01.05.2026', '19:30', '75 min', 'Väike saal'] as $value) {
                $this->assertStringContainsString($value, $html, "{$name} is missing {$value}");
            }
        }
    }

    public function test_a_letters_own_rows_follow_the_common_ones(): void
    {
        $mails = $this->renderedMails();

        $this->assertInOrder($mails['commented'], ['Asukoht', 'Kommenteeris']);
        $this->assertInOrder($mails['received'], ['Asukoht', 'Staatus', 'Kinnitas']);
        $this->assertInOrder($mails['submitted'], ['Asukoht', 'Kontakt', $this->author->email, 'Lühikirjeldus']);
    }

    /**
     * An act's own title, when the night has one, is named alongside the
     * format — an evening shared by several acts would otherwise read the same
     * in every letter.
     */
    public function test_the_performance_title_is_named_with_the_format(): void
    {
        $this->performance->update(['title' => 'Külalisetendus']);
        $this->plan->refresh();

        foreach ($this->renderedMails() as $name => $html) {
            $this->assertInOrder($html, ['Formaat', 'Improõhtu — Külalisetendus', 'Esineja'], $name);
        }
    }

    /**
     * A row the night has no value for is still drawn — with the em dash, or
     * for the technician the words that the answer is still coming — so no
     * letter's table is shorter than another's. The venue is the exception: a
     * night in the house's own room names none.
     */
    public function test_a_missing_value_keeps_its_row(): void
    {
        $this->performance->staff()->detach();
        $this->performance->update(['location' => null, 'duration' => null]);

        $html = (new TechnicalPlanMissing($this->performance->fresh(), 'https://plaan.test/link'))
            ->toMail($this->author)
            ->render();

        $this->assertInOrder($html, ['Formaat', 'Esineja', 'Tehnik', 'Kuupäev', 'Algus', 'Kestus', '—']);
        $this->assertStringContainsString(PlanDocument::NO_TECHNICIAN, $html);
        $this->assertStringNotContainsString('Asukoht', $html);
    }

    /**
     * A plan whose night has since been put aside still names something: its
     * own key, the same name the subject line falls back on.
     */
    public function test_a_plan_without_a_night_is_named_by_its_key(): void
    {
        $this->performance->delete();

        $html = (new TechnicalPlanReceived($this->plan->fresh(), User::factory()->create()))->toMail($this->author)->render();

        $this->assertInOrder($html, ['Formaat', $this->plan->token, 'Esineja']);
        $this->assertStringContainsString(PlanDocument::NO_TECHNICIAN, $html);
    }

    /**
     * @return array<string, string>
     */
    private function renderedMails(): array
    {
        $comment = TechnicalPlanComment::factory()->for($this->plan, 'plan')->create();
        $recording = PerformanceRecording::factory()->for($this->performance)->create();

        return [
            'submitted' => (new TechnicalPlanSubmitted($this->plan))->toMail($this->author)->render(),
            'received' => (new TechnicalPlanReceived($this->plan, User::factory()->create()))->toMail($this->author)->render(),
            'commented' => (new TechnicalPlanCommented($comment))->toMail($this->author)->render(),
            'missing' => (new TechnicalPlanMissing($this->performance, 'https://plaan.test/link'))->toMail($this->author)->render(),
            'recording' => (new PerformanceRecordingAvailable($recording))->toMail($this->author)->render(),
        ];
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function assertInOrder(string $html, array $needles, string $mail = ''): void
    {
        $offset = 0;

        foreach ($needles as $needle) {
            $position = strpos($html, $needle, $offset);

            $this->assertNotFalse($position, trim("{$mail} is missing {$needle} after offset {$offset}"));

            $offset = $position + strlen($needle);
        }
    }
}
