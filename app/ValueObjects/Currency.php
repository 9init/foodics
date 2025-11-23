<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Currency Value Object
 *
 * Represents monetary values using the smallest currency unit (e.g., piastres, halalas)
 * to avoid floating-point precision issues. This is critical for financial calculations.
 *
 * Examples:
 * - EGP 156.50 is stored as 15650 piastres (100 piastres = 1 EGP)
 * - SAR 156.50 is stored as 15650 halalas (100 halalas = 1 SAR)
 */
class Currency implements JsonSerializable
{
    private int $amount;
    private string $currency;

    private const CURRENCY_PRECISION = [
        'EGP' => 100,   // Egyptian Pound (100 piastres)
        'SAR' => 100,   // Saudi Riyal (100 halalas)
        'USD' => 100,   // US Dollar (100 cents)
        'EUR' => 100,   // Euro (100 cents)
        'GBP' => 100,   // British Pound (100 pence)
        'AED' => 100,   // UAE Dirham (100 fils)
        'BHD' => 1000,  // Bahraini Dinar (1000 fils)
        'KWD' => 1000,  // Kuwaiti Dinar (1000 fils)
        'OMR' => 1000,  // Omani Rial (1000 baisa)
        'JOD' => 1000,  // Jordanian Dinar (1000 fils)
        'JPY' => 1,     // Japanese Yen (no minor unit)
        'KRW' => 1,     // Korean Won (no minor unit)
    ];

    private function __construct(int $amount, string $currency)
    {
        $this->validateCurrency($currency);
        $this->amount = $amount;
        $this->currency = strtoupper($currency);
    }

    /**
     * Create Currency from smallest unit (e.g., halalas, cents)
     */
    public static function fromMinorUnit(int $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    /**
     * Create Currency from major unit (e.g., SAR, USD) with decimal value
     *
     * @param float|string $amount Use string for exact precision (e.g., "156.50")
     */
    public static function fromMajorUnit(float|string $amount, string $currency): self
    {
        $currency = strtoupper($currency);
        self::validateCurrency($currency);

        $precision = self::CURRENCY_PRECISION[$currency];
        $amountString = is_float($amount) ? number_format($amount, 10, '.', '') : $amount;
        $amountString = rtrim(rtrim($amountString, '0'), '.');
        $minorAmount = (int) round((float) $amountString * $precision);

        return new self($minorAmount, $currency);
    }

    /**
     * Parse amount from float string (e.g., "156,50" or "156.50")
     */
    public static function fromFloatStr(string $amount, string $currency): self
    {
        $normalized = str_replace(',', '.', $amount);
        return self::fromMajorUnit($normalized, $currency);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Get amount in major units as float (for display purposes only)
     */
    public function toFloat(): float
    {
        $precision = self::CURRENCY_PRECISION[$this->currency];
        return $this->amount / $precision;
    }

    /**
     * Get formatted amount with decimal places (e.g., "156.50")
     */
    public function toDecimalString(): string
    {
        $precision = self::CURRENCY_PRECISION[$this->currency];
        $decimalPlaces = strlen((string) $precision) - 1;

        return number_format($this->amount / $precision, $decimalPlaces, '.', '');
    }

    /**
     * Get formatted amount for display (e.g., "SAR 156.50")
     */
    public function format(): string
    {
        return $this->currency . ' ' . $this->toDecimalString();
    }

    public function add(Currency $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Currency $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int|float $multiplier): self
    {
        return new self((int) round($this->amount * $multiplier), $this->currency);
    }

    public function isGreaterThan(Currency $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    public function isLessThan(Currency $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    public function equals(Currency $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    private function assertSameCurrency(Currency $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot perform operation on different currencies: {$this->currency} and {$other->currency}"
            );
        }
    }

    private static function validateCurrency(string $currency): void
    {
        $currency = strtoupper($currency);

        if (!isset(self::CURRENCY_PRECISION[$currency])) {
            throw new InvalidArgumentException(
                "Unsupported currency: {$currency}. Add it to CURRENCY_PRECISION map."
            );
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
