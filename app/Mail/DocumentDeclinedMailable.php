<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\DocumentRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentDeclinedMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private Document $document,
        private DocumentRecipient $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Declined: {$this->document->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-declined',
            with: [
                'documentTitle' => $this->document->title,
                'recipientEmail' => $this->recipient->email,
                'recipientName' => $this->recipient->name,
                'declineReason' => $this->recipient->decline_reason,
                'documentUrl' => route('esign.documents.show', $this->document->id),
            ],
        );
    }
}
