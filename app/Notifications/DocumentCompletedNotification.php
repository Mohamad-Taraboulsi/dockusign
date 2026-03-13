<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Document $document) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Completed: {$this->document->title}")
            ->greeting('Hello,')
            ->line("All recipients have signed **{$this->document->title}**.")
            ->line('The document is now complete.')
            ->action('View Document', route('esign.documents.show', $this->document->id));
    }
}
