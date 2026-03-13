<?php

namespace App\Http\Controllers\ElectronicSignature;

use App\Http\Controllers\Controller;
use App\Http\Requests\ElectronicSignature\StoreDocumentFieldRequest;
use App\Http\Requests\ElectronicSignature\UpdateDocumentFieldRequest;
use App\Models\Document;
use App\Models\DocumentField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentFieldController extends Controller
{
    public function store(StoreDocumentFieldRequest $request, Document $document): JsonResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        $field = $document->fields()->create($request->validated());
        $field->load('recipient');

        return response()->json($field, 201);
    }

    public function update(UpdateDocumentFieldRequest $request, Document $document, DocumentField $field): JsonResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        abort_unless($field->document_id === $document->id, 404);

        $field->update($request->validated());
        $field->load('recipient');

        return response()->json($field);
    }

    public function destroy(Request $request, Document $document, DocumentField $field): JsonResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        abort_unless($field->document_id === $document->id, 404);

        $field->delete();

        return response()->json(null, 204);
    }

    public function bulkStore(Request $request, Document $document): JsonResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.document_file_id' => 'required|exists:document_files,id',
            'fields.*.recipient_id' => 'required|exists:document_recipients,id',
            'fields.*.type' => 'required|string|in:signature,initials,stamp,text_name,text_title,text_email,checkbox,dropdown,radio,note,attachment,date_signed',
            'fields.*.label' => 'nullable|string|max:255',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.page_number' => 'required|integer|min:1',
            'fields.*.position_x' => 'required|numeric|min:0|max:100',
            'fields.*.position_y' => 'required|numeric|min:0|max:100',
            'fields.*.width' => 'required|numeric|min:0|max:100',
            'fields.*.height' => 'required|numeric|min:0|max:100',
            'fields.*.is_required' => 'boolean',
            'fields.*.options' => 'nullable|array',
        ]);

        $fields = collect($validated['fields'])->map(function ($fieldData) use ($document) {
            return $document->fields()->create($fieldData);
        });

        $fields->each(fn ($f) => $f->load('recipient'));

        return response()->json($fields->values(), 201);
    }

    private function authorizeDocumentAccess(Request $request, Document $document): void
    {
        if ((int) $document->user_id !== (int) $request->user()->id) {
            abort(response()->json(['message' => 'Unauthorized.'], 403));
        }

        if (! $document->isDraft()) {
            abort(response()->json(['message' => 'Only draft documents can be edited.'], 403));
        }
    }
}
