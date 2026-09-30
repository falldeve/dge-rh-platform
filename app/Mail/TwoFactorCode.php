<?php

namespace App\Mail;

use App\Services\TwoFactorChallenge;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Model $user, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code de connexion — DGE');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.two-factor-code', with: [
            'code' => $this->code,
            'minutes' => TwoFactorChallenge::TTL_MINUTES,
        ]);
    }
}
