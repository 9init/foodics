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
                DB::transaction(function () use ($parsedTransactions, $acquirer, $traceId, $webhookLog) {
                    // Bulk duplicate check - get all existing references in one query
                    $references = array_map(fn($pt) => $pt->reference, $parsedTransactions);
                    $existingReferences = Transaction::where('acquirer_id', $acquirer->id)
                        ->whereIn('reference', $references)
                        ->pluck('reference')
                        ->flip()
                        ->toArray();

                    // Prepare bulk insert data
                    $transactionsToInsert = [];
                    $now = now();

                    foreach ($parsedTransactions as $parsedTransaction) {
                        // Skip if duplicate
                        if (isset($existingReferences[$parsedTransaction->reference])) {
                            continue;
                        }

                        $currency = $parsedTransaction->amount->getCurrency() ?: $acquirer->currency;

                        $transactionsToInsert[] = [
                            'trace_id' => $traceId,
                            'acquirer_id' => $acquirer->id,
                            'reference' => $parsedTransaction->reference,
                            'type' => Transaction::TYPE_CREDIT,
                            'amount' => $parsedTransaction->amount->getAmount(),
                            'currency' => $currency,
                            'source' => $acquirer->identifier,
                            'metadata' => json_encode($parsedTransaction->metadata),
                            'transaction_date' => $parsedTransaction->date,
                            'status' => Transaction::STATUS_COMPLETED,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    // Bulk insert all transactions in chunks to avoid max_allowed_packet issues
                    if (!empty($transactionsToInsert)) {
                        // Insert in chunks of 500 to stay within packet limits
                        foreach (array_chunk($transactionsToInsert, 500) as $chunk) {
                            Transaction::insert($chunk);
                        }
                    }

                    $processedCount = count($transactionsToInsert);
                    $duplicateCount = count($parsedTransactions) - $processedCount;

                    if ($duplicateCount > 0) {
                        Log::info('Duplicate transactions detected', with_trace([
                            'duplicate_count' => $duplicateCount,
                            'acquirer' => $acquirer->identifier,
                        ]));
                    }

                    Log::info('Batch transactions processed', with_trace([
                        'total_count' => count($parsedTransactions),
                        'processed_count' => $processedCount,
                        'duplicate_count' => $duplicateCount,
                        'acquirer' => $acquirer->identifier,
                    ]));
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
}
