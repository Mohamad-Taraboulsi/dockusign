<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\DocumentRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentDeclinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Document $document,
        private DocumentRecipient $recipient,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Declined: {$this->document->title}")
            ->greeting('Hello,')
            ->line("{$this->recipient->email} has declined to sign **{$this->document->title}**.");

        if ($this->recipient->decline_reason) {
            $mail->line("Reason: {$this->recipient->decline_reason}");
        }

        return $mail->action('View Document', route('esign.documents.show', $this->document->id));
    }
}
