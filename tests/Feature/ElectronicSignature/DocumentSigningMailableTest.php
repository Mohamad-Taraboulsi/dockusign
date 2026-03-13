<?php

use App\Mail\DocumentSigningMailable;
use App\Models\Document;
use App\Models\DocumentField;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->signer = User::factory()->create(['email' => 'signer@test.com', 'name' => 'Test Signer']);
    $this->actingAs($this->user);

    // Create document
    $this->document = Document::create([
        'user_id' => $this->user->id,
        'title' => 'Test Contract',
        'subject' => 'Please sign this document',
        'message' => 'This is a test message',
        'signing_order' => 'parallel',
        'status' => 'draft',
    ]);

    // Create file
    $this->file = DocumentFile::create([
        'document_id' => $this->document->id,
        'original_name' => 'test.pdf',
        'stored_path' => 'test.pdf',
        'pdf_path' => 'test.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'page_count' => 1,
        'sort_order' => 0,
    ]);

    // Create recipient
    $this->recipient = DocumentRecipient::create([
        'document_id' => $this->document->id,
        'user_id' => $this->signer->id,
        'email' => 'signer@test.com',
        'name' => 'Test Signer',
        'role' => 'signer',
        'sort_order' => 0,
    ]);

    // Create field for recipient
    DocumentField::create([
        'document_id' => $this->document->id,
        'document_file_id' => $this->file->id,
        'recipient_id' => $this->recipient->id,
        'type' => 'signature',
        'page_number' => 1,
        'position_x' => 10,
        'position_y' => 10,
        'width' => 20,
        'height' => 5,
        'is_required' => true,
    ]);
});

test('document signing mailable is sent to MAIL_TEST_TO', function () {
    $testEmail = env('MAIL_TEST_TO', 'test@example.com');

    Mail::fake();

    $accessCode = 'ABC123';
    $this->document->load('user');

    Mail::to($testEmail)->send(new DocumentSigningMailable(
        $this->document,
        $this->recipient,
        $accessCode
    ));

    Mail::assertSent(DocumentSigningMailable::class, function ($mail) use ($testEmail, $accessCode) {
        return $mail->hasTo($testEmail);
    });
});

test('mailable has correct subject', function () {
    $testEmail = env('MAIL_TEST_TO', 'test@example.com');

    $this->document->load('user');

    $mailable = new DocumentSigningMailable(
        $this->document,
        $this->recipient,
        'ABC123'
    );

    expect($mailable->envelope()->subject)->toBe('Please sign this document');
});

test('mailable contains correct data', function () {
    $testEmail = env('MAIL_TEST_TO', 'test@example.com');

    $this->document->load('user');

    $mailable = new DocumentSigningMailable(
        $this->document,
        $this->recipient,
        'TEST12'
    );

    $envelope = $mailable->envelope();
    expect($envelope->subject)->toBe('Please sign this document');
});

test('mailable sends actual email to MAIL_TEST_TO', function () {
    $testEmail = env('MAIL_TEST_TO');

    if (!$testEmail) {
        $this->markTestSkipped('MAIL_TEST_TO environment variable not set');
    }

    // Temporarily switch to SMTP for this test
    config(['mail.mailer' => 'smtp']);

    $this->document->load('user');

    // Send real email - no Mail::fake()
    Mail::to($testEmail)->send(new DocumentSigningMailable(
        $this->document,
        $this->recipient,
        'REAL01'
    ));

    // Test passes if no exception is thrown
    expect(true)->toBeTrue();
});
