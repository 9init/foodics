<?php

use App\Jobs\ProcessWebhookJob;
use App\Models\Acquirer;
use App\Services\Webhook\IngestionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('Webhook Pause Mechanism', function () {
    beforeEach(function () {
        $this->foodicsAcquirer = Acquirer::factory()->foodicsBank()->create();
        Queue::fake();
    });

    test('webhook jobs are queued when ingestion is active', function () {
        $ingestionManager = app(IngestionManager::class);
        $ingestionManager->resume();

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
        $ingestionManager = app(IngestionManager::class);
        $ingestionManager->pause();

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
        $ingestionManager = app(IngestionManager::class);
        $ingestionManager->pause();

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
        $job->handle($processor, $ingestionManager);

        expect($job->released)->toBeTrue()
            ->and($job->releaseDelay)->toBe(300); // 5 minutes
    });

    test('resumed job processes normally', function () {
        $ingestionManager = app(IngestionManager::class);
        $ingestionManager->resume();
        $job = new class('20250615100,00#REF001#note/test', 'foodics_bank') extends ProcessWebhookJob {
            public bool $released = false;

            public function release($delay = 0): void
            {
                $this->released = true;
            }
        };

        $processor = app(\App\Services\Webhook\WebhookProcessor::class);
        $job->handle($processor, $ingestionManager);

        expect($job->released)->toBeFalse();
    });

    test('pause endpoint sets cache correctly', function () {
        $response = $this->postJson('/api/payments/ingestion/pause');
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $ingestionManager = app(IngestionManager::class);
        expect($ingestionManager->isPaused())->toBeTrue();
    });

    test('resume endpoint clears cache correctly', function () {
        $ingestionManager = app(IngestionManager::class);
        $ingestionManager->pause();
        $response = $this->postJson('/api/payments/ingestion/resume');
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        expect($ingestionManager->isPaused())->toBeFalse();
    });

    test('status endpoint returns correct pause state', function () {
        $ingestionManager = app(IngestionManager::class);
        $ingestionManager->resume();
        $response = $this->getJson('/api/payments/ingestion/status');
        $response->assertJson([
            'status' => 'active',
            'paused' => false,
        ]);

        $ingestionManager->pause();

        $response = $this->getJson('/api/payments/ingestion/status');
        $response->assertJson([
            'status' => 'paused',
            'paused' => true,
        ]);
    });
});
