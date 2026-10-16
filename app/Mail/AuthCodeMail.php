<?php

namespace App\Mail;

use App\Enums\AuthCodePurpose;
use App\Services\Auth\AuthCodes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuthCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AuthCodePurpose $purpose, public string $code)
    {
        $this->onConnection(config('mail.queue_connection'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->purpose) {
                AuthCodePurpose::VerifyEmail => 'رمز تأكيد البريد — عوداً عوداً',
                AuthCodePurpose::ResetPassword => 'رمز إعادة تعيين كلمة المرور — عوداً عوداً',
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.auth-code',
            with: ['minutes' => AuthCodes::TTL_MINUTES],
        );
    }
}
