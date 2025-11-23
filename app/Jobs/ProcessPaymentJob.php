<?php

namespace App\Jobs;

use App\Services\Payment\DTO\PaymentRequest;
use App\Services\Payment\PaymentXmlBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 120, 300];

    public function __construct(
        public PaymentRequest $paymentRequest,
        public ?string $traceId = null
    ) {
    }

    public function handle(PaymentXmlBuilder $xmlBuilder): void
    {
        if ($this->traceId) {
            set_trace_id($this->traceId);
        }

        Log::info('Processing payment job', with_trace([
            'reference' => $this->paymentRequest->reference,
            'amount' => $this->paymentRequest->amount->format(),
        ]));

        try {
            $xml = $xmlBuilder->buildWithValidation($this->paymentRequest);

            // NOTE: Communication with the bank would happen here
            Log::info('Payment XML generated successfully', with_trace([
                'reference' => $this->paymentRequest->reference,
                'xml_length' => strlen($xml),
            ]));

            // In a real implementation, you would:
            // 1. Send the XML to the bank's API
            // 2. Store the response
            // 3. Update transaction status
            // 4. Trigger any necessary events/notifications

        } catch (\Exception $e) {
            Log::error('Failed to process payment job', with_trace([
                'reference' => $this->paymentRequest->reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]));

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Payment job failed after all retries', with_trace([
            'reference' => $this->paymentRequest->reference,
            'error' => $exception->getMessage(),
        ]));

        // In a real implementation, you might:
        // 1. Update transaction status to 'failed'
        // 2. Send notifications to relevant parties
        // 3. Store failure details for manual review
    }
}
