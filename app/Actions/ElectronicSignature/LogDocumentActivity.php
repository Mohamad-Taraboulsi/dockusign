<?php

namespace App\Actions\ElectronicSignature;

use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\DocumentRecipient;
use App\Models\User;

class LogDocumentActivity
{
    public function execute(
        Document $document,
        string $type,
        ?DocumentRecipient $recipient = null,
        ?User $user = null,
        ?string $description = null,
        ?array $metadata = null,
    ): DocumentActivity {
        return $document->activities()->create([
            'type' => $type,
            'recipient_id' => $recipient?->id,
            'user_id' => $user?->id,
            'description' => $description ?? $this->defaultDescription($type, $recipient),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function defaultDescription(string $type, ?DocumentRecipient $recipient): string
    {
        $name = $recipient?->name ?? $recipient?->email ?? 'System';

        return match ($type) {
            'created' => 'Document created',
            'sent' => 'Document sent for signing',
            'opened' => "{$name} opened the document",
            'viewed' => "{$name} viewed the document",
            'signed' => "{$name} signed the document",
            'completed' => 'All recipients have signed — document completed',
            'declined' => "{$name} declined to sign",
            'voided' => 'Document voided by sender',
            'resent' => "Document resent to {$name}",
            default => $type,
        };
    }
}
