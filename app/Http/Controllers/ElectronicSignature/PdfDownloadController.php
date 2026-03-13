<?php

namespace App\Http\Controllers\ElectronicSignature;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PdfDownloadController extends Controller
{
    public function getForFlattening(Request $request, Document $document, DocumentFile $file): JsonResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        $pdfContent = Storage::disk('google')->get($file->getPdfPathForViewing());

        abort_if(!$pdfContent, 404, 'PDF file not found');

        $fields = $document->fields()
            ->where('document_file_id', $file->id)
            ->with('value')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'type' => $f->type,
                'page_number' => $f->page_number,
                'position_x' => $f->position_x,
                'position_y' => $f->position_y,
                'width' => $f->width,
                'height' => $f->height,
                'value' => $f->value?->value,
            ]);

        return response()->json([
            'pdf_base64' => base64_encode($pdfContent),
            'fields' => $fields,
            'page_count' => $file->page_count,
        ]);
    }

    public function getSignedForFlattening(Request $request, Document $document, DocumentRecipient $recipient): JsonResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        abort_unless($document->id === $recipient->document_id, 404);

        $files = $document->files()->orderBy('sort_order')->get();

        $filesData = [];

        foreach ($files as $file) {
            $pdfContent = Storage::disk('google')->get($file->getPdfPathForViewing());

            if (!$pdfContent) {
                continue;
            }

            $fields = $document->fields()
                ->where('document_file_id', $file->id)
                ->where('recipient_id', $recipient->id)
                ->with('value')
                ->get()
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'type' => $f->type,
                    'page_number' => $f->page_number,
                    'position_x' => $f->position_x,
                    'position_y' => $f->position_y,
                    'width' => $f->width,
                    'height' => $f->height,
                    'value' => $f->value?->value,
                ]);

            $filesData[] = [
                'file_id' => $file->id,
                'original_name' => $file->original_name,
                'pdf_base64' => base64_encode($pdfContent),
                'fields' => $fields,
                'page_count' => $file->page_count,
            ];
        }

        return response()->json([
            'files' => $filesData,
            'document_title' => $document->title,
            'recipient_name' => $recipient->name,
        ]);
    }

    private function authorizeDocumentAccess(Request $request, Document $document): void
    {
        abort_unless((int) $document->user_id === (int) $request->user()->id, 403);
    }
}
