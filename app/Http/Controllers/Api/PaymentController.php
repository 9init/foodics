<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Jobs\ProcessPaymentJob;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function transfer(StorePaymentRequest $request): JsonResponse
    {
        $traceId = trace_id();
        try {
            $paymentRequest = $request->toPaymentRequest();
            ProcessPaymentJob::dispatch($paymentRequest, $traceId);

            return response()->json([
                'status' => 'accepted',
                'message' => 'Payment request queued for processing',
                'reference' => $paymentRequest->reference,
                'trace_id' => $traceId,
            ], 202);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace_id' => $traceId,
            ], 400);
        }
    }
}
