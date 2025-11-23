<?php

namespace App\Services\Webhook;

use App\Models\Acquirer;
use App\Models\Transaction;
use App\Models\WebhookLog;
use App\Services\Webhook\DTO\ParsedTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookProcessor
{
    public function process(string $payload, string $acquirerIdentifier, ?string $traceId = null): WebhookLog
    {
        $webhookLog = null;

        try {
            $acquirer = Cache::remember(
                "acquirer:{$acquirerIdentifier}",
                now()->addHour(),
                fn() => Acquirer::findByIdentifier($acquirerIdentifier)
            );

            if (!$acquirer) {
                throw new \InvalidArgumentException("Acquirer '{$acquirerIdentifier}' not found or inactive");
            }

            $webhookLog = WebhookLog::create([
                'trace_id' => $traceId,
                'acquirer_id' => $acquirer->id,
                'bank_identifier' => $acquirer->identifier,
                'payload' => $payload,
                'status' => WebhookLog::STATUS_PROCESSING,
            ]);

            // Parse transactions
            $parser = $acquirer->getParserInstance();
            $parsedTransactions = $parser->parse($payload);
            $webhookLog->update([
                'transactions_count' => count($parsedTransactions),
            ]);
            $webhookLog->markAsProcessing();


            try {
                DB::transaction(function () use ($parsedTransactions, $acquirer, $traceId) {
                    foreach ($parsedTransactions as $parsedTransaction) {
                        $this->processTransaction($parsedTransaction, $acquirer, $traceId);
                    }
                });

                $webhookLog->update([
                    'processed_count' => count($parsedTransactions),
                    'failed_count' => 0,
                ]);
                $webhookLog->markAsCompleted();
            } catch (\Exception $e) {
                Log::error('Webhook batch processing failed', with_trace([
                    'acquirer' => $acquirer->identifier,
                    'transaction_count' => count($parsedTransactions),
                    'error' => $e->getMessage(),
                ]));

                $webhookLog->update([
                    'processed_count' => 0,
                    'failed_count' => count($parsedTransactions),
                ]);

                throw $e;
            }
        } catch (\Exception $e) {
            if (!$webhookLog) {
                $webhookLog = WebhookLog::create([
                    'trace_id' => $traceId,
                    'bank_identifier' => $acquirerIdentifier ?? 'unknown',
                    'payload' => $payload,
                    'status' => WebhookLog::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                    'processed_at' => now(),
                ]);
            } else {
                $webhookLog->markAsFailed($e->getMessage());
            }
            throw $e;
        }

        return $webhookLog;
    }

    private function processTransaction(ParsedTransaction $parsedTransaction, Acquirer $acquirer, ?string $traceId = null): void
    {
        $existingTransaction = Transaction::where('reference', $parsedTransaction->reference)
            ->where('acquirer_id', $acquirer->id)
            ->first();

        if ($existingTransaction) {
            Log::info('Duplicate transaction detected, skipping', with_trace([
                'reference' => $parsedTransaction->reference,
                'acquirer' => $acquirer->identifier,
            ]));
            return;
        }

        try {
            $currency = $parsedTransaction->amount->getCurrency() ?: $acquirer->currency;

            $transaction = Transaction::create([
                'trace_id' => $traceId,
                'acquirer_id' => $acquirer->id,
                'reference' => $parsedTransaction->reference,
                'type' => Transaction::TYPE_CREDIT,
                'amount' => $parsedTransaction->amount->getAmount(),
                'currency' => $currency,
                'source' => $acquirer->identifier,
                'metadata' => $parsedTransaction->metadata,
                'transaction_date' => $parsedTransaction->date,
                'status' => Transaction::STATUS_COMPLETED,
            ]);

            Log::info('Transaction processed successfully', with_trace([
                'reference' => $parsedTransaction->reference,
                'amount' => $parsedTransaction->amount->format(),
                'currency' => $currency,
                'acquirer' => $acquirer->identifier,
                'transaction_id' => $transaction->id,
            ]));
        } catch (\Exception $e) {
            Log::error('Failed to process individual transaction', with_trace([
                'reference' => $parsedTransaction->reference,
                'acquirer' => $acquirer->identifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]));

            throw $e;
        }
    }
}
