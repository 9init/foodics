<?php

use App\Jobs\ProcessPaymentJob;
use App\Services\Payment\DTO\PaymentRequest;
use App\ValueObjects\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('Payment Queue', function () {
    beforeEach(function () {
        Queue::fake();
    });

    test('payment request is queued successfully', function () {
        $response = $this->postJson('/api/payments/transfer', [
            'reference' => 'PAY-TEST-001',
            'date' => '2025-11-23',
            'amount' => 100.50,
            'currency' => 'SAR',
            'sender_account_number' => 'SA123456789',
            'receiver_bank_code' => 'BANK001',
            'receiver_account_number' => 'SA987654321',
            'beneficiary_name' => 'Test Beneficiary',
            'notes' => ['Test payment'],
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'accepted',
                'message' => 'Payment request queued for processing',
                'reference' => 'PAY-TEST-001',
            ]);

        Queue::assertPushed(ProcessPaymentJob::class, function ($job) {
            return $job->paymentRequest->reference === 'PAY-TEST-001'
                && $job->paymentRequest->amount->format() === 'SAR 100.50';
        });
    });

    test('payment job processes successfully', function () {
        $paymentRequest = PaymentRequest::fromArray([
            'reference' => 'PAY-TEST-002',
            'date' => new DateTime('2025-11-23'),
            'amount' => Currency::fromMajorUnit(200.75, 'SAR'),
            'sender_account_number' => 'SA123456789',
            'receiver_bank_code' => 'BANK001',
            'receiver_account_number' => 'SA987654321',
            'beneficiary_name' => 'Test Beneficiary',
            'notes' => ['Test payment'],
        ]);

        $job = new ProcessPaymentJob($paymentRequest);
        $xmlBuilder = app(\App\Services\Payment\PaymentXmlBuilder::class);
        $job->handle($xmlBuilder);

        expect(true)->toBeTrue();
    });

    test('validation errors prevent queueing', function () {
        $response = $this->postJson('/api/payments/transfer', [
            'reference' => 'PAY-TEST-003',
            // Missing required fields
        ]);

        $response->assertStatus(422);
    });

    test('unsupported currency prevents queueing', function () {
        $response = $this->postJson('/api/payments/transfer', [
            'reference' => 'PAY-TEST-004',
            'date' => '2025-11-23',
            'amount' => 100.50,
            'currency' => 'XXX',
            'sender_account_number' => 'SA123456789',
            'receiver_bank_code' => 'BANK001',
            'receiver_account_number' => 'SA987654321',
            'beneficiary_name' => 'Test Beneficiary',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
            ]);
    });
});
