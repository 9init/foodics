<?php

namespace App\Services\Webhook;

use App\Services\Webhook\Parsers\AcmeBankParser;
use App\Services\Webhook\Parsers\FoodicsBankParser;
use InvalidArgumentException;

/**
 * Factory for creating webhook parsers
 * Uses auto-detection to determine which parser to use
 */
class WebhookParserFactory
{
    /** @var WebhookParserInterface[] */
    private array $parsers = [];

    public function __construct()
    {
        // Register all available parsers
        $this->registerParser(new FoodicsBankParser());
        $this->registerParser(new AcmeBankParser());
    }

    public function registerParser(WebhookParserInterface $parser): void
    {
        $this->parsers[$parser->getBankIdentifier()] = $parser;
    }

    public function getParser(string $bankIdentifier): WebhookParserInterface
    {
        if (!isset($this->parsers[$bankIdentifier])) {
            throw new InvalidArgumentException("No parser registered for bank: {$bankIdentifier}");
        }

        return $this->parsers[$bankIdentifier];
    }

    public function detectParser(string $payload): WebhookParserInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->canHandle($payload)) {
                return $parser;
            }
        }

        throw new InvalidArgumentException('No parser found that can handle this payload format');
    }

    /**
     * Get all registered parsers
     *
     * @return WebhookParserInterface[]
     */
    public function getAllParsers(): array
    {
        return $this->parsers;
    }
}
