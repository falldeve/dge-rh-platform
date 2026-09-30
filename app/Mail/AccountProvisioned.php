<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountProvisioned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $temporaryPassword) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte DGE — accès');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-provisioned', with: [
            'user' => $this->user,
            'password' => $this->temporaryPassword,
            'url' => route('login'),
        ]);
    }
}
