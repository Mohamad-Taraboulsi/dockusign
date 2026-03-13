<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\DocumentRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NextRecipientMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private Document $document,
        private DocumentRecipient $recipient,
        private string $accessCode,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your turn to sign: {$this->document->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.next-recipient',
            with: [
                'recipientName' => $this->recipient->name,
                'documentTitle' => $this->document->title,
                'accessCode' => $this->accessCode,
                'signingUrl' => route('sign.show', $this->recipient->id),
            ],
        );
    }
}
