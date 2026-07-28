<?php

namespace App\Enums;

use App\Concerns\HasValues;

enum SignupSource: string
{
    use HasValues;

    case AnonymousPlan = 'anonymous-plan';
    case SignupForm = 'signup-form';
}
