<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentField extends Model
{
    use HasUuids;

    protected $fillable = [
        'document_id',
        'document_file_id',
        'recipient_id',
        'type',
        'label',
        'placeholder',
        'page_number',
        'position_x',
        'position_y',
        'width',
        'height',
        'is_required',
        'validation_rules',
        'options',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'document_file_id' => 'integer',
            'page_number' => 'integer',
            'position_x' => 'float',
            'position_y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'validation_rules' => 'array',
            'options' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(DocumentFile::class, 'document_file_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(DocumentRecipient::class, 'recipient_id');
    }

    public function value(): HasOne
    {
        return $this->hasOne(DocumentFieldValue::class);
    }
}
