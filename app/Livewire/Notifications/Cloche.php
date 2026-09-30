<?php

namespace App\Livewire\Notifications;

use Livewire\Component;

class Cloche extends Component
{
    public function toutMarquerLu(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notifications.cloche', [
            'nonLues' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->take(10)->get(),
        ]);
    }
}
