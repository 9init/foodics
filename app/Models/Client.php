<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'account_number',
        'bank_code',
        'country_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function getOrCreateWallet(string $currency, string $type = 'main'): Wallet
    {
        return $this->wallets()
            ->firstOrCreate(
                [
                    'currency' => strtoupper($currency),
                    'type' => $type,
                ],
                [
                    'balance' => 0,
                    'is_active' => true,
                ]
            );
    }

    public function getWallet(string $currency, string $type = 'main'): ?Wallet
    {
        return $this->wallets()
            ->where('currency', strtoupper($currency))
            ->where('type', $type)
            ->first();
    }
}
