<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFieldValue extends Model
{
    protected $fillable = [
        'document_field_id',
        'recipient_id',
        'value',
        'file_path',
        'filled_at',
    ];

    protected function casts(): array
    {
        return [
            'filled_at' => 'datetime',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(DocumentField::class, 'document_field_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(DocumentRecipient::class, 'recipient_id');
    }
}
