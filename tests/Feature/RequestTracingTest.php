<?php

use App\Jobs\ProcessWebhookJob;
use App\Models\Acquirer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// TRC-YYYYMMDD-SERVERID-NNNNNN
const TRACE_ID_PATTERN = '/^TRC-(\d{8})-([A-Z0-9]{8})-(\d{6})$/';

describe('Request Tracing', function () {
    beforeEach(function () {
        $this->foodicsAcquirer = Acquirer::factory()->foodicsBank()->create();
        Queue::fake();
    });

    test('webhook endpoint returns trace_id in incremental format', function () {
        $response = $this->call(
            'POST',
            '/api/webhooks/foodics-bank',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            '20250615156,50#REF001#note/test payment'
        );

        $response->assertStatus(202);
        $response->assertJsonStructure(['trace_id']);
        $traceId = $response->json('trace_id');

        expect($traceId)->toMatch(TRACE_ID_PATTERN);
        expect($response->headers->get('X-Trace-ID'))->toBe($traceId);

        Queue::assertPushed(ProcessWebhookJob::class, function ($job) use ($traceId) {
            return $job->traceId === $traceId;
        });
    });

    test('payment endpoint returns trace_id in incremental format', function () {
        $response = $this->postJson('/api/payments/transfer', [
            'reference' => 'PAY-TRACE-001',
            'date' => '2025-11-23',
            'amount' => 100.50,
            'currency' => 'SAR',
            'sender_account_number' => 'SA123456789',
            'receiver_bank_code' => 'BANK001',
            'receiver_account_number' => 'SA987654321',
            'beneficiary_name' => 'Test User',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure(['trace_id']);
        $traceId = $response->json('trace_id');

        expect($traceId)->toMatch(TRACE_ID_PATTERN);
        expect($response->headers->get('X-Trace-ID'))->toBe($traceId);
    });

    test('trace_id increments for each request', function () {
        $response1 = $this->postJson('/api/payments/transfer', [
            'reference' => 'PAY-001',
            'date' => '2025-11-23',
            'amount' => 100,
            'currency' => 'SAR',
            'sender_account_number' => 'SA123',
            'receiver_bank_code' => 'BANK001',
            'receiver_account_number' => 'SA456',
            'beneficiary_name' => 'User 1',
        ]);

        $response2 = $this->postJson('/api/payments/transfer', [
            'reference' => 'PAY-002',
            'date' => '2025-11-23',
            'amount' => 200,
            'currency' => 'SAR',
            'sender_account_number' => 'SA789',
            'receiver_bank_code' => 'BANK001',
            'receiver_account_number' => 'SA012',
            'beneficiary_name' => 'User 2',
        ]);

        $traceId1 = $response1->json('trace_id');
        $traceId2 = $response2->json('trace_id');

        // Extract parts: TRC-YYYYMMDD-SERVERID-NNNNNN
        preg_match(TRACE_ID_PATTERN, $traceId1, $matches1);
        preg_match(TRACE_ID_PATTERN, $traceId2, $matches2);

        // Same date and server
        expect($matches1[1])->toBe($matches2[1]); // date
        expect($matches1[2])->toBe($matches2[2]); // server ID

        // Counter should increment
        $counter1 = (int) $matches1[3];
        $counter2 = (int) $matches2[3];
        expect($counter2)->toBe($counter1 + 1);
    });

    test('webhook job is dispatched with trace_id', function () {
        $response = $this->call(
            'POST',
            '/api/webhooks/foodics-bank',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            '20250615156,50#REF002#note/test'
        );
        $traceId = $response->json('trace_id');

        Queue::assertPushed(ProcessWebhookJob::class, function ($job) use ($traceId) {
            return $job->traceId === $traceId;
        });
    });

    test('trace_id helper returns current trace_id', function () {
        $this->get('/')->assertStatus(200);

        $traceId = trace_id();
        expect($traceId)->not->toBeNull();
        expect($traceId)->toMatch(TRACE_ID_PATTERN);
    });

    test('with_trace helper adds trace_id to context', function () {
        $this->get('/')->assertStatus(200);

        $context = with_trace(['foo' => 'bar']);
        expect($context)->toHaveKey('trace_id');
        expect($context)->toHaveKey('foo');
        expect($context['foo'])->toBe('bar');
        expect($context['trace_id'])->toMatch(TRACE_ID_PATTERN);
    });

    test('trace_id format includes date', function () {
        $response = $this->postJson('/api/payments/transfer', [
            'reference' => 'PAY-DATE-001',
            'date' => '2025-11-23',
            'amount' => 100,
            'currency' => 'SAR',
            'sender_account_number' => 'SA123',
            'receiver_bank_code' => 'BANK001',
            'receiver_account_number' => 'SA456',
            'beneficiary_name' => 'User',
        ]);

        $traceId = $response->json('trace_id');
        $today = now()->format('Ymd');

        expect($traceId)->toStartWith("TRC-{$today}-");
    });
});
