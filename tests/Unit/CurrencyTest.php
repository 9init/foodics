<?php

use App\ValueObjects\Currency;

describe('Money Value Object', function () {
    test('can create from minor units', function () {
        $money = Currency::fromMinorUnit(15650, 'SAR');

        expect($money->getAmount())->toBe(15650)
            ->and($money->getCurrency())->toBe('SAR')
            ->and($money->toDecimalString())->toBe('156.50');
    });

    test('can create from major units with float', function () {
        $money = Currency::fromMajorUnit(156.50, 'SAR');

        expect($money->getAmount())->toBe(15650)
            ->and($money->toDecimalString())->toBe('156.50');
    });

    test('can create from major units with string for precision', function () {
        $money = Currency::fromMajorUnit('156.50', 'USD');

        expect($money->getAmount())->toBe(15650)
            ->and($money->toDecimalString())->toBe('156.50');
    });

    test('can parse bank format with comma decimal separator', function () {
        $money = Currency::fromFloatStr('156,50', 'SAR');

        expect($money->getAmount())->toBe(15650)
            ->and($money->toDecimalString())->toBe('156.50');
    });

    test('can parse bank format with dot decimal separator', function () {
        $money = Currency::fromFloatStr('156.50', 'SAR');

        expect($money->getAmount())->toBe(15650);
    });

    test('handles different currency precisions correctly', function () {
        $egp = Currency::fromMajorUnit(100, 'EGP');
        expect($egp->getAmount())->toBe(10000);

        $sar = Currency::fromMajorUnit(100, 'SAR');
        expect($sar->getAmount())->toBe(10000);

        $kwd = Currency::fromMajorUnit(100, 'KWD');
        expect($kwd->getAmount())->toBe(100000);

        $jpy = Currency::fromMajorUnit(100, 'JPY');
        expect($jpy->getAmount())->toBe(100);
    });

    test('can add money of same currency', function () {
        $money1 = Currency::fromMajorUnit(100, 'SAR');
        $money2 = Currency::fromMajorUnit(50, 'SAR');

        $result = $money1->add($money2);

        expect($result->toDecimalString())->toBe('150.00');
    });

    test('can subtract money of same currency', function () {
        $money1 = Currency::fromMajorUnit(100, 'SAR');
        $money2 = Currency::fromMajorUnit(50, 'SAR');

        $result = $money1->subtract($money2);

        expect($result->toDecimalString())->toBe('50.00');
    });

    test('can multiply money', function () {
        $money = Currency::fromMajorUnit(100, 'SAR');

        $result = $money->multiply(2);

        expect($result->toDecimalString())->toBe('200.00');
    });

    test('throws exception when adding different currencies', function () {
        $sar = Currency::fromMajorUnit(100, 'SAR');
        $usd = Currency::fromMajorUnit(100, 'USD');

        $sar->add($usd);
    })->throws(InvalidArgumentException::class);

    test('can compare money amounts', function () {
        $money1 = Currency::fromMajorUnit(100, 'SAR');
        $money2 = Currency::fromMajorUnit(50, 'SAR');
        $money3 = Currency::fromMajorUnit(100, 'SAR');

        expect($money1->isGreaterThan($money2))->toBeTrue()
            ->and($money2->isLessThan($money1))->toBeTrue()
            ->and($money1->equals($money3))->toBeTrue();
    });

    test('can check if money is positive, negative, or zero', function () {
        $positive = Currency::fromMajorUnit(100, 'SAR');
        $negative = Currency::fromMinorUnit(-100, 'SAR');
        $zero = Currency::fromMinorUnit(0, 'SAR');

        expect($positive->isPositive())->toBeTrue()
            ->and($negative->isNegative())->toBeTrue()
            ->and($zero->isZero())->toBeTrue();
    });

    test('formats money correctly', function () {
        $money = Currency::fromMajorUnit(156.50, 'SAR');

        expect($money->format())->toBe('SAR 156.50')
            ->and((string) $money)->toBe('SAR 156.50');
    });

    test('throws exception for unsupported currency', function () {
        Currency::fromMajorUnit(100, 'XXX');
    })->throws(InvalidArgumentException::class);

    test('handles high precision correctly for 3 decimal currencies', function () {
        $kwd = Currency::fromMajorUnit('123.456', 'KWD');

        expect($kwd->getAmount())->toBe(123456)
            ->and($kwd->toDecimalString())->toBe('123.456');
    });

    test('handles no decimal currencies correctly', function () {
        $jpy = Currency::fromMajorUnit('1234', 'JPY');

        expect($jpy->getAmount())->toBe(1234)
            ->and($jpy->toDecimalString())->toBe('1234');
    });

    test('converts to float correctly', function () {
        $money = Currency::fromMinorUnit(15650, 'SAR');

        expect($money->toFloat())->toBe(156.50);
    });

    test('serializes to JSON correctly', function () {
        $money = Currency::fromMajorUnit(156.50, 'SAR');

        $json = json_decode(json_encode($money), true);

        expect($json)->toHaveKey('amount', 15650)
            ->and($json)->toHaveKey('currency', 'SAR')
            ->and($json)->toHaveKey('formatted', 'SAR 156.50');
    });
});
