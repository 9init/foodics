<?php

namespace App\Http\Requests;

use App\Services\Payment\DTO\PaymentRequest;
use App\ValueObjects\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'sender_account_number' => ['required', 'string', 'max:255'],
            'receiver_bank_code' => ['required', 'string', 'max:255'],
            'receiver_account_number' => ['required', 'string', 'max:255'],
            'beneficiary_name' => ['required', 'string', 'max:255'],
            'notes' => ['sometimes', 'array'],
            'notes.*' => ['string', 'max:500'],
            'payment_type' => ['sometimes', 'nullable', 'string', 'max:10'],
            'charge_details' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sender_account_number' => 'sender account number',
            'receiver_bank_code' => 'receiver bank code',
            'receiver_account_number' => 'receiver account number',
            'beneficiary_name' => 'beneficiary name',
            'payment_type' => 'payment type',
            'charge_details' => 'charge details',
        ];
    }

    public function toPaymentRequest(): PaymentRequest
    {
        $validated = $this->validated();

        return PaymentRequest::fromArray([
            'reference' => $validated['reference'],
            'date' => new \DateTime($validated['date']),
            'amount' => Currency::fromMajorUnit($validated['amount'], $validated['currency']),
            'sender_account_number' => $validated['sender_account_number'],
            'receiver_bank_code' => $validated['receiver_bank_code'],
            'receiver_account_number' => $validated['receiver_account_number'],
            'beneficiary_name' => $validated['beneficiary_name'],
            'notes' => $validated['notes'] ?? [],
            'payment_type' => $validated['payment_type'] ?? null,
            'charge_details' => $validated['charge_details'] ?? null,
        ]);
    }


    protected function passedValidation(): void
    {
        \Log::info('Payment request validated', [
            'reference' => $this->validated('reference'),
            'amount' => $this->validated('amount'),
            'currency' => $this->validated('currency'),
            'ip' => $this->ip(),
        ]);
    }
}
