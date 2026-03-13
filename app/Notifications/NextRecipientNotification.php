<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\DocumentRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NextRecipientNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Document $document,
        private DocumentRecipient $recipient,
        private string $accessCode,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $signingUrl = route('sign.show', $this->recipient->id);

        return (new MailMessage)
            ->subject("Your turn to sign: {$this->document->title}")
            ->greeting("Hello {$this->recipient->name},")
            ->line("It's your turn to sign **{$this->document->title}**.")
            ->line("Your access code is: **{$this->accessCode}**")
            ->action('Review & Sign', $signingUrl)
            ->line('Please sign in or register to access the document.');
    }
}
