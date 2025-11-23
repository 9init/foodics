<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    protected $fillable = [
        'acquirer_id',
        'bank_identifier',
        'payload',
        'status',
        'transactions_count',
        'processed_count',
        'failed_count',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'transactions_count' => 'integer',
        'processed_count' => 'integer',
        'failed_count' => 'integer',
    ];

    protected $attributes = [
        'transactions_count' => 0,
        'processed_count' => 0,
        'failed_count' => 0,
    ];

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function acquirer(): BelongsTo
    {
        return $this->belongsTo(Acquirer::class);
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
            'processed_at' => now(),
        ]);
    }

    public function incrementProcessed(): void
    {
        $this->increment('processed_count');
    }

    public function incrementFailed(): void
    {
        $this->increment('failed_count');
    }
}
