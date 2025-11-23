# Foodics Pay - Online Transaction Processing Application

A production-ready online transaction processing application built with Laravel for receiving and sending money across multiple banks and currencies.

## Table of Contents

-   [Installation](#installation)
-   [Usage](#usage)
-   [API Documentation](#api-documentation)
-   [Testing](#testing)
-   [Architecture & Design Decisions](#architecture--design-decisions)

## Installation

```bash
# Clone the repository
git clone https://github.com/9init/foodics.git
cd foodics

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=foodics
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations
php artisan migrate

# Seed database (mandatory acquirers)
php artisan db:seed --class=AcquirerSeeder

# Run tests
php artisan test

# Start queue worker (for processing webhooks)
php artisan queue:work

# Start development server
php artisan serve
```

## Usage

### Receiving Webhooks

```bash
# Specific bank endpoint
curl -X POST http://localhost:8000/api/webhooks/foodics-bank \
  -H "Content-Type: text/plain" \
  -d "20250615156,50#202506159000001#note/payment"

# Pause ingestion (webhooks queued but not processed)
curl -X POST http://localhost:8000/api/payments/ingestion/pause

# Resume ingestion
curl -X POST http://localhost:8000/api/payments/ingestion/resume

# Check status
curl http://localhost:8000/api/payments/ingestion/status
```

### Payment Transfer

```bash
curl -X POST http://localhost:8000/api/payments/transfer \
  -H "Content-Type: application/json" \
  -d '{
    "reference": "e0f4763d-28ea-42d4-ac1c-c4013c242105",
    "date": "2025-02-25 06:33:00",
    "amount": 177.39,
    "currency": "SAR",
    "sender_account_number": "SA6980000204608016212908",
    "receiver_bank_code": "FDCSSARI",
    "receiver_account_number": "SA6980000204608016211111",
    "beneficiary_name": "Jane Doe",
    "notes": ["Lorem Epsum", "Dolor Sit Amet"],
    "payment_type": "421",
    "charge_details": "RB"
  }'
```

## API Documentation

### POST /api/webhooks/{bank}

Process webhook from specific bank (foodics_bank, acme_bank).

### POST /api/payments/ingestion/pause

Temporarily pause webhook processing (webhooks still queued).

### POST /api/payments/ingestion/resume

Resume webhook processing.

### GET /api/payments/ingestion/status

Check if ingestion is paused or active.

### POST /api/payments/generate-xml

Generate payment XML for bank transfer.

## Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Unit/MoneyTest.php

# Performance test (>=1000 transactions)
php artisan test --filter "processes large webhook"
```

## Architecture & Design Decisions

### 1. Money Value Object (Avoiding Floating-Point Issues)

**Problem**: Floating-point arithmetic in financial calculations can lead to precision errors.

**Solution**: All monetary values are stored and calculated using the **smallest currency unit** (e.g., halalas for SAR, cents for USD, fils for KWD).

```php
// BAD: Using floats
$amount = 156.50; // Can become 156.49999999...

// GOOD: Using smallest units
$amount = 15650; // 156.50 SAR = 15650 halalas (exact integer)
```

The `Currency` value object handles:

-   Currency-specific precision
-   Safe arithmetic operations
-   Proper formatting for display
-   Validation and type safety

### 2. Strategy Pattern for Webhook Parsers

**Problem**: Different banks use different webhook formats (Foodics Bank, Acme Bank, etc.).

**Solution**: Implemented a **Strategy Pattern** to handle multiple webhook formats:

```
WebhookParserInterface
├── FoodicsBankParser
├── AcmeBankParser
└── [Future bank parsers...]
```

Benefits:

-   Easy to add new bank formats
-   Each parser is independent and testable
-   No changes to core processing logic

### 3. Idempotency & Duplicate Handling

**Problem**: Banks may send the same transaction multiple times. Additionally, different banks might use the same reference number for different transactions.

**Solution**:

-   Composite unique constraint on `(reference, source)` - not just reference alone
-   Each bank has its own dedicated webhook endpoint
-   Before processing, check if transaction exists for that specific bank
-   Use database transactions for atomicity
-   Webhooks are logged even if transactions are duplicated

**Example**:

```php
// Foodics Bank: Reference "REF001" → Creates transaction
POST /api/webhooks/foodics-bank
Body: 20250615100,00#REF001#note/payment

// Acme Bank: Reference "REF001" → Creates DIFFERENT transaction (same ref, different bank)
POST /api/webhooks/acme-bank
Body: 100,00//REF001//20250615

// Both transactions exist because they're from different sources
// Idempotency key: (REF001, foodics_bank) and (REF001, acme_bank)
```
