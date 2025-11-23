<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Services\Webhook\IngestionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Controller
 *
 * Handles incoming webhooks from banks
 *
 * IMPORTANT: Each bank should have its own dedicated endpoint to ensure proper
 * source identification. The same transaction reference can exist in multiple banks,
 * so idempotency is enforced using (reference, acquirer_id) composite key.
 *
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly IngestionManager $ingestionManager
    ) {
    }

    public function handleBank(Request $request, string $bank): JsonResponse
    {
        $payload = $request->getContent();
        $traceId = trace_id();

        if (empty($payload)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Empty payload',
            ], 400);
        }

        // Queue the webhook for processing with bank identifier
        // Note: If ingestion is paused, the job will be held in queue and retried later
        ProcessWebhookJob::dispatch($payload, $bank, $traceId);

        Log::info('Webhook received and queued', with_trace([
            'bank' => $bank,
            'payload_size' => strlen($payload),
            'ip' => $request->ip(),
            'paused' => $this->ingestionManager->isPaused(),
        ]));

        return response()->json([
            'status' => 'accepted',
            'message' => 'Webhook received and queued for processing',
            'trace_id' => $traceId,
        ], 202);
    }
}
