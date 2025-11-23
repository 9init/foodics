<?php

namespace App\Services\Webhook\DTO;

use App\ValueObjects\Currency;
use DateTime;

class ParsedTransaction
{
    public function __construct(
        public readonly string $reference,
        public readonly Currency $amount,
        public readonly DateTime $date,
        public readonly array $metadata = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            reference: $data['reference'],
            amount: $data['amount'],
            date: $data['date'],
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'amount' => [
                'value' => $this->amount->getAmount(),
                'currency' => $this->amount->getCurrency(),
            ],
            'date' => $this->date->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
        ];
    }
}
