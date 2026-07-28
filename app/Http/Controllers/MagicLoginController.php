<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateUserByEmail;
use App\Notifications\MagicLoginLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MagicLink\Actions\LoginAction;
use MagicLink\MagicLink;

class MagicLoginController extends Controller
{
    public function __construct(private FindOrCreateUserByEmail $findOrCreateUser) {}

    /**
     * E-mail a one-time magic login link to the given address, provisioning a
     * lightweight account first if the address is not yet registered.
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Sisesta e-posti aadress.',
            'email.email' => 'Sisesta kehtiv e-posti aadress.',
        ]);

        $user = $this->findOrCreateUser->handle($data['email']);

        $action = new LoginAction($user, redirect()->route('technical-plan.index'));

        $magicLink = MagicLink::create($action, lifetime: 30, numMaxVisits: 4);

        // An unauthenticated endpoint that mails anybody who asks: the caller's
        // origin is the part that matters when it is being leant on.
        Log::info('Sending magic login link to user', [
            'user' => $user->id,
            'new_account' => $user->wasRecentlyCreated,
            'ip' => $request->ip(),
        ]);

        $user->notify(new MagicLoginLink($magicLink->url));

        return response()->json(['sent' => true]);
    }
}
