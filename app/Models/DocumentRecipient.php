<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentRecipient extends Model
{
    use HasUuids;

    protected $fillable = [
        'document_id',
        'user_id',
        'email',
        'name',
        'role',
        'sort_order',
        'access_code',
        'status',
        'sent_at',
        'opened_at',
        'signed_at',
        'declined_at',
        'decline_reason',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'signed_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class, 'recipient_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(DocumentFieldValue::class, 'recipient_id');
    }

    public function isSigner(): bool
    {
        return $this->role === 'signer';
    }

    public function hasSigned(): bool
    {
        return $this->status === 'signed';
    }

    public function hasDeclined(): bool
    {
        return $this->status === 'declined';
    }
}
