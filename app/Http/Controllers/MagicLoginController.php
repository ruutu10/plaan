<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateUserByEmail;
use App\Actions\MagicLink\LogInAndVerifyEmail;
use App\Enums\SignupSource;
use App\Models\TechnicalPlan;
use App\Notifications\MagicLoginLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MagicLink\MagicLink;

class MagicLoginController extends Controller
{
    public function __construct(private FindOrCreateUserByEmail $findOrCreateUser) {}

    /**
     * E-mail a one-time magic login link to the given address, provisioning a
     * lightweight account first if the address is not yet registered.
     *
     * The same link is asked for at two doors, which differ only in where they
     * leave the user and how they answer: the plan wizard's own login step
     * talks JSON and lands on the plan being read, while the application's
     * login page is an Inertia screen and lands on the dashboard.
     */
    public function send(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            // Sent by a visitor reading a shared plan: where to put them once
            // they are signed in.
            'token' => ['nullable', 'string'],
        ], [
            'email.required' => 'Sisesta e-posti aadress.',
            'email.email' => 'Sisesta kehtiv e-posti aadress.',
        ]);

        $fromLoginPage = $request->routeIs('login.magic-link');

        $user = $this->findOrCreateUser->handle(
            $data['email'],
            $fromLoginPage ? SignupSource::SignupForm : SignupSource::AnonymousPlan,
        );

        $action = new LogInAndVerifyEmail($user, $fromLoginPage
            // Whatever page sent them to the login screen, if anything did.
            ? redirect()->intended(route('dashboard'))
            : $this->destination($data['token'] ?? null));

        $magicLink = MagicLink::create($action, lifetime: 30, numMaxVisits: 4);

        // An unauthenticated endpoint that mails anybody who asks: the caller's
        // origin is the part that matters when it is being leant on.
        Log::info('Sending magic login link to user', [
            'user' => $user->id,
            'new_account' => $user->wasRecentlyCreated,
            'ip' => $request->ip(),
        ]);

        $user->notify(new MagicLoginLink($magicLink->url));

        if ($fromLoginPage) {
            return back()->with(
                'status',
                "Saatsime sisselogimislingi aadressile {$user->email}. Link kehtib 30 minutit.",
            );
        }

        return response()->json(['sent' => true]);
    }

    /**
     * Where the magic link leaves the user: back at the plan they were reading
     * when they asked for it, or the wizard's own page when they came to it
     * without one.
     *
     * A key that names no plan is not worth failing the login over — the plan
     * has been deleted since the link was shared, and the wizard is a better
     * place to land than an error.
     */
    private function destination(?string $key): RedirectResponse
    {
        if (blank($key)) {
            return redirect()->route('technical-plan.index');
        }

        $plan = TechnicalPlan::firstWhere('token', $key);

        if ($plan === null) {
            Log::info('A login was asked for from a plan that no longer exists', [
                'token' => $key,
            ]);

            return redirect()->route('technical-plan.index');
        }

        return redirect()->route('technical-plan.public', $plan);
    }
}
