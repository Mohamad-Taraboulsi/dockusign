<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\DocumentRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentSigningMailable extends Mailable
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
            subject: $this->document->subject ?? "Please sign: {$this->document->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-signing',
            with: [
                'recipientName' => $this->recipient->name,
                'senderName' => $this->document->user?->name ?? 'Unknown',
                'documentTitle' => $this->document->title,
                'documentMessage' => $this->document->message,
                'accessCode' => $this->accessCode,
                'signingUrl' => route('sign.show', $this->recipient->id),
            ],
        );
    }
}
