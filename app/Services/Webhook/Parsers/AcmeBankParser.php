<?php

namespace App\Services\Webhook\Parsers;

use App\Services\Webhook\DTO\ParsedTransaction;
use App\Services\Webhook\WebhookParserInterface;
use App\ValueObjects\Currency;
use DateTime;
use InvalidArgumentException;

/**
 * Acme Bank Webhook Parser
 *
 * Format: Amount (two decimals), "//", Reference, "//", Date
 * Example: 156,50//202506159000001//20250615
 */
class AcmeBankParser implements WebhookParserInterface
{
    public function parse(string $payload): array
    {
        $lines = array_filter(
            explode("\n", trim($payload)),
            fn($line) => !empty(trim($line))
        );

        $transactions = [];
        foreach ($lines as $lineNumber => $line) {
            try {
                $transactions[] = $this->parseLine(trim($line));
            } catch (\Exception $e) {
                throw new InvalidArgumentException(
                    "Failed to parse line " . ($lineNumber + 1) . ": {$line}. Error: {$e->getMessage()}"
                );
            }
        }

        return $transactions;
    }

    private function parseLine(string $line): ParsedTransaction
    {
        $parts = explode('//', $line);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException(
                'Invalid line format: expected 3 parts separated by //'
            );
        }

        $amountString = trim($parts[0]);
        $reference = trim($parts[1]);
        $dateString = trim($parts[2]);
        $amount = Currency::fromFloatStr($amountString, 'SAR');

        $date = DateTime::createFromFormat('Ymd', $dateString);
        if (!$date) {
            throw new InvalidArgumentException("Invalid date format: {$dateString}");
        }

        return new ParsedTransaction(
            reference: $reference,
            amount: $amount,
            date: $date,
            metadata: []
        );
    }

    public function getBankIdentifier(): string
    {
        return 'acme_bank';
    }

    public function canHandle(string $payload): bool
    {
        $firstLine = trim(explode("\n", $payload)[0] ?? '');
        return str_contains($firstLine, '//') && substr_count($firstLine, '//') >= 2;
    }
}
