<?php

namespace App\Actions\ElectronicSignature;

use App\Models\Document;
use App\Models\User;

class VoidDocument
{
    public function __construct(private LogDocumentActivity $logActivity) {}

    public function execute(Document $document, User $user): void
    {
        if (in_array($document->status, ['completed', 'voided'])) {
            throw new \InvalidArgumentException('Cannot void a completed or already voided document.');
        }

        $document->update([
            'status' => 'voided',
            'voided_at' => now(),
        ]);

        $this->logActivity->execute($document, 'voided', null, $user);
    }
}
