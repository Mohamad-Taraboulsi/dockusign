<?php

namespace App\Http\Controllers\ElectronicSignature;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FlattenedPdfController extends Controller
{
    public function store(Request $request, Document $document, DocumentFile $file): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'recipient_id' => 'required|string',
            'pdf' => 'required|string', // base64 encoded
        ]);

        abort_unless($file->document_id === $document->id, 404);

        $recipientId = $request->input('recipient_id');
        
        return $this->saveFlattenedPdf($file, $recipientId, $request->input('pdf'));
    }

    public function storeForRecipient(Request $request, DocumentRecipient $recipient): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file_id' => 'required|integer',
            'pdf' => 'required|string', // base64 encoded
        ]);

        $fileId = $request->input('file_id');
        $file = DocumentFile::where('id', $fileId)
            ->where('document_id', $recipient->document_id)
            ->firstOrFail();

        return $this->saveFlattenedPdf($file, $recipient->id, $request->input('pdf'));
    }

    private function saveFlattenedPdf(DocumentFile $file, string $recipientId, string $pdfBase64): \Illuminate\Http\JsonResponse
    {
        $pdfData = base64_decode($pdfBase64);

        abort_if(!$pdfData, 400, 'Invalid PDF data');

        $document = $file->document;
        $path = "documents/{$document->id}/signed/{$recipientId}/{$file->id}.pdf";
        
        Storage::disk('google')->put($path, $pdfData);

        $flattenedPaths = $file->flattened_pdf_paths ?? [];
        $flattenedPaths[$recipientId] = $path;
        $file->update(['flattened_pdf_paths' => $flattenedPaths]);

        return response()->json([
            'success' => true,
            'path' => $path,
        ]);
    }
}
