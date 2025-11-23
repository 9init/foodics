<?php

use App\Services\Webhook\Parsers\AcmeBankParser;
use App\Services\Webhook\Parsers\FoodicsBankParser;
use App\Services\Webhook\WebhookParserFactory;

describe('Foodics Bank Parser', function () {
    test('can parse single transaction', function () {
        $parser = new FoodicsBankParser();
        $payload = "20250615156,50#202506159000001#note/debt payment march/internal_reference/A462JE81";

        $transactions = $parser->parse($payload);

        expect($transactions)->toHaveCount(1);

        $transaction = $transactions[0];
        expect($transaction->reference)->toBe('202506159000001')
            ->and($transaction->amount->toDecimalString())->toBe('156.50')
            ->and($transaction->amount->getCurrency())->toBe('SAR')
            ->and($transaction->date->format('Ymd'))->toBe('20250615')
            ->and($transaction->metadata)->toHaveKey('note', 'debt payment march')
            ->and($transaction->metadata)->toHaveKey('internal_reference', 'A462JE81');
    });

    test('can parse multiple transactions', function () {
        $parser = new FoodicsBankParser();
        $payload = "20250615156,50#202506159000001#note/payment 1\n20250616200,00#202506169000002#note/payment 2";

        $transactions = $parser->parse($payload);

        expect($transactions)->toHaveCount(2)
            ->and($transactions[0]->reference)->toBe('202506159000001')
            ->and($transactions[1]->reference)->toBe('202506169000002');
    });

    test('can identify foodics bank format', function () {
        $parser = new FoodicsBankParser();
        $payload = "20250615156,50#202506159000001#note/payment";

        expect($parser->canHandle($payload))->toBeTrue();
    });

    test('rejects invalid format', function () {
        $parser = new FoodicsBankParser();
        $payload = "invalid format";

        expect($parser->canHandle($payload))->toBeFalse();
    });

    test('throws exception for invalid line', function () {
        $parser = new FoodicsBankParser();
        $payload = "invalid#format";

        $parser->parse($payload);
    })->throws(InvalidArgumentException::class);
});

describe('Acme Bank Parser', function () {
    test('can parse single transaction', function () {
        $parser = new AcmeBankParser();
        $payload = "156,50//202506159000001//20250615";

        $transactions = $parser->parse($payload);

        expect($transactions)->toHaveCount(1);

        $transaction = $transactions[0];
        expect($transaction->reference)->toBe('202506159000001')
            ->and($transaction->amount->toDecimalString())->toBe('156.50')
            ->and($transaction->amount->getCurrency())->toBe('SAR')
            ->and($transaction->date->format('Ymd'))->toBe('20250615')
            ->and($transaction->metadata)->toBeEmpty();
    });

    test('can parse multiple transactions', function () {
        $parser = new AcmeBankParser();
        $payload = "156,50//202506159000001//20250615\n200,00//202506169000002//20250616";

        $transactions = $parser->parse($payload);

        expect($transactions)->toHaveCount(2)
            ->and($transactions[0]->reference)->toBe('202506159000001')
            ->and($transactions[1]->reference)->toBe('202506169000002');
    });

    test('can identify acme bank format', function () {
        $parser = new AcmeBankParser();
        $payload = "156,50//202506159000001//20250615";

        expect($parser->canHandle($payload))->toBeTrue();
    });

    test('rejects invalid format', function () {
        $parser = new AcmeBankParser();
        $payload = "20250615156,50#202506159000001";

        expect($parser->canHandle($payload))->toBeFalse();
    });

    test('throws exception for invalid line format', function () {
        $parser = new AcmeBankParser();
        $payload = "invalid//format";

        $parser->parse($payload);
    })->throws(InvalidArgumentException::class);
});

describe('Webhook Parser Factory', function () {
    test('can auto-detect foodics bank parser', function () {
        $factory = new WebhookParserFactory();
        $payload = "20250615156,50#202506159000001#note/payment";

        $parser = $factory->detectParser($payload);

        expect($parser)->toBeInstanceOf(FoodicsBankParser::class)
            ->and($parser->getBankIdentifier())->toBe('foodics_bank');
    });

    test('can auto-detect acme bank parser', function () {
        $factory = new WebhookParserFactory();
        $payload = "156,50//202506159000001//20250615";

        $parser = $factory->detectParser($payload);

        expect($parser)->toBeInstanceOf(AcmeBankParser::class)
            ->and($parser->getBankIdentifier())->toBe('acme_bank');
    });

    test('can get parser by identifier', function () {
        $factory = new WebhookParserFactory();

        $parser = $factory->getParser('foodics_bank');

        expect($parser)->toBeInstanceOf(FoodicsBankParser::class);
    });

    test('throws exception for unknown bank identifier', function () {
        $factory = new WebhookParserFactory();

        $factory->getParser('unknown_bank');
    })->throws(InvalidArgumentException::class);

    test('throws exception when no parser can handle payload', function () {
        $factory = new WebhookParserFactory();

        $factory->detectParser('completely invalid format');
    })->throws(InvalidArgumentException::class);
});
