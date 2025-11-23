<?php

use App\Jobs\ProcessWebhookJob;
use App\Models\Acquirer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('Webhook Pause Mechanism', function () {
    beforeEach(function () {
        $this->foodicsAcquirer = Acquirer::factory()->foodicsBank()->create();
        Queue::fake();
    });

    test('webhook jobs are queued when ingestion is active', function () {
        Cache::forget('webhook_ingestion_paused');

        $response = $this->call(
            'POST',
            '/api/webhooks/foodics-bank',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            '20250615100,00#REF001#note/test'
        );

        $response->assertStatus(202);

        Queue::assertPushed(ProcessWebhookJob::class);
    });

    test('webhook jobs are still queued when ingestion is paused', function () {
        Cache::put('webhook_ingestion_paused', true, now()->addHours(1));

        $response = $this->call(
            'POST',
            '/api/webhooks/foodics-bank',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            '20250615100,00#REF001#note/test'
        );

        $response->assertStatus(202);
        Queue::assertPushed(ProcessWebhookJob::class);
    });

    test('paused job releases itself back to queue', function () {
        Cache::put('webhook_ingestion_paused', true, now()->addHours(1));

        $job = new ProcessWebhookJob(
            '20250615100,00#REF001#note/test',
            'foodics_bank'
        );

        $job = new class('20250615100,00#REF001#note/test', 'foodics_bank') extends ProcessWebhookJob {
            public bool $released = false;
            public ?int $releaseDelay = null;

            public function release($delay = 0): void
            {
                $this->released = true;
                $this->releaseDelay = $delay;
            }
        };

        $processor = app(\App\Services\Webhook\WebhookProcessor::class);
        $job->handle($processor);

        expect($job->released)->toBeTrue()
            ->and($job->releaseDelay)->toBe(300); // 5 minutes
    });

    test('resumed job processes normally', function () {
        Cache::forget('webhook_ingestion_paused');
        $job = new class('20250615100,00#REF001#note/test', 'foodics_bank') extends ProcessWebhookJob {
            public bool $released = false;

            public function release($delay = 0): void
            {
                $this->released = true;
            }
        };

        $processor = app(\App\Services\Webhook\WebhookProcessor::class);
        $job->handle($processor);

        expect($job->released)->toBeFalse();
    });

    test('pause endpoint sets cache correctly', function () {
        $response = $this->postJson('/api/payments/ingestion/pause');
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        expect(Cache::get('webhook_ingestion_paused'))->toBeTrue();
    });

    test('resume endpoint clears cache correctly', function () {
        Cache::put('webhook_ingestion_paused', true, now()->addHours(1));
        $response = $this->postJson('/api/payments/ingestion/resume');
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        expect(Cache::has('webhook_ingestion_paused'))->toBeFalse();
    });

    test('status endpoint returns correct pause state', function () {
        Cache::forget('webhook_ingestion_paused');
        $response = $this->getJson('/api/payments/ingestion/status');
        $response->assertJson([
            'status' => 'active',
            'paused' => false,
        ]);

        Cache::put('webhook_ingestion_paused', true, now()->addHours(1));

        $response = $this->getJson('/api/payments/ingestion/status');
        $response->assertJson([
            'status' => 'paused',
            'paused' => true,
        ]);
    });
});
