<?php

namespace App\Models;

use App\Services\Webhook\WebhookParserInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Acquirer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'identifier',
        'parser_class',
        'country_code',
        'currency',
        'webhook_endpoint',
        'api_key',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (Acquirer $acquirer) {
            Cache::forget("acquirer:{$acquirer->identifier}");
        });

        static::deleted(function (Acquirer $acquirer) {
            Cache::forget("acquirer:{$acquirer->identifier}");
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    public static function findByIdentifier(string $identifier): ?self
    {
        return static::where('identifier', $identifier)
            ->where('is_active', true)
            ->first();
    }

    public function getParserInstance(): WebhookParserInterface
    {
        $parserClass = $this->parser_class;
        if (!class_exists($parserClass)) {
            throw new \RuntimeException("Parser class {$parserClass} not found for acquirer {$this->identifier}");
        }

        return new $parserClass();
    }
}
