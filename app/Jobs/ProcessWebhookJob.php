<?php

namespace App\Jobs;

use App\Services\Webhook\WebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    private const INGESTION_PAUSED_KEY = 'webhook_ingestion_paused';

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $payload,
        public string $acquirerIdentifier
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookProcessor $processor): void
    {
        if (Cache::get(self::INGESTION_PAUSED_KEY, false)) {
            Log::info('Webhook processing paused, releasing job back to queue', [
                'acquirer_identifier' => $this->acquirerIdentifier,
            ]);

            $this->release(300);
            return;
        }

        Log::info('Processing webhook job', [
            'acquirer_identifier' => $this->acquirerIdentifier,
            'payload_length' => strlen($this->payload),
        ]);

        try {
            $webhookLog = $processor->process($this->payload, $this->acquirerIdentifier);

            Log::info('Webhook processed successfully', [
                'webhook_log_id' => $webhookLog->id,
                'transactions_count' => $webhookLog->transactions_count,
                'processed_count' => $webhookLog->processed_count,
                'failed_count' => $webhookLog->failed_count,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process webhook job', [
                'error' => $e->getMessage(),
                'acquirer_identifier' => $this->acquirerIdentifier,
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook job failed after all retries', [
            'error' => $exception->getMessage(),
            'acquirer_identifier' => $this->acquirerIdentifier,
        ]);
    }
}
