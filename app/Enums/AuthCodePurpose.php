<?php

namespace App\Enums;

enum AuthCodePurpose: string
{
    case VerifyEmail = 'verify_email';
    case ResetPassword = 'reset_password';
}
