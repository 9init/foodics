<?php

namespace App\Services\Webhook;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IngestionManager
{
    private const INGESTION_PAUSED_KEY = 'webhook_ingestion_paused';
    private const DEFAULT_PAUSE_DURATION_MINUTES = 60;

    public function isPaused(): bool
    {
        return Cache::get(self::INGESTION_PAUSED_KEY, false);
    }

    public function pause(?int $minutes = null): void
    {
        $minutes = $minutes ?? self::DEFAULT_PAUSE_DURATION_MINUTES;
        $resumeAt = now()->addMinutes($minutes);
        Cache::put(self::INGESTION_PAUSED_KEY, true, $resumeAt);

        Log::warning('Webhook ingestion paused - jobs will be held in queue', [
            'duration_minutes' => $minutes,
            'resume_at' => $resumeAt->toIso8601String(),
        ]);
    }

    public function resume(): void
    {
        Cache::forget(self::INGESTION_PAUSED_KEY);
        Log::info('Webhook ingestion resumed - processing queued jobs');
    }

    public function getCacheKey(): string
    {
        return self::INGESTION_PAUSED_KEY;
    }
}
