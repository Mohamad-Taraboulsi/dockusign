<?php

use App\Http\Controllers\ElectronicSignature\AiAnalysisController;
use App\Http\Controllers\ElectronicSignature\DocumentController;
use App\Http\Controllers\ElectronicSignature\DocumentFieldController;
use App\Http\Controllers\ElectronicSignature\FlattenedPdfController;
use App\Http\Controllers\ElectronicSignature\PdfDownloadController;
use App\Http\Controllers\ElectronicSignature\SigningController;
use Illuminate\Support\Facades\Route;

// Authenticated sender routes
Route::middleware(['auth', 'verified'])->prefix('esign')->name('esign.')->group(function () {
    Route::resource('documents', DocumentController::class)->except(['edit', 'update']);

    Route::post('documents/{document}/send', [DocumentController::class, 'send'])->name('documents.send');
    Route::post('documents/{document}/void', [DocumentController::class, 'void'])->name('documents.void');
    Route::get('documents/{document}/editor', [DocumentController::class, 'editor'])->name('documents.editor');
    Route::get('documents/{document}/pdf/{file}', [DocumentController::class, 'servePdf'])->name('documents.pdf');
    Route::get('documents/{document}/pdf/{file}/flatten-data', [PdfDownloadController::class, 'getForFlattening'])->name('documents.pdf.flatten-data');
    Route::get('documents/{document}/download/{file}', [DocumentController::class, 'downloadPdf'])->name('documents.download');
    Route::get('documents/{document}/signed/{recipient}', [DocumentController::class, 'signedCopy'])->name('documents.signed');
    Route::get('documents/{document}/signed/{recipient}/flatten-data', [PdfDownloadController::class, 'getSignedForFlattening'])->name('documents.signed.flatten-data');
    Route::get('documents/{document}/signed/{recipient}/download', [DocumentController::class, 'downloadSignedCopy'])->name('documents.signed.download');
    Route::get('documents/{document}/download-all', [DocumentController::class, 'downloadAll'])->name('documents.download-all');

    // Fields API (JSON)
    Route::post('documents/{document}/fields', [DocumentFieldController::class, 'store'])->name('documents.fields.store');
    Route::put('documents/{document}/fields/{field}', [DocumentFieldController::class, 'update'])->name('documents.fields.update');
    Route::delete('documents/{document}/fields/{field}', [DocumentFieldController::class, 'destroy'])->name('documents.fields.destroy');
    Route::post('documents/{document}/fields/bulk', [DocumentFieldController::class, 'bulkStore'])->name('documents.fields.bulk');
});

// Signing routes (guests allowed - access code verification)
// Note: web middleware is required for CSRF protection on POST routes
Route::middleware('web')->prefix('sign')->name('sign.')->group(function () {
    Route::get('{recipient:id}', [SigningController::class, 'show'])->name('show');
    Route::post('{recipient:id}/verify', [SigningController::class, 'verify'])->name('verify');
    Route::post('{recipient:id}/submit', [SigningController::class, 'submit'])->name('submit');
    Route::post('{recipient:id}/decline', [SigningController::class, 'decline'])->name('decline');

    // Get PDF for flattening (guest accessible for signing)
    Route::get('{recipient:id}/pdf/{file}', [SigningController::class, 'servePdfForSigning'])->name('pdf');

    // Upload flattened PDF (after signing)
    Route::post('{recipient:id}/upload-flattened', [FlattenedPdfController::class, 'storeForRecipient'])->name('upload-flattened');

    // AI Analysis
    Route::post('{recipient:id}/ai/legal-analysis', [AiAnalysisController::class, 'analyzeLegal'])->name('ai.legal');
    Route::post('{recipient:id}/ai/translate', [AiAnalysisController::class, 'translate'])->name('ai.translate');
});
