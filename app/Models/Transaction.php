<?php

namespace App\Models;

use App\ValueObjects\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'acquirer_id',
        'reference',
        'type',
        'amount',
        'currency',
        'source',
        'metadata',
        'transaction_date',
        'status',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'transaction_date' => 'datetime',
    ];

    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';

    public function acquirer(): BelongsTo
    {
        return $this->belongsTo(Acquirer::class);
    }

    public function getAmountMoney(): Currency
    {
        return Currency::fromMinorUnit($this->amount, $this->currency);
    }

    public function isCredit(): bool
    {
        return $this->type === self::TYPE_CREDIT;
    }

    public function isDebit(): bool
    {
        return $this->type === self::TYPE_DEBIT;
    }

    public function getFormattedAmountAttribute(): string
    {
        return $this->getAmountMoney()->format();
    }
}
