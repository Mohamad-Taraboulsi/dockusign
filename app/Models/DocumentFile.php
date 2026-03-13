<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFile extends Model
{
    protected $fillable = [
        'document_id',
        'original_name',
        'stored_path',
        'pdf_path',
        'mime_type',
        'size_bytes',
        'page_count',
        'sort_order',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function getPdfPathForViewing(): string
    {
        return $this->pdf_path ?? $this->stored_path;
    }
}
