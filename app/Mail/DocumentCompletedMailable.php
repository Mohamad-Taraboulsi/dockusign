<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DocumentCompletedMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private Document $document,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Completed: {$this->document->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-completed',
            with: [
                'documentTitle' => $this->document->title,
                'documentUrl' => route('esign.documents.show', $this->document->id),
            ],
        );
    }

    public function build(): static
    {
        $this->document->loadMissing(['files', 'recipients']);

        foreach ($this->document->files as $file) {
            $flattenedPaths = $file->flattened_pdf_paths ?? [];

            // If we have pre-flattened PDFs, attach them
            if (!empty($flattenedPaths)) {
                foreach ($flattenedPaths as $recipientId => $path) {
                    try {
                        $content = Storage::disk('google')->get($path);
                        if ($content) {
                            // Find recipient name for filename
                            $recipient = $this->document->recipients->firstWhere('id', $recipientId);
                            $recipientName = $recipient?->name ?? $recipient?->email ?? 'unknown';
                            $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $recipientName);
                            
                            $this->attachData(
                                $content,
                                pathinfo($file->original_name, PATHINFO_FILENAME)."_signed_by_{$safeName}.pdf",
                                ['mime' => 'application/pdf'],
                            );
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            } else {
                // Fallback: attach original PDF
                try {
                    $content = Storage::disk('google')->get($file->getPdfPathForViewing());
                    if ($content) {
                        $this->attachData(
                            $content,
                            pathinfo($file->original_name, PATHINFO_FILENAME).'_signed.pdf',
                            ['mime' => 'application/pdf'],
                        );
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return $this;
    }
}
