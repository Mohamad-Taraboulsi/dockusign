<?php

namespace App\Http\Controllers\ElectronicSignature;

use App\Actions\ElectronicSignature\LogDocumentActivity;
use App\Actions\ElectronicSignature\ProcessSigning;
use App\Http\Controllers\Controller;
use App\Http\Requests\ElectronicSignature\SubmitSigningRequest;
use App\Mail\DocumentDeclinedMailable;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SigningController extends Controller
{
    public function show(Request $request, DocumentRecipient $recipient): Response|RedirectResponse
    {
        $document = $recipient->document;

        if ($document->isVoided()) {
            return redirect()->route('home')->with('error', 'This document has been voided.');
        }

        if ($recipient->hasSigned()) {
            return redirect()->route('home')->with('info', 'You have already signed this document.');
        }

        if ($recipient->hasDeclined()) {
            return redirect()->route('home')->with('info', 'You have already declined this document.');
        }

        // Log opened activity
        if (! $recipient->opened_at) {
            $recipient->update(['opened_at' => now(), 'status' => 'opened']);
            app(LogDocumentActivity::class)->execute($document, 'opened', $recipient, $request->user());
        }

        // Show verify page if:
        // 1. Access code is set, AND
        // 2. User is NOT logged in OR user's email doesn't match recipient's email
        // 3. Access code hasn't been verified in session yet
        $needsVerification = $recipient->access_code && 
            (!$request->user() || $request->user()->email !== $recipient->email) &&
            !$request->session()->get("signing_verified_{$recipient->id}");
            
        if ($needsVerification) {
            return Inertia::render('Products/ElectronicSignature/Sign/Verify', [
                'recipient' => $recipient->only('id', 'email', 'name'),
                'documentTitle' => $document->title,
            ]);
        }

        // If logged in user has matching email, they're authorized - skip verify
        // Or if they've already verified via access code in this session
        $document->load(['files', 'fields.value']);

        // Filter fields to only show this recipient's fields
        $recipientFields = $document->fields->filter(function ($field) use ($recipient) {
            return $field->recipient_id === $recipient->id;
        })->values();

        // Replace the fields relation with filtered results
        $document->setRelation('fields', $recipientFields);

        return Inertia::render('Products/ElectronicSignature/Sign/Sign', [
            'document' => $document,
            'recipient' => $recipient,
        ]);
    }

    public function verify(Request $request, DocumentRecipient $recipient): RedirectResponse
    {
        $request->validate(['access_code' => 'required|string']);

        if (! Hash::check($request->access_code, $recipient->access_code)) {
            return back()->withErrors(['access_code' => 'Invalid access code.']);
        }

        $request->session()->put("signing_verified_{$recipient->id}", true);

        return redirect()->route('sign.show', $recipient);
    }

    public function submit(Request $request, DocumentRecipient $recipient, ProcessSigning $action): Response
    {
        $document = $recipient->document;

        if ($document->isVoided()) {
            return Inertia::render('Products/ElectronicSignature/Sign/Complete', [
                'documentTitle' => $document->title,
                'flash' => ['error' => 'This document has been voided.'],
            ]);
        }

        if ($recipient->hasSigned()) {
            return Inertia::render('Products/ElectronicSignature/Sign/Complete', [
                'documentTitle' => $document->title,
                'flash' => ['info' => 'You have already signed this document.'],
            ]);
        }

        $action->execute($recipient, $request->all(), $request);

        return Inertia::render('Products/ElectronicSignature/Sign/Complete', [
            'documentTitle' => $document->title,
        ]);
    }

    public function decline(Request $request, DocumentRecipient $recipient): Response|RedirectResponse
    {
        $request->validate(['reason' => 'nullable|string|max:1000']);

        $document = $recipient->document;

        if ($document->isVoided()) {
            return redirect()->route('home')->with('error', 'This document has been voided.');
        }

        if ($recipient->hasSigned()) {
            return redirect()->route('home')->with('info', 'You have already signed this document.');
        }

        $recipient->update([
            'status' => 'declined',
            'declined_at' => now(),
            'decline_reason' => $request->reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $document->update(['status' => 'declined']);

        app(LogDocumentActivity::class)->execute($document, 'declined', $recipient, $request->user());

        // Notify sender via email
        $document->load('user');
        \Illuminate\Support\Facades\Mail::to($document->user->email)->send(
            new DocumentDeclinedMailable($document, $recipient)
        );

        return Inertia::render('Products/ElectronicSignature/Sign/Declined', [
            'documentTitle' => $document->title,
        ]);
    }

    public function servePdfForSigning(Request $request, DocumentRecipient $recipient, DocumentFile $file): \Symfony\Component\HttpFoundation\Response
    {
        $document = $recipient->document;

        abort_unless($file->document_id === $document->id, 404);

        $path = $file->getPdfPathForViewing();
        $content = Storage::disk('google')->get($path);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }
}
