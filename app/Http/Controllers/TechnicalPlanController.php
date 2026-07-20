<?php

namespace App\Http\Controllers;

use App\Enums\TechnicalPlanStatus;
use App\Http\Requests\StoreTechnicalPlanRequest;
use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TechnicalPlanController extends Controller
{
    /**
     * Show the landing page (gate) of the technical-plan wizard.
     */
    public function index(): Response
    {
        return Inertia::render('TechnicalPlan', [
            'config' => $this->wizardConfig(),
            'initialPlan' => null,
        ]);
    }

    /**
     * Open a previously shared plan as the basis for a new plan.
     */
    public function public(TechnicalPlan $plan): Response
    {
        return Inertia::render('TechnicalPlan', [
            'config' => $this->wizardConfig(),
            'initialPlan' => $plan->toPayload(),
        ]);
    }

    /**
     * Return a saved plan's payload as JSON (open by key).
     */
    public function show(TechnicalPlan $plan): JsonResponse
    {
        return response()->json($plan->toPayload());
    }

    /**
     * Store (or update) a plan and return its shareable token & public link.
     */
    public function store(StoreTechnicalPlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $submitting = (bool) ($data['submit'] ?? false);

        $user = $this->resolveContactUser($data['meta']['contactEmail']);

        $attributes = [
            'user_id' => $user->id,
            'performance_id' => $data['meta']['performanceId'] ?? null,
            'sound' => $data['sound'],
            'scenes' => array_values($data['scenes']),
            'equipment' => array_merge($data['equipment'], ['items' => array_values($data['equipment']['items'] ?? [])]),
            'extra' => array_merge($data['extra'], ['files' => array_values($data['extra']['files'] ?? [])]),
        ];

        $plan = TechnicalPlan::query()
            ->firstOrNew(['token' => $data['token'] ?? null]);

        if ($submitting) {
            $attributes['status'] = TechnicalPlanStatus::Submitted;
            $attributes['submitted_at'] = now();
        } elseif (! $plan->exists) {
            $attributes['status'] = TechnicalPlanStatus::Draft;
        }

        $plan->fill($attributes)->save();

        return response()->json([
            'token' => $plan->token,
            'status' => $plan->status->value,
            'publicUrl' => route('technical-plan.public', $plan),
        ]);
    }

    /**
     * Find plans submitted with a given contact e-mail.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Sisesta kehtiv e-posti aadress.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower($validator->validated()['email']);

        $results = TechnicalPlan::query()
            ->with('performance.team')
            ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))
            ->where('status', TechnicalPlanStatus::Submitted)
            ->latest('submitted_at')
            ->limit(50)
            ->get()
            ->map(fn (TechnicalPlan $plan) => [
                'token' => $plan->token,
                'title' => trim(($plan->performance?->show_name ?: 'Nimeta plaan').($plan->performance?->team?->name ? ' — '.$plan->performance->team->name : '')),
                'sub' => collect([
                    $plan->performance?->show_date?->format('d.m.Y'),
                    $plan->submitted_at ? 'esitatud '.$plan->submitted_at->format('d.m.Y') : null,
                ])->filter()->implode(' · '),
            ]);

        return response()->json(['results' => $results]);
    }

    /**
     * List upcoming performances the user can attach a plan to.
     */
    public function performances(): JsonResponse
    {
        $results = Performance::query()
            ->with('team')
            ->whereDate('show_date', '>=', now()->toDateString())
            ->orderBy('show_date')
            ->limit(100)
            ->get()
            ->map(fn (Performance $performance) => [
                'id' => $performance->id,
                'performer' => $performance->team?->name ?? '',
                'showName' => $performance->show_name,
                'showDate' => $performance->show_date?->format('Y-m-d'),
                'duration' => $performance->duration,
                'description' => $performance->description ?? '',
            ]);

        return response()->json(['results' => $results]);
    }

    /**
     * Ask the technician AI to review a plan and suggest improvements.
     */
    public function aiReview(StoreTechnicalPlanRequest $request): JsonResponse
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            return response()->json([
                'message' => 'AI ülevaatus pole seadistatud. Lisa ANTHROPIC_API_KEY.',
            ], 422);
        }

        $markdown = $this->buildPlanMarkdown($request->validated());

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model', 'claude-opus-4-8'),
            'max_tokens' => 1400,
            'system' => 'Oled Ruutu10 improteatri kogenud valgus- ja helitehnik. Vaatad üle esineja etenduse tehnikaplaani ja annad lühikese, praktilise tagasiside eesti keeles. Too välja puuduv või ebaselge info (nt puuduvad helifailide lingid, ebamäärased üleminekud, täitmata kohustuslikud väljad, vastuolud) ning anna konkreetsed soovitused parandusteks. Vasta lühidalt: alusta ühe lausega üldmuljest, seejärel täpploend soovitustega. Ära leiuta infot, mida plaanis pole.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Palun vaata see tehnikaplaan üle ja anna soovitused, mida esineja võiks parandada või täpsustada:\n\n".$markdown,
                ],
            ],
        ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'AI ülevaatus ebaõnnestus. Proovi hetke pärast uuesti.',
            ], 422);
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return response()->json([
            'review' => trim($text) ?: 'Tagasisidet ei saadud. Proovi uuesti.',
        ]);
    }

    /**
     * Find the contact user by e-mail, creating a lightweight account if none
     * exists yet, so the plan can be linked to a real user record.
     */
    private function resolveContactUser(string $email): User
    {
        $email = strtolower(trim($email));

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => Str::of($email)->before('@')->trim()->value() ?: 'Esineja',
                'password' => Hash::make(Str::random(40)),
            ],
        );
    }

    /**
     * Static configuration passed to the wizard frontend.
     *
     * @return array<string, mixed>
     */
    private function wizardConfig(): array
    {
        return [
            'deadlineHours' => (int) config('technical_plan.deadline_hours', 24),
            'techEmail' => (string) config('technical_plan.tech_email', 'ando@ruutu10.ee'),
        ];
    }

    /**
     * Build a compact Markdown representation of the plan for the AI reviewer.
     *
     * @param  array<string, mixed>  $data
     */
    private function buildPlanMarkdown(array $data): string
    {
        $meta = $data['meta'];
        $sound = $data['sound'];
        $equipment = $data['equipment'];
        $extra = $data['extra'];

        $dash = fn ($value) => filled($value) ? trim((string) $value) : '—';
        $cell = fn ($value) => str_replace("\n", ' ', $dash($value));

        $md = '# Tehnikaplaan: '.$dash($meta['showName'] ?? null)."\n\n";
        $md .= '- Esineja: '.$dash($meta['performer'] ?? null)."\n";
        $md .= '- Kuupäev: '.$dash($meta['showDate'] ?? null)."\n";
        $md .= '- Kestus: '.(filled($meta['duration'] ?? null) ? $meta['duration'].' min' : '—')."\n";
        $md .= '- Kontakt: '.$dash($meta['contactEmail'] ?? null)."\n";
        $md .= '- Lühikirjeldus: '.$cell($meta['description'] ?? null)."\n\n";

        $md .= "## Heliplaan\n";
        $md .= '- Mikrofonid: '.(($sound['micsMode'] ?? 'no') === 'yes' ? 'Jah'.(filled($sound['micsDetail'] ?? null) ? ' — '.$sound['micsDetail'] : '') : 'Ei')."\n";
        $md .= '- Oma muusik: '.(($sound['musicianMode'] ?? 'no') === 'yes' ? 'Jah'.(filled($sound['musicianDetail'] ?? null) ? ' — '.$sound['musicianDetail'] : '') : 'Ei')."\n\n";

        $md .= "## Stseenid\n\n| Nr | Nimi | Valgus | Heli | Märkmed |\n|---|---|---|---|---|\n";
        foreach (array_values($data['scenes']) as $i => $scene) {
            $heli = collect([$scene['soundUrl'] ?? null, $scene['sound'] ?? null])->filter()->implode(' ');
            $md .= '| '.($i + 1).' | '.$cell($scene['name'] ?? null).' | '.$cell($scene['light'] ?? null).' | '.($heli !== '' ? str_replace("\n", ' ', $heli) : '—').' | '.$cell($scene['notes'] ?? null)." |\n";
        }

        $md .= "\n## Erivahendid\n";
        foreach (($equipment['items'] ?? []) as $item) {
            if (filled($item['name'] ?? null) || filled($item['use'] ?? null)) {
                $md .= '- '.$dash($item['name'] ?? null).': '.$cell($item['use'] ?? null)."\n";
            }
        }
        $smoke = $equipment['smoke'] ?? 'yes';
        $md .= '- Suitsuefektid: '.($smoke === 'no' ? 'Ei' : ($smoke === 'yes' ? 'Jah' : 'Jah, minimaalselt'))."\n";
        $md .= '- Tehniku pakkumised: '.(($equipment['suggestions'] ?? 'yes') === 'yes' ? 'Jah' : 'Ei').(filled($equipment['suggestNote'] ?? null) ? ' — '.$equipment['suggestNote'] : '')."\n";

        $md .= "\n## Lisainfo\n".$cell($extra['notes'] ?? null)."\n";

        return $md;
    }
}
