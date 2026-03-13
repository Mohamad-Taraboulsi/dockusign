<?php

namespace App\Actions\ElectronicSignature;

use App\Models\DocumentRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessSigning
{
    public function __construct(
        private CompleteDocument $completeDocument,
        private LogDocumentActivity $logActivity,
    ) {}

    public function execute(DocumentRecipient $recipient, array $data, Request $request): void
    {
        DB::transaction(function () use ($recipient, $data, $request) {
            $document = $recipient->document;
            $fields = $document->fields()->where('recipient_id', $recipient->id)->get();

            // Store field values
            foreach ($fields as $field) {
                $fieldKey = "fields.{$field->id}";
                $value = data_get($data, $fieldKey);
                $filePath = null;

                // Handle file uploads (signatures, stamps, attachments)
                if ($request->hasFile("files.{$field->id}")) {
                    $uploadedFile = $request->file("files.{$field->id}");
                    $filePath = $uploadedFile->store(
                        "documents/{$document->id}/signatures",
                        'google'
                    );
                    $value = $value ?? $uploadedFile->getClientOriginalName();
                }

                if ($value !== null || $filePath !== null) {
                    $field->value()->updateOrCreate(
                        ['recipient_id' => $recipient->id],
                        [
                            'value' => is_array($value) ? json_encode($value) : $value,
                            'file_path' => $filePath,
                            'filled_at' => now(),
                        ]
                    );
                }
            }

            // Update recipient status
            $recipient->update([
                'status' => 'signed',
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $this->logActivity->execute($document, 'signed', $recipient, $request->user());

            // Check if all signers have signed
            $allSigned = $document->signers()
                ->where('status', '!=', 'signed')
                ->doesntExist();

            if ($allSigned) {
                $this->completeDocument->execute($document);
            } elseif ($document->signing_order === 'sequential') {
                // Send to next recipient
                $nextRecipient = $document->signers()
                    ->where('sort_order', '>', $recipient->sort_order)
                    ->where('status', 'pending')
                    ->orderBy('sort_order')
                    ->first();

                if ($nextRecipient) {
                    app(SendDocument::class)->sendToRecipientDirect($nextRecipient);
                    $document->update(['status' => 'in_progress']);
                }
            } else {
                $document->update(['status' => 'in_progress']);
            }
        });
    }
}
