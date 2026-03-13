<?php

namespace App\Http\Controllers\ElectronicSignature;

use App\Actions\ElectronicSignature\CreateDocument;
use App\Actions\ElectronicSignature\LogDocumentActivity;
use App\Actions\ElectronicSignature\SendDocument;
use App\Actions\ElectronicSignature\VoidDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\ElectronicSignature\StoreDocumentRequest;
use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tab = $request->get('tab', 'sent');

        $query = match ($tab) {
            'drafts' => $user->documents()->where('status', 'draft'),
            'received' => Document::whereHas('recipients', fn ($q) => $q->where('email', $user->email))
                ->with(['recipients' => fn ($q) => $q->where('email', $user->email)]),
            default => $user->documents()->where('status', '!=', 'draft'),
        };

        $documents = $query->with(['recipients', 'files'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Products/ElectronicSignature/Index', [
            'documents' => $documents,
            'tab' => $tab,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Products/ElectronicSignature/Create');
    }

    public function store(StoreDocumentRequest $request, CreateDocument $action): RedirectResponse
    {
        $document = $action->execute($request->user(), $request->validated());

        return redirect()->route('esign.documents.editor', $document);
    }

    public function show(Request $request, Document $document): Response
    {
        $this->authorizeDocumentAccess($request, $document);

        $document->load(['files', 'recipients.user', 'activities.user', 'activities.recipient']);

        return Inertia::render('Products/ElectronicSignature/Show', [
            'document' => $document,
        ]);
    }

    public function editor(Request $request, Document $document): Response|RedirectResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        if (! $document->isDraft()) {
            return redirect()->route('esign.documents.show', $document)->with('error', 'Only draft documents can be edited.');
        }

        $document->load(['files', 'recipients', 'fields.recipient']);

        return Inertia::render('Products/ElectronicSignature/Editor', [
            'document' => $document,
        ]);
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        if (! $document->isDraft()) {
            return redirect()->route('esign.documents.show', $document)->with('error', 'Only draft documents can be deleted.');
        }

        $document->delete();

        return redirect()->route('esign.documents.index')->with('success', 'Document deleted.');
    }

    public function send(Request $request, Document $document, SendDocument $action): RedirectResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        if (! $document->isDraft()) {
            return redirect()->route('esign.documents.show', $document)->with('error', 'Only draft documents can be sent.');
        }

        try {
            $action->execute($document, $request->user());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('esign.documents.editor', $document)->with('error', $e->getMessage());
        }

        return redirect()->route('esign.documents.show', $document)->with('success', 'Document sent successfully.');
    }

    public function void(Request $request, Document $document, VoidDocument $action): RedirectResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        try {
            $action->execute($document, $request->user());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('esign.documents.show', $document)->with('error', $e->getMessage());
        }

        return redirect()->route('esign.documents.show', $document)->with('success', 'Document voided.');
    }

    public function servePdf(Request $request, Document $document, DocumentFile $file): HttpResponse
    {
        $isOwner = $document->user_id === $request->user()->id;
        $isRecipient = $document->recipients()->where('email', $request->user()->email)->exists();
        abort_unless($isOwner || $isRecipient, 403);
        abort_unless($file->document_id === $document->id, 404);

        $path = $file->getPdfPathForViewing();
        $content = Storage::disk('google')->get($path);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    public function downloadPdf(Request $request, Document $document, DocumentFile $file): HttpResponse
    {
        $isOwner = $document->user_id === $request->user()->id;
        $isRecipient = $document->recipients()->where('email', $request->user()->email)->exists();
        abort_unless($isOwner || $isRecipient, 403);
        abort_unless($file->document_id === $document->id, 404);

        $filename = pathinfo($file->original_name, PATHINFO_FILENAME).'.pdf';
        $content = Storage::disk('google')->get($file->getPdfPathForViewing());

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function downloadSignedCopy(Request $request, Document $document, \App\Models\DocumentRecipient $recipient): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        if ($recipient->document_id !== $document->id) {
            return redirect()->route('esign.documents.show', $document)->with('error', 'Recipient not found for this document.');
        }

        if (! $recipient->hasSigned()) {
            return redirect()->route('esign.documents.show', $document)->with('warning', 'This recipient has not signed yet.');
        }

        $files = $document->files()->orderBy('sort_order')->get();

        if ($files->count() === 1) {
            $file = $files->first();
            $filename = pathinfo($file->original_name, PATHINFO_FILENAME).'_signed_by_'.str_replace(' ', '_', $recipient->name ?: $recipient->email).'.pdf';
            $content = Storage::disk('google')->get($file->getPdfPathForViewing());

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'signedzip_');
        $zip = new \ZipArchive;
        $zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($files as $file) {
            $content = Storage::disk('google')->get($file->getPdfPathForViewing());
            $filename = pathinfo($file->original_name, PATHINFO_FILENAME).'_signed.pdf';
            $zip->addFromString($filename, $content);
        }

        $zip->close();
        $zipContent = file_get_contents($tempFile);
        @unlink($tempFile);

        $zipName = str_replace(' ', '_', $document->title).'_signed_by_'.str_replace(' ', '_', $recipient->name ?: $recipient->email).'.zip';

        return response($zipContent, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$zipName.'"',
        ]);
    }

    public function signedCopy(Request $request, Document $document, \App\Models\DocumentRecipient $recipient): Response|RedirectResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        if ($recipient->document_id !== $document->id) {
            return redirect()->route('esign.documents.show', $document)->with('error', 'Recipient not found for this document.');
        }

        if (! $recipient->hasSigned()) {
            return redirect()->route('esign.documents.show', $document)->with('warning', 'This recipient has not signed yet.');
        }

        $document->load(['files', 'fields' => function ($q) use ($recipient) {
            $q->where('recipient_id', $recipient->id)->with('value');
        }]);

        return Inertia::render('Products/ElectronicSignature/SignedCopy', [
            'document' => $document,
            'recipient' => $recipient,
        ]);
    }

    public function downloadAll(Request $request, Document $document): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeDocumentAccess($request, $document);

        $files = $document->files()->orderBy('sort_order')->get();

        if ($files->count() === 1) {
            $file = $files->first();
            $filename = pathinfo($file->original_name, PATHINFO_FILENAME).'.pdf';
            $content = Storage::disk('google')->get($file->getPdfPathForViewing());

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'doczip_');
        $zip = new \ZipArchive;
        $zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($files as $file) {
            $content = Storage::disk('google')->get($file->getPdfPathForViewing());
            $filename = pathinfo($file->original_name, PATHINFO_FILENAME).'.pdf';
            $zip->addFromString($filename, $content);
        }

        $zip->close();
        $zipContent = file_get_contents($tempFile);
        @unlink($tempFile);

        $zipName = str_replace(' ', '_', $document->title).'.zip';

        return response($zipContent, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$zipName.'"',
        ]);
    }

    private function authorizeDocumentAccess(Request $request, Document $document): void
    {
        abort_unless((int) $document->user_id === (int) $request->user()->id, 403);
    }
}
