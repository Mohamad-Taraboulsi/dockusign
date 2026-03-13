export type DocumentStatus = 'draft' | 'sent' | 'in_progress' | 'completed' | 'declined' | 'voided';
export type RecipientStatus = 'pending' | 'sent' | 'opened' | 'signed' | 'declined';
export type RecipientRole = 'signer' | 'cc';
export type SigningOrder = 'parallel' | 'sequential';

export type FieldType =
    | 'signature'
    | 'initials'
    | 'stamp'
    | 'text_name'
    | 'text_title'
    | 'text_email'
    | 'checkbox'
    | 'dropdown'
    | 'radio'
    | 'note'
    | 'attachment'
    | 'date_signed';

export type Document = {
    id: string;
    user_id: number;
    title: string;
    subject: string | null;
    message: string | null;
    status: DocumentStatus;
    signing_order: SigningOrder;
    completed_at: string | null;
    voided_at: string | null;
    created_at: string;
    updated_at: string;
    files?: DocumentFile[];
    recipients?: DocumentRecipient[];
    fields?: DocumentField[];
    activities?: DocumentActivity[];
    user?: { id: number; name: string; email: string };
};

export type DocumentFile = {
    id: number;
    document_id: string;
    original_name: string;
    stored_path: string;
    pdf_path: string | null;
    mime_type: string;
    size_bytes: number;
    page_count: number;
    sort_order: number;
};

export type DocumentRecipient = {
    id: string;
    document_id: string;
    user_id: number | null;
    email: string;
    name: string | null;
    role: RecipientRole;
    sort_order: number;
    status: RecipientStatus;
    sent_at: string | null;
    opened_at: string | null;
    signed_at: string | null;
    declined_at: string | null;
    decline_reason: string | null;
};

export type DocumentField = {
    id: string;
    document_id: string;
    document_file_id: number;
    recipient_id: string;
    type: FieldType;
    label: string | null;
    placeholder: string | null;
    page_number: number;
    position_x: number;
    position_y: number;
    width: number;
    height: number;
    is_required: boolean;
    validation_rules: Record<string, unknown> | null;
    options: Record<string, unknown> | null;
    sort_order: number;
    recipient?: DocumentRecipient;
    value?: DocumentFieldValue;
};

export type DocumentFieldValue = {
    id: number;
    document_field_id: string;
    recipient_id: string;
    value: string | null;
    file_path: string | null;
    filled_at: string | null;
};

export type DocumentActivity = {
    id: number;
    document_id: string;
    recipient_id: string | null;
    user_id: number | null;
    type: string;
    description: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    user?: { id: number; name: string; email: string } | null;
    recipient?: DocumentRecipient | null;
};

export type FieldTypeConfig = {
    type: FieldType;
    label: string;
    icon: string;
    group: 'text' | 'elements' | 'other';
    defaultWidth: number;
    defaultHeight: number;
};

export const FIELD_TYPES: FieldTypeConfig[] = [
    { type: 'signature', label: 'Signature', icon: 'PenTool', group: 'elements', defaultWidth: 20, defaultHeight: 5 },
    { type: 'initials', label: 'Initials', icon: 'Type', group: 'elements', defaultWidth: 10, defaultHeight: 5 },
    { type: 'stamp', label: 'Stamp', icon: 'Stamp', group: 'elements', defaultWidth: 15, defaultHeight: 8 },
    { type: 'text_name', label: 'Full Name', icon: 'User', group: 'text', defaultWidth: 20, defaultHeight: 3 },
    { type: 'text_title', label: 'Title', icon: 'Briefcase', group: 'text', defaultWidth: 20, defaultHeight: 3 },
    { type: 'text_email', label: 'Email', icon: 'Mail', group: 'text', defaultWidth: 20, defaultHeight: 3 },
    { type: 'checkbox', label: 'Checkbox', icon: 'CheckSquare', group: 'elements', defaultWidth: 3, defaultHeight: 3 },
    { type: 'dropdown', label: 'Dropdown', icon: 'ChevronDown', group: 'elements', defaultWidth: 20, defaultHeight: 3 },
    { type: 'radio', label: 'Radio', icon: 'Circle', group: 'elements', defaultWidth: 3, defaultHeight: 3 },
    { type: 'note', label: 'Note', icon: 'FileText', group: 'other', defaultWidth: 25, defaultHeight: 5 },
    { type: 'attachment', label: 'Attachment', icon: 'Paperclip', group: 'other', defaultWidth: 15, defaultHeight: 5 },
    { type: 'date_signed', label: 'Date Signed', icon: 'Calendar', group: 'other', defaultWidth: 15, defaultHeight: 3 },
];

export const RECIPIENT_COLORS = [
    '#3B82F6', // blue
    '#10B981', // green
    '#F59E0B', // amber
    '#EF4444', // red
    '#8B5CF6', // violet
    '#EC4899', // pink
    '#06B6D4', // cyan
    '#F97316', // orange
];

export const STATUS_LABELS: Record<DocumentStatus, string> = {
    draft: 'Draft',
    sent: 'Sent',
    in_progress: 'In Progress',
    completed: 'Completed',
    declined: 'Declined',
    voided: 'Voided',
};
