<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'subject',
        'message',
        'status',
        'signing_order',
        'completed_at',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class)->orderBy('sort_order');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(DocumentRecipient::class)->orderBy('sort_order');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DocumentActivity::class)->orderByDesc('created_at');
    }

    public function signers(): HasMany
    {
        return $this->recipients()->where('role', 'signer');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }
}
