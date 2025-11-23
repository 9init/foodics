<?php

use App\Models\Acquirer;
use App\Models\Transaction;
use App\Models\WebhookLog;
use App\Services\Webhook\WebhookProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Webhook Processing', function () {
    beforeEach(function () {
        /** @var Acquirer $foodicsAcquirer */
        $this->foodicsAcquirer = Acquirer::factory()->foodicsBank()->create();
        /** @var Acquirer $acmeAcquirer */
        $this->acmeAcquirer = Acquirer::factory()->acmeBank()->create();
    });

    test('can process foodics bank webhook with single transaction', function () {
        $processor = app(WebhookProcessor::class);
        $payload = "20250615156,50#202506159000001#note/debt payment";
        $webhookLog = $processor->process($payload, 'foodics_bank');

        expect($webhookLog->status)->toBe(WebhookLog::STATUS_COMPLETED)
            ->and($webhookLog->transactions_count)->toBe(1)
            ->and($webhookLog->processed_count)->toBe(1)
            ->and($webhookLog->failed_count)->toBe(0)
            ->and($webhookLog->bank_identifier)->toBe('foodics_bank')
            ->and($webhookLog->acquirer_id)->toBe($this->foodicsAcquirer->id);

        $transaction = Transaction::where('reference', '202506159000001')->first();
        expect($transaction)->not->toBeNull()
            ->and($transaction->amount)->toBe(15650)
            ->and($transaction->currency)->toBe($this->foodicsAcquirer->currency)
            ->and($transaction->type)->toBe(Transaction::TYPE_CREDIT)
            ->and($transaction->acquirer_id)->toBe($this->foodicsAcquirer->id);
    });

    test('can process acme bank webhook with single transaction', function () {
        $processor = app(WebhookProcessor::class);
        $payload = "156,50//202506159000001//20250615";
        $webhookLog = $processor->process($payload, 'acme_bank');

        expect($webhookLog->status)->toBe(WebhookLog::STATUS_COMPLETED)
            ->and($webhookLog->transactions_count)->toBe(1)
            ->and($webhookLog->bank_identifier)->toBe('acme_bank')
            ->and($webhookLog->acquirer_id)->toBe($this->acmeAcquirer->id);

        $transaction = Transaction::where('reference', '202506159000001')->first();
        expect($transaction->currency)->toBe($this->acmeAcquirer->currency);
    });

    test('handles duplicate transactions idempotently', function () {
        $processor = app(WebhookProcessor::class);
        $payload = "20250615156,50#202506159000001#note/payment";

        $processor->process($payload, 'foodics_bank');
        $processor->process($payload, 'foodics_bank');

        $transactionCount = Transaction::where('reference', '202506159000001')->count();
        expect($transactionCount)->toBe(1);
    });

    test('can process webhook with multiple transactions', function () {
        $processor = app(WebhookProcessor::class);
        $payload = "20250615100,00#REF001#note/payment 1\n";
        $payload .= "20250616200,00#REF002#note/payment 2\n";
        $payload .= "20250617300,00#REF003#note/payment 3";

        $webhookLog = $processor->process($payload, 'foodics_bank');

        expect($webhookLog->transactions_count)->toBe(3)
            ->and($webhookLog->processed_count)->toBe(3);

        expect(Transaction::count())->toBe(3);
    });

    test('stores metadata correctly', function () {
        $processor = app(WebhookProcessor::class);
        $payload = "20250615156,50#REF001#note/test payment/category/income/source/bank transfer";
        $processor->process($payload, 'foodics_bank');

        $transaction = Transaction::where('reference', 'REF001')->first();
        expect($transaction->metadata)->toHaveKey('note')
            ->and($transaction->metadata)->toHaveKey('category', 'income')
            ->and($transaction->metadata)->toHaveKey('source', 'bank transfer');
    });

    test('allows same reference from different banks', function () {
        $processor = app(WebhookProcessor::class);
        $foodicsPayload = "20250615100,00#REF001#note/payment from foodics";
        $processor->process($foodicsPayload, 'foodics_bank');
        $acmePayload = "100,00//REF001//20250615";
        $processor->process($acmePayload, 'acme_bank');

        $foodicsTransaction = Transaction::where('reference', 'REF001')
            ->where('acquirer_id', $this->foodicsAcquirer->id)
            ->first();

        $acmeTransaction = Transaction::where('reference', 'REF001')
            ->where('acquirer_id', $this->acmeAcquirer->id)
            ->first();

        expect($foodicsTransaction)->not->toBeNull()
            ->and($acmeTransaction)->not->toBeNull()
            ->and(Transaction::where('reference', 'REF001')->count())->toBe(2);
    });

    test('prevents duplicate from same bank', function () {
        $processor = app(WebhookProcessor::class);
        $payload = "20250615100,00#REF001#note/payment";
        $processor->process($payload, 'foodics_bank');
        $processor->process($payload, 'foodics_bank'); // Duplicate

        $count = Transaction::where('reference', 'REF001')
            ->where('acquirer_id', $this->foodicsAcquirer->id)
            ->count();

        expect($count)->toBe(1);
    });
});
