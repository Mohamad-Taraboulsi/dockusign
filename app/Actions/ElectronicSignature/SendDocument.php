<?php

namespace App\Actions\ElectronicSignature;

use App\Models\Document;
use App\Models\User;
use App\Mail\DocumentSigningMailable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SendDocument
{
    public function __construct(private LogDocumentActivity $logActivity) {}

    public function execute(Document $document, User $sender): void
    {
        $signers = $document->signers()->get();

        if ($signers->isEmpty()) {
            throw new \InvalidArgumentException('At least one signer is required.');
        }

        // Validate that all signers have at least one field assigned
        foreach ($signers as $signer) {
            $fieldCount = $document->fields()->where('recipient_id', $signer->id)->count();
            if ($fieldCount === 0) {
                throw new \InvalidArgumentException("Signer {$signer->email} has no fields assigned.");
            }
        }

        $document->update(['status' => 'sent']);

        if ($document->signing_order === 'sequential') {
            // Only send to first signer
            $firstSigner = $signers->sortBy('sort_order')->first();
            $this->sendToRecipient($firstSigner);
        } else {
            // Send to all signers
            foreach ($signers as $signer) {
                $this->sendToRecipient($signer);
            }
        }

        // Send CC recipients a notification
        $ccRecipients = $document->recipients()->where('role', 'cc')->get();
        foreach ($ccRecipients as $cc) {
            $cc->update(['status' => 'sent', 'sent_at' => now()]);
        }

        $this->logActivity->execute($document, 'sent', null, $sender);
    }

    public function sendToRecipientDirect($recipient): void
    {
        $this->sendToRecipient($recipient);
    }

    private function sendToRecipient($recipient): void
    {
        $accessCode = Str::random(6);
        $recipient->update([
            'access_code' => Hash::make($accessCode),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Resolve user_id if user exists
        if (! $recipient->user_id) {
            $user = User::where('email', $recipient->email)->first();
            if ($user) {
                $recipient->update(['user_id' => $user->id]);
            }
        }

        // Load document with user relationship for the email
        $recipient->load('document.user');

        // Send email using Laravel Mail
        \Illuminate\Support\Facades\Mail::to($recipient->email)->send(
            new DocumentSigningMailable($recipient->document, $recipient, $accessCode)
        );
    }
}
