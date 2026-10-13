<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Ticket $ticket,
        public User $assignedBy,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->ticket->code}] Tiket ditugaskan kepadamu")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("{$this->assignedBy->name} menugaskan tiket \"{$this->ticket->title}\" kepadamu.")
            ->line("Prioritas: {$this->ticket->priority->value}")
            ->action('Buka tiket', config('app.frontend_url') . "/tickets/{$this->ticket->id}");
    }
}