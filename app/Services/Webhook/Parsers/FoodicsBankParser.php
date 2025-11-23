<?php

namespace App\Services\Webhook\Parsers;

use App\Services\Webhook\DTO\ParsedTransaction;
use App\Services\Webhook\WebhookParserInterface;
use App\ValueObjects\Currency;
use DateTime;
use InvalidArgumentException;

/**
 * Foodics Bank Webhook Parser
 *
 * Format: Date, Amount (two decimals), "#", Reference, "#", Key-value pairs
 * Example: 20250615156,50#202506159000001#note/debt payment march/internal_reference/A462JE81
 */
class FoodicsBankParser implements WebhookParserInterface
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
        $parts = explode('#', $line);
        if (count($parts) < 2) {
            throw new InvalidArgumentException('Invalid line format: missing # separator');
        }

        $dateAndAmount = $parts[0];
        if (!preg_match('/^(\d{8})(.+)$/', $dateAndAmount, $matches)) {
            throw new InvalidArgumentException('Invalid date/amount format');
        }

        $dateString = $matches[1]; // YYYYMMDD
        $amountString = $matches[2]; // Amount with comma as decimal separator
        $date = DateTime::createFromFormat('Ymd', $dateString);
        if (!$date) {
            throw new InvalidArgumentException("Invalid date format: {$dateString}");
        }

        $amount = Currency::fromFloatStr($amountString, 'SAR');
        $reference = $parts[1];

        $metadata = [];
        if (isset($parts[2])) {
            $metadataString = $parts[2];
            $segments = explode('/', $metadataString);

            for ($i = 0; $i < count($segments); $i += 2) {
                if (isset($segments[$i])) {
                    $key = trim($segments[$i]);
                    $value = isset($segments[$i + 1]) ? trim($segments[$i + 1]) : '';
                    if (!empty($key)) {
                        $metadata[$key] = $value;
                    }
                }
            }
        }

        return new ParsedTransaction(
            reference: $reference,
            amount: $amount,
            date: $date,
            metadata: $metadata
        );
    }

    public function getBankIdentifier(): string
    {
        return 'foodics_bank';
    }

    public function canHandle(string $payload): bool
    {
        $firstLine = trim(explode("\n", $payload)[0] ?? '');
        return preg_match('/^\d{8}.+#.+/', $firstLine) === 1;
    }
}
