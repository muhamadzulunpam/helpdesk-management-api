<?php

namespace App\Notifications;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    // Kalau tiketnya sudah dihapus sebelum email diproses, lewati saja (tanpa error)
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Ticket $ticket,
        public TicketStatus $from,
        public TicketStatus $to,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->ticket->code}] Status tiketmu diperbarui")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Status tiket \"{$this->ticket->title}\" berubah dari {$this->from->label()} menjadi {$this->to->label()}.")
            ->action('Lihat tiket', config('app.frontend_url') . "/tickets/{$this->ticket->id}")
            ->line('Terima kasih telah menggunakan layanan helpdesk kami.');
    }
}