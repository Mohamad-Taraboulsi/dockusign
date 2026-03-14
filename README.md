# Dockusign

An open-source electronic signature platform built with Laravel 12 and Vue 3. Upload documents, place signature fields with a drag-and-drop editor, send to recipients for signing, and track everything in real-time.

## Core Features

### Document Workflow
- **Upload & Convert** — Upload PDF, DOCX, or image files. Non-PDF files are converted server-side.
- **Drag & Drop Editor** — Place signature fields, text inputs, checkboxes, dropdowns, and more directly on document pages using an interactive editor.
- **Multi-Recipient Signing** — Send documents to multiple recipients with configurable signing order (parallel or sequential).
- **Access Code Verification** — Recipients receive an email with a unique link and access code for secure document access.
- **Real-Time Tracking** — Monitor document status, recipient activity, and completion progress from the dashboard.
- **Activity Audit Trail** — Every action (created, sent, opened, signed, completed, declined, voided) is logged with timestamps and metadata.

### Field Types
| Field | Description |
|-------|-------------|
| Signature | Draw signature on a canvas pad |
| Initials | Draw initials |
| Stamp | Draw a custom stamp |
| Full Name | Text input for name |
| Title | Text input for title |
| Email | Text input, auto-filled from recipient |
| Checkbox | Boolean toggle |
| Dropdown | Select from predefined options |
| Radio | Single-select option group |
| Date Signed | Auto-filled with signing date |
| Note | Free-text note |
| Attachment | File upload |

### AI-Powered Analysis
Powered by HuggingFace Inference API, available to signers before they sign:

- **Legal Clause Detection** — Zero-shot classification scans document text against 10 risk categories (liability, indemnification, non-compete, arbitration, termination, confidentiality, penalties, IP assignment, warranty disclaimer, limitation of liability). Each finding is scored and categorized as low/medium/high risk.
- **Document Translation** — Auto-detects non-English text and translates documents using Helsinki-NLP translation models, helping signers understand what they're signing.

### PDF Flattening
Signed field values are baked into the final PDF using client-side pdf-lib. The flattened PDF is uploaded and stored so that completed document emails attach the signed version, not the blank original.

### Authentication & Security
- User registration with email verification
- Two-factor authentication (TOTP) with recovery codes
- Password reset flow
- Access code verification for document signing

## Tech Stack

### Backend
- **Laravel 12** — PHP 8.2+ framework
- **Laravel Fortify** — Authentication scaffolding with 2FA
- **Inertia.js** — Server-driven SPA bridge
- **Google Drive** via `masbug/flysystem-google-drive-ext` — Document file storage

### Frontend
- **Vue 3** (Composition API + TypeScript)
- **Tailwind CSS 4** — Utility-first styling
- **shadcn/ui (reka-ui)** — Accessible component primitives
- **pdfjs-dist** — Client-side PDF rendering to canvas
- **pdf-lib** — Client-side PDF manipulation (flattening signed fields)
- **interactjs** — Drag-and-drop and resize for field placement
- **signature_pad** — Canvas-based signature drawing
- **vue-sonner** — Toast notifications
- **lucide-vue-next** — Icon library

### AI
- **HuggingFace Inference API**
  - `facebook/bart-large-mnli` — Zero-shot classification for legal analysis
  - `Helsinki-NLP/opus-mt-mul-en` — Multi-language translation

## Setup

### Requirements
- PHP 8.2+
- Node.js 18+
- Composer
- A database (MySQL, PostgreSQL, or SQLite)
- Google Drive API credentials (for file storage)
- HuggingFace API token (for AI features, optional)

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd dockusign

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Build frontend assets
npm run build
```

### Environment Variables

Configure the following in `.env`:

```env
# Database
DB_CONNECTION=mysql
DB_DATABASE=dockusign

# Google Drive Storage
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REFRESH_TOKEN=
GOOGLE_DRIVE_FOLDER=

# HuggingFace AI (optional)
HUGGINGFACE_API_TOKEN=

# Mail (for sending signing invitations)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
```

### Development

```bash
# Start the dev server
composer run dev

# Or run separately
php artisan serve
npm run dev
```

### Code Quality

```bash
# Lint PHP
./vendor/bin/pint

# Lint & fix JS/TS/Vue
npm run lint

# Format with Prettier
npm run format

# Run tests
php artisan test
```

## Database Schema

| Table | Key | Description |
|-------|-----|-------------|
| `documents` | UUID | Core document record — title, status, signing order |
| `document_files` | Auto ID | Uploaded files — original path, PDF path, flattened paths per recipient |
| `document_recipients` | UUID | Signers and CC recipients — email, role, status, access code |
| `document_fields` | UUID | Placed fields — type, page, position (percentage-based), linked to recipient |
| `document_field_values` | Auto ID | Filled values — text content or file path, linked to field and recipient |
| `document_activities` | Auto ID | Audit log — event type, description, metadata |

## License

This project is open-source.
