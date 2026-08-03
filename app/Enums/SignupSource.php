<?php

namespace App\Enums;

use App\Concerns\HasValues;

enum SignupSource: string
{
    use HasValues;

    case AnonymousPlan = 'anonymous-plan';
    case SignupForm = 'signup-form';
    case AuthentikSso = 'authentik-sso';

    /**
     * How the door the account came through is named in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::AnonymousPlan => 'Tehnikaplaani vorm',
            self::SignupForm => 'Registreerimisvorm',
            self::AuthentikSso => 'Ruutu10 konto',
        };
    }
}
