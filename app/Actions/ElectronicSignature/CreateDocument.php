<?php

namespace App\Actions\ElectronicSignature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CreateDocument
{
    public function __construct(
        private ConvertToPdf $convertToPdf,
        private LogDocumentActivity $logActivity,
    ) {}

    public function execute(User $user, array $data): Document
    {
        return DB::transaction(function () use ($user, $data) {
            $document = $user->documents()->create([
                'title' => $data['title'],
                'subject' => $data['subject'] ?? null,
                'message' => $data['message'] ?? null,
                'signing_order' => $data['signing_order'] ?? 'parallel',
                'status' => 'draft',
            ]);

            // Store uploaded files
            if (! empty($data['files'])) {
                foreach ($data['files'] as $index => $file) {
                    $storagePath = "documents/{$document->id}/originals";
                    $storedPath = $file->store($storagePath, 'google');

                    $pdfPath = null;
                    $pageCount = 0;

                    if ($file->getMimeType() === 'application/pdf') {
                        $pdfPath = $storedPath;
                        $pdfContent = Storage::disk('google')->get($storedPath);
                        $pageCount = $this->convertToPdf->getPageCountFromContent($pdfContent);
                    } else {
                        // Download from Google Drive to a temp file for conversion
                        $tempInput = tempnam(sys_get_temp_dir(), 'doc_');
                        file_put_contents($tempInput, Storage::disk('google')->get($storedPath));

                        $tempOutputDir = sys_get_temp_dir().'/doc_pdf_'.uniqid();
                        $convertedPath = $this->convertToPdf->execute($tempInput, $tempOutputDir);

                        if ($convertedPath) {
                            $pdfRelativePath = "documents/{$document->id}/pdf/".basename($convertedPath);
                            Storage::disk('google')->put($pdfRelativePath, file_get_contents($convertedPath));
                            $pdfPath = $pdfRelativePath;

                            $pdfContent = file_get_contents($convertedPath);
                            $pageCount = $this->convertToPdf->getPageCountFromContent($pdfContent);

                            // Clean up temp converted file
                            @unlink($convertedPath);
                            @rmdir($tempOutputDir);
                        }

                        // Clean up temp input file
                        @unlink($tempInput);
                    }

                    $document->files()->create([
                        'original_name' => $file->getClientOriginalName(),
                        'stored_path' => $storedPath,
                        'pdf_path' => $pdfPath,
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'page_count' => $pageCount,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Create recipients
            if (! empty($data['recipients'])) {
                foreach ($data['recipients'] as $index => $recipientData) {
                    $existingUser = User::where('email', $recipientData['email'])->first();

                    $document->recipients()->create([
                        'email' => $recipientData['email'],
                        'name' => $recipientData['name'] ?? null,
                        'role' => $recipientData['role'] ?? 'signer',
                        'sort_order' => $index,
                        'user_id' => $existingUser?->id,
                    ]);
                }
            }

            $this->logActivity->execute($document, 'created', null, $user);

            return $document;
        });
    }
}
