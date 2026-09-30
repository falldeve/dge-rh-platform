<?php

namespace App\Notifications;

use App\Models\Demande;
use Illuminate\Notifications\Notification;

class DemandeDecisionNotification extends Notification
{
    public function __construct(
        public Demande $demande,
        public string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'demande_id' => $this->demande->id,
            'type' => $this->demande->type,
            'statut' => $this->demande->statut,
            'message' => $this->message,
        ];
    }
}
