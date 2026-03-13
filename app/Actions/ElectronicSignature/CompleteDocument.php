<?php

namespace App\Actions\ElectronicSignature;

use App\Models\Document;
use App\Mail\DocumentCompletedMailable;

class CompleteDocument
{
    public function __construct(private LogDocumentActivity $logActivity) {}

    public function execute(Document $document): void
    {
        $document->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->logActivity->execute($document, 'completed');

        // Notify sender
        \Illuminate\Support\Facades\Mail::to($document->user->email)->send(
            new DocumentCompletedMailable($document)
        );

        // Notify all recipients
        foreach ($document->recipients as $recipient) {
            if ($recipient->user) {
                \Illuminate\Support\Facades\Mail::to($recipient->user->email)->send(
                    new DocumentCompletedMailable($document)
                );
            }
        }
    }
}
