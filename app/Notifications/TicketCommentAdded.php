<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TicketCommentAdded extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Ticket $ticket,
        public Comment $comment,
        public User $author,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->ticket->code}] Komentar baru pada tiket")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("{$this->author->name} menulis komentar pada tiket \"{$this->ticket->title}\":")
            ->line('"'.Str::limit($this->comment->body, 200).'"')
            ->action('Balas komentar', config('app.frontend_url')."/tickets/{$this->ticket->id}");
    }
}
