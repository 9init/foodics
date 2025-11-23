<?php

namespace App\Services\Webhook;

use App\Services\Webhook\DTO\ParsedTransaction;

interface WebhookParserInterface
{
    /**
     * Parse webhook payload and return array of parsed transactions
     *
     * @param string $payload Raw webhook payload
     * @return ParsedTransaction[]
     */
    public function parse(string $payload): array;

    /**
     * Get the bank identifier for this parser
     */
    public function getBankIdentifier(): string;

    /**
     * Validate if this parser can handle the given payload
     */
    public function canHandle(string $payload): bool;
}
