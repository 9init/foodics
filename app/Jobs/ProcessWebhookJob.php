<?php

namespace App\Jobs;

use App\Services\Webhook\IngestionManager;
use App\Services\Webhook\WebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 120, 300];

    public function __construct(
        public string $payload,
        public string $acquirerIdentifier,
        public ?string $traceId = null
    ) {
    }

    public function handle(WebhookProcessor $processor, IngestionManager $ingestionManager): void
    {
        if ($this->traceId) {
            set_trace_id($this->traceId);
        }

        if ($ingestionManager->isPaused()) {
            Log::info('Webhook processing paused, releasing job back to queue', with_trace([
                'acquirer_identifier' => $this->acquirerIdentifier,
            ]));

            $this->release(300);
            return;
        }

        Log::info('Processing webhook job', with_trace([
            'acquirer_identifier' => $this->acquirerIdentifier,
            'payload_length' => strlen($this->payload),
        ]));

        try {
            $webhookLog = $processor->process($this->payload, $this->acquirerIdentifier, $this->traceId);

            Log::info('Webhook processed successfully', with_trace([
                'webhook_log_id' => $webhookLog->id,
                'transactions_count' => $webhookLog->transactions_count,
                'processed_count' => $webhookLog->processed_count,
                'failed_count' => $webhookLog->failed_count,
            ]));
        } catch (\Exception $e) {
            Log::error('Failed to process webhook job', with_trace([
                'error' => $e->getMessage(),
                'acquirer_identifier' => $this->acquirerIdentifier,
            ]));

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook job failed after all retries', with_trace([
            'error' => $exception->getMessage(),
            'acquirer_identifier' => $this->acquirerIdentifier,
        ]));
    }
}
