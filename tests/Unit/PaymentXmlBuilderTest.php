<?php

use App\Services\Payment\DTO\PaymentRequest;
use App\Services\Payment\PaymentXmlBuilder;
use App\ValueObjects\Currency;

describe('Payment XML Builder', function () {
    test('builds XML with all elements', function () {
        $builder = new PaymentXmlBuilder();

        $payment = new PaymentRequest(
            reference: 'e0f4763d-28ea-42d4-ac1c-c4013c242105',
            date: new DateTime('2025-02-25 06:33:00', new DateTimeZone('+03:00')),
            amount: Currency::fromMajorUnit(177.39, 'SAR'),
            senderAccountNumber: 'SA6980000204608016212908',
            receiverBankCode: 'FDCSSARI',
            receiverAccountNumber: 'SA6980000204608016211111',
            beneficiaryName: 'Jane Doe',
            notes: ['Lorem Epsum', 'Dolor Sit Amet'],
            paymentType: '421',
            chargeDetails: 'RB'
        );

        $xml = $builder->build($payment);

        expect($xml)->toContain('<?xml version="1.0" encoding="utf-8"?>')
            ->and($xml)->toContain('<PaymentRequestMessage>')
            ->and($xml)->toContain('<Reference>e0f4763d-28ea-42d4-ac1c-c4013c242105</Reference>')
            ->and($xml)->toContain('<Amount>177.39</Amount>')
            ->and($xml)->toContain('<Currency>SAR</Currency>')
            ->and($xml)->toContain('<AccountNumber>SA6980000204608016212908</AccountNumber>')
            ->and($xml)->toContain('<BankCode>FDCSSARI</BankCode>')
            ->and($xml)->toContain('<BeneficiaryName>Jane Doe</BeneficiaryName>')
            ->and($xml)->toContain('<Note>Lorem Epsum</Note>')
            ->and($xml)->toContain('<Note>Dolor Sit Amet</Note>')
            ->and($xml)->toContain('<PaymentType>421</PaymentType>')
            ->and($xml)->toContain('<ChargeDetails>RB</ChargeDetails>');
    });

    test('excludes Notes element when notes are empty', function () {
        $builder = new PaymentXmlBuilder();

        $payment = new PaymentRequest(
            reference: 'test-ref',
            date: new DateTime(),
            amount: Currency::fromMajorUnit(100, 'SAR'),
            senderAccountNumber: 'SA1234567890',
            receiverBankCode: 'FDCSSARI',
            receiverAccountNumber: 'SA0987654321',
            beneficiaryName: 'John Doe',
            notes: [] // Empty notes
        );

        $xml = $builder->build($payment);

        expect($xml)->not->toContain('<Notes>')
            ->and($xml)->not->toContain('<Note>');
    });

    test('excludes PaymentType when value is 99', function () {
        $builder = new PaymentXmlBuilder();

        $payment = new PaymentRequest(
            reference: 'test-ref',
            date: new DateTime(),
            amount: Currency::fromMajorUnit(100, 'SAR'),
            senderAccountNumber: 'SA1234567890',
            receiverBankCode: 'FDCSSARI',
            receiverAccountNumber: 'SA0987654321',
            beneficiaryName: 'John Doe',
            paymentType: '99'
        );

        $xml = $builder->build($payment);

        expect($xml)->not->toContain('<PaymentType>');
    });

    test('excludes ChargeDetails when value is SHA', function () {
        $builder = new PaymentXmlBuilder();

        $payment = new PaymentRequest(
            reference: 'test-ref',
            date: new DateTime(),
            amount: Currency::fromMajorUnit(100, 'SAR'),
            senderAccountNumber: 'SA1234567890',
            receiverBankCode: 'FDCSSARI',
            receiverAccountNumber: 'SA0987654321',
            beneficiaryName: 'John Doe',
            chargeDetails: 'SHA'
        );

        $xml = $builder->build($payment);

        expect($xml)->not->toContain('<ChargeDetails>');
    });

    test('includes PaymentType when value is not 99', function () {
        $builder = new PaymentXmlBuilder();

        $payment = new PaymentRequest(
            reference: 'test-ref',
            date: new DateTime(),
            amount: Currency::fromMajorUnit(100, 'SAR'),
            senderAccountNumber: 'SA1234567890',
            receiverBankCode: 'FDCSSARI',
            receiverAccountNumber: 'SA0987654321',
            beneficiaryName: 'John Doe',
            paymentType: '421'
        );

        $xml = $builder->build($payment);

        expect($xml)->toContain('<PaymentType>421</PaymentType>');
    });

    test('includes ChargeDetails when value is not SHA', function () {
        $builder = new PaymentXmlBuilder();

        $payment = new PaymentRequest(
            reference: 'test-ref',
            date: new DateTime(),
            amount: Currency::fromMajorUnit(100, 'SAR'),
            senderAccountNumber: 'SA1234567890',
            receiverBankCode: 'FDCSSARI',
            receiverAccountNumber: 'SA0987654321',
            beneficiaryName: 'John Doe',
            chargeDetails: 'RB'
        );

        $xml = $builder->build($payment);

        expect($xml)->toContain('<ChargeDetails>RB</ChargeDetails>');
    });

    test('validates payment request', function () {
        $builder = new PaymentXmlBuilder();

        $payment = new PaymentRequest(
            reference: '',
            date: new DateTime(),
            amount: Currency::fromMajorUnit(-100, 'SAR'),
            senderAccountNumber: '',
            receiverBankCode: '',
            receiverAccountNumber: '',
            beneficiaryName: ''
        );

        $errors = $builder->validate($payment);

        expect($errors)->toContain('Reference is required')
            ->and($errors)->toContain('Amount must be positive')
            ->and($errors)->toContain('Sender account number is required')
            ->and($errors)->toContain('Receiver bank code is required')
            ->and($errors)->toContain('Receiver account number is required')
            ->and($errors)->toContain('Beneficiary name is required');
    });

    test('buildWithValidation throws exception for invalid payment', function () {
        $builder = new PaymentXmlBuilder();

        $payment = new PaymentRequest(
            reference: '',
            date: new DateTime(),
            amount: Currency::fromMajorUnit(100, 'SAR'),
            senderAccountNumber: 'SA1234567890',
            receiverBankCode: 'FDCSSARI',
            receiverAccountNumber: 'SA0987654321',
            beneficiaryName: 'John Doe'
        );

        $builder->buildWithValidation($payment);
    })->throws(InvalidArgumentException::class);

    test('builds valid XML for minimal payment', function () {
        $builder = new PaymentXmlBuilder();

        $payment = new PaymentRequest(
            reference: 'test-ref-123',
            date: new DateTime('2025-11-22 10:00:00'),
            amount: Currency::fromMajorUnit(500.00, 'SAR'),
            senderAccountNumber: 'SA1234567890123456',
            receiverBankCode: 'TESTBANK',
            receiverAccountNumber: 'SA9876543210987654',
            beneficiaryName: 'Test Beneficiary'
        );

        $xml = $builder->buildWithValidation($payment);

        expect($xml)->toContain('<Reference>test-ref-123</Reference>')
            ->and($xml)->toContain('<Amount>500.00</Amount>')
            ->and($xml)->toContain('<Currency>SAR</Currency>')
            ->and($xml)->toContain('<BeneficiaryName>Test Beneficiary</BeneficiaryName>');
    });

    test('properly formats dates with timezone', function () {
        $builder = new PaymentXmlBuilder();

        $date = new DateTime('2025-02-25 06:33:00', new DateTimeZone('+03:00'));

        $payment = new PaymentRequest(
            reference: 'test-ref',
            date: $date,
            amount: Currency::fromMajorUnit(100, 'SAR'),
            senderAccountNumber: 'SA1234567890',
            receiverBankCode: 'FDCSSARI',
            receiverAccountNumber: 'SA0987654321',
            beneficiaryName: 'John Doe'
        );

        $xml = $builder->build($payment);

        expect($xml)->toContain('<Date>2025-02-25 06:33:00+03:00</Date>');
    });
});
