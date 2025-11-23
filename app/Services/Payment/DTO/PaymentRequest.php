<?php

namespace App\Services\Payment\DTO;

use App\ValueObjects\Currency;
use DateTime;

class PaymentRequest
{
    public function __construct(
        public readonly string $reference,
        public readonly DateTime $date,
        public readonly Currency $amount,
        public readonly string $senderAccountNumber,
        public readonly string $receiverBankCode,
        public readonly string $receiverAccountNumber,
        public readonly string $beneficiaryName,
        public readonly array $notes = [],
        public readonly ?string $paymentType = null, // Only include if not 99
        public readonly ?string $chargeDetails = null, // Only include if not SHA
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            reference: $data['reference'],
            date: $data['date'] instanceof DateTime ? $data['date'] : new DateTime($data['date']),
            amount: $data['amount'] instanceof Currency ? $data['amount'] : Currency::fromMajorUnit($data['amount'], $data['currency']),
            senderAccountNumber: $data['sender_account_number'],
            receiverBankCode: $data['receiver_bank_code'],
            receiverAccountNumber: $data['receiver_account_number'],
            beneficiaryName: $data['beneficiary_name'],
            notes: $data['notes'] ?? [],
            paymentType: $data['payment_type'] ?? null,
            chargeDetails: $data['charge_details'] ?? null,
        );
    }

    public function hasNotes(): bool
    {
        return !empty($this->notes);
    }

    public function shouldIncludePaymentType(): bool
    {
        return $this->paymentType !== null && $this->paymentType !== '99';
    }

    public function shouldIncludeChargeDetails(): bool
    {
        return $this->chargeDetails !== null && $this->chargeDetails !== 'SHA';
    }
}
