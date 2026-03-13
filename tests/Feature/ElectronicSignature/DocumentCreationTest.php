<?php

use App\Actions\ElectronicSignature\ConvertToPdf;
use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('google');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// Helper: minimal valid PDF content with a given number of pages
function fakePdfContent(int $pages = 1): string
{
    // Build a minimal valid PDF structure
    $content = "%PDF-1.4\n";
    for ($i = 0; $i < $pages; $i++) {
        $content .= ($i + 1)." 0 obj\n<< /Type /Page >>\nendobj\n";
    }
    $content .= "trailer\n%%EOF\n";

    return $content;
}

function createPdfUpload(string $name = 'contract.pdf', int $pages = 1): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'test_pdf_');
    file_put_contents($path, fakePdfContent($pages));

    return new UploadedFile($path, $name, 'application/pdf', null, true);
}

// ─── Page rendering ──────────────────────────────────────────────

test('guests cannot access document pages', function () {
    auth()->logout();

    $this->get(route('esign.documents.index'))->assertRedirect(route('login'));
    $this->get(route('esign.documents.create'))->assertRedirect(route('login'));
});

test('unverified users cannot access document pages', function () {
    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified);

    $this->get(route('esign.documents.index'))->assertRedirect();
});

test('create page renders for authenticated user', function () {
    $this->get(route('esign.documents.create'))->assertOk();
});

test('index page renders for authenticated user', function () {
    $this->get(route('esign.documents.index'))->assertOk();
});

// ─── Validation ──────────────────────────────────────────────────

test('store requires title, files and recipients', function () {
    $this->post(route('esign.documents.store'), [])
        ->assertSessionHasErrors(['title', 'files', 'recipients', 'signing_order']);
});

test('store rejects invalid file types', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Test',
        'signing_order' => 'parallel',
        'files' => [UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload')],
        'recipients' => [['email' => 'bob@example.com', 'name' => 'Bob', 'role' => 'signer']],
    ])->assertSessionHasErrors('files.0');
});

test('store rejects invalid recipient role', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Test',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [['email' => 'bob@example.com', 'name' => 'Bob', 'role' => 'viewer']],
    ])->assertSessionHasErrors('recipients.0.role');
});

test('store rejects invalid signing order', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Test',
        'signing_order' => 'random',
        'files' => [createPdfUpload()],
        'recipients' => [['email' => 'bob@example.com', 'name' => 'Bob', 'role' => 'signer']],
    ])->assertSessionHasErrors('signing_order');
});

// ─── Document creation (PDF upload → Google Drive) ───────────────

test('store creates document with PDF file on google drive and redirects to editor', function () {
    $response = $this->post(route('esign.documents.store'), [
        'title' => 'Employment Contract',
        'subject' => 'Please sign this contract',
        'message' => 'Hi, please review and sign.',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload('contract.pdf', 2)],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Jane Signer', 'role' => 'signer'],
        ],
    ]);

    // Should redirect to editor
    $document = Document::first();
    expect($document)->not->toBeNull();
    $response->assertRedirect(route('esign.documents.editor', $document));

    // Document created correctly
    expect($document->title)->toBe('Employment Contract')
        ->and($document->subject)->toBe('Please sign this contract')
        ->and($document->message)->toBe('Hi, please review and sign.')
        ->and($document->signing_order)->toBe('parallel')
        ->and($document->status)->toBe('draft')
        ->and($document->user_id)->toBe($this->user->id);
});

test('store uploads file to google drive storage', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Test Upload',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload('test.pdf', 1)],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    $document = Document::first();
    $file = DocumentFile::first();

    expect($file)->not->toBeNull()
        ->and($file->original_name)->toBe('test.pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->size_bytes)->toBeGreaterThan(0)
        ->and($file->sort_order)->toBe(0)
        ->and($file->pdf_path)->toBe($file->stored_path); // PDF uploaded as-is

    // File exists on the fake google drive
    Storage::disk('google')->assertExists($file->stored_path);
});

test('store counts PDF pages correctly', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Multi Page Doc',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload('multi.pdf', 3)],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    $file = DocumentFile::first();
    expect($file->page_count)->toBe(3);
});

test('store creates recipients correctly', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Test Recipients',
        'signing_order' => 'sequential',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'first@example.com', 'name' => 'First Signer', 'role' => 'signer'],
            ['email' => 'second@example.com', 'name' => 'Second Signer', 'role' => 'signer'],
            ['email' => 'cc@example.com', 'name' => 'CC Person', 'role' => 'cc'],
        ],
    ]);

    $document = Document::first();
    $recipients = DocumentRecipient::orderBy('sort_order')->get();

    expect($recipients)->toHaveCount(3)
        ->and($recipients[0]->email)->toBe('first@example.com')
        ->and($recipients[0]->name)->toBe('First Signer')
        ->and($recipients[0]->role)->toBe('signer')
        ->and($recipients[0]->sort_order)->toBe(0)
        ->and($recipients[0]->status)->toBe('pending')
        ->and($recipients[1]->email)->toBe('second@example.com')
        ->and($recipients[1]->sort_order)->toBe(1)
        ->and($recipients[2]->role)->toBe('cc')
        ->and($recipients[2]->sort_order)->toBe(2);
});

test('store resolves user_id for existing users as recipients', function () {
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    $this->post(route('esign.documents.store'), [
        'title' => 'Test Resolve',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'existing@example.com', 'name' => 'Existing', 'role' => 'signer'],
            ['email' => 'unknown@example.com', 'name' => 'Unknown', 'role' => 'signer'],
        ],
    ]);

    $recipients = DocumentRecipient::orderBy('sort_order')->get();
    expect($recipients[0]->user_id)->toBe($existingUser->id)
        ->and($recipients[1]->user_id)->toBeNull();
});

test('store handles multiple file uploads', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Multi File',
        'signing_order' => 'parallel',
        'files' => [
            createPdfUpload('first.pdf', 2),
            createPdfUpload('second.pdf', 1),
        ],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    $files = DocumentFile::orderBy('sort_order')->get();
    expect($files)->toHaveCount(2)
        ->and($files[0]->original_name)->toBe('first.pdf')
        ->and($files[0]->sort_order)->toBe(0)
        ->and($files[1]->original_name)->toBe('second.pdf')
        ->and($files[1]->sort_order)->toBe(1);

    Storage::disk('google')->assertExists($files[0]->stored_path);
    Storage::disk('google')->assertExists($files[1]->stored_path);
});

test('store logs document created activity', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Activity Test',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    $activity = DocumentActivity::first();
    expect($activity)->not->toBeNull()
        ->and($activity->type)->toBe('created')
        ->and($activity->user_id)->toBe($this->user->id)
        ->and($activity->description)->toBe('Document created');
});

// ─── PDF retrieval from Google Drive ─────────────────────────────

test('servePdf returns PDF content from google drive', function () {
    // Create document with file
    $this->post(route('esign.documents.store'), [
        'title' => 'PDF Serve Test',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload('serve.pdf', 1)],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    $document = Document::first();
    $file = DocumentFile::first();

    // Retrieve PDF via the serve route
    $response = $this->get(route('esign.documents.pdf', [$document, $file]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    // Content should start with %PDF
    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

test('servePdf denies access to non-owner non-recipient', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Access Test',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    $document = Document::first();
    $file = DocumentFile::first();

    // Login as a different user who is NOT a recipient
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);

    $this->get(route('esign.documents.pdf', [$document, $file]))
        ->assertForbidden();
});

test('servePdf allows access to recipients', function () {
    $recipientUser = User::factory()->create(['email' => 'recipient@example.com']);

    $this->post(route('esign.documents.store'), [
        'title' => 'Recipient Access Test',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'recipient@example.com', 'name' => 'Recipient', 'role' => 'signer'],
        ],
    ]);

    $document = Document::first();
    $file = DocumentFile::first();

    // Login as the recipient
    $this->actingAs($recipientUser);

    $this->get(route('esign.documents.pdf', [$document, $file]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

// ─── Editor page ─────────────────────────────────────────────────

test('editor page renders for draft document owner', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Editor Test',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    $document = Document::first();

    $this->get(route('esign.documents.editor', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Products/ElectronicSignature/Editor')
            ->has('document')
            ->where('document.id', $document->id)
            ->where('document.title', 'Editor Test')
            ->where('document.status', 'draft')
            ->has('document.files')
            ->has('document.recipients')
        );
});

test('editor page loads document files and recipients', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Full Data Test',
        'signing_order' => 'sequential',
        'files' => [createPdfUpload('doc1.pdf', 2)],
        'recipients' => [
            ['email' => 'alice@example.com', 'name' => 'Alice', 'role' => 'signer'],
            ['email' => 'bob@example.com', 'name' => 'Bob', 'role' => 'signer'],
        ],
    ]);

    $document = Document::first();

    $this->get(route('esign.documents.editor', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Products/ElectronicSignature/Editor')
            ->has('document.files', 1)
            ->has('document.recipients', 2)
            ->where('document.signing_order', 'sequential')
            ->where('document.files.0.original_name', 'doc1.pdf')
            ->where('document.recipients.0.email', 'alice@example.com')
            ->where('document.recipients.1.email', 'bob@example.com')
        );
});

test('editor denies access to non-owner', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Not Yours',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    $document = Document::first();
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);

    $this->get(route('esign.documents.editor', $document))
        ->assertForbidden();
});

test('editor denies access for non-draft documents', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Sent Doc',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    $document = Document::first();
    $document->update(['status' => 'sent']);

    $this->get(route('esign.documents.editor', $document))
        ->assertForbidden();
});

// ─── Document index/list ─────────────────────────────────────────

test('index shows documents in correct tabs', function () {
    // Create a draft
    $this->post(route('esign.documents.store'), [
        'title' => 'My Draft',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    // Create a sent document
    $this->post(route('esign.documents.store'), [
        'title' => 'My Sent Doc',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);
    $sentDoc = Document::where('title', 'My Sent Doc')->first();
    $sentDoc->update(['status' => 'sent']);

    // Drafts tab
    $this->get(route('esign.documents.index', ['tab' => 'drafts']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'drafts')
            ->has('documents.data', 1)
            ->where('documents.data.0.title', 'My Draft')
        );

    // Sent tab (default)
    $this->get(route('esign.documents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'sent')
            ->has('documents.data', 1)
            ->where('documents.data.0.title', 'My Sent Doc')
        );
});

// ─── Document deletion ──────────────────────────────────────────

test('owner can delete draft document', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Delete Me',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    $document = Document::first();

    $this->delete(route('esign.documents.destroy', $document))
        ->assertRedirect(route('esign.documents.index'));

    expect(Document::withTrashed()->find($document->id)->trashed())->toBeTrue();
});

test('cannot delete non-draft document', function () {
    $this->post(route('esign.documents.store'), [
        'title' => 'Cannot Delete',
        'signing_order' => 'parallel',
        'files' => [createPdfUpload()],
        'recipients' => [
            ['email' => 'signer@example.com', 'name' => 'Signer', 'role' => 'signer'],
        ],
    ]);

    $document = Document::first();
    $document->update(['status' => 'sent']);

    $this->delete(route('esign.documents.destroy', $document))
        ->assertForbidden();
});

// ─── End-to-end: create → upload → editor ────────────────────────

test('full flow: create document with PDF, store on google drive, open editor, serve PDF', function () {
    // Step 1: Submit the create form
    $response = $this->post(route('esign.documents.store'), [
        'title' => 'NDA Agreement',
        'subject' => 'Sign our NDA',
        'message' => 'Please review and sign the attached NDA.',
        'signing_order' => 'sequential',
        'files' => [createPdfUpload('nda.pdf', 3)],
        'recipients' => [
            ['email' => 'alice@example.com', 'name' => 'Alice', 'role' => 'signer'],
            ['email' => 'bob@example.com', 'name' => 'Bob', 'role' => 'cc'],
        ],
    ]);

    $document = Document::first();

    // Step 2: Verify redirect to editor
    $response->assertRedirect(route('esign.documents.editor', $document));

    // Step 3: Verify database state
    expect($document->title)->toBe('NDA Agreement')
        ->and($document->signing_order)->toBe('sequential')
        ->and($document->status)->toBe('draft');

    $file = $document->files()->first();
    expect($file->original_name)->toBe('nda.pdf')
        ->and($file->page_count)->toBe(3)
        ->and($file->mime_type)->toBe('application/pdf');

    $recipients = $document->recipients()->orderBy('sort_order')->get();
    expect($recipients)->toHaveCount(2)
        ->and($recipients[0]->email)->toBe('alice@example.com')
        ->and($recipients[0]->role)->toBe('signer')
        ->and($recipients[1]->email)->toBe('bob@example.com')
        ->and($recipients[1]->role)->toBe('cc');

    // Step 4: Verify file on Google Drive
    Storage::disk('google')->assertExists($file->stored_path);

    // Step 5: Open editor — verify it loads with all data
    $this->get(route('esign.documents.editor', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Products/ElectronicSignature/Editor')
            ->where('document.id', $document->id)
            ->where('document.title', 'NDA Agreement')
            ->where('document.status', 'draft')
            ->where('document.signing_order', 'sequential')
            ->has('document.files', 1)
            ->where('document.files.0.original_name', 'nda.pdf')
            ->where('document.files.0.page_count', 3)
            ->has('document.recipients', 2)
        );

    // Step 6: Serve PDF — verify retrievable from Google Drive
    $pdfResponse = $this->get(route('esign.documents.pdf', [$document, $file]));
    $pdfResponse->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $content = $pdfResponse->getContent();
    expect(str_starts_with($content, '%PDF'))->toBeTrue();

    // Step 7: Verify activity trail
    $activities = $document->activities()->get();
    expect($activities)->toHaveCount(1)
        ->and($activities[0]->type)->toBe('created')
        ->and($activities[0]->user_id)->toBe($this->user->id);
});
