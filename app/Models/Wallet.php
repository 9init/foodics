<?php

namespace App\Models;

use App\ValueObjects\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    protected $fillable = [
        'client_id',
        'currency',
        'balance',
        'type',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'integer',
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getBalanceMoney(): Currency
    {
        return Currency::fromMinorUnit($this->balance, $this->currency);
    }

    public function credit(Currency $amount): void
    {
        if (!$amount->getCurrency() === $this->currency) {
            throw new \InvalidArgumentException(
                "Currency mismatch: wallet is {$this->currency}, amount is {$amount->getCurrency()}"
            );
        }

        DB::transaction(function () use ($amount) {
            // Use pessimistic locking to prevent race conditions
            $this->lockForUpdate()->increment('balance', $amount->getAmount());
        });

        $this->refresh();
    }

    public function debit(Currency $amount): void
    {
        if (!$amount->getCurrency() === $this->currency) {
            throw new \InvalidArgumentException(
                "Currency mismatch: wallet is {$this->currency}, amount is {$amount->getCurrency()}"
            );
        }

        if ($this->getBalanceMoney()->isLessThan($amount)) {
            throw new \DomainException('Insufficient funds');
        }

        DB::transaction(function () use ($amount) {
            // Use pessimistic locking to prevent race conditions
            $this->lockForUpdate()->decrement('balance', $amount->getAmount());
        });

        $this->refresh();
    }


    public function getFormattedBalanceAttribute(): string
    {
        return $this->getBalanceMoney()->format();
    }
}
