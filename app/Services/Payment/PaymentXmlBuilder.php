<?php

namespace App\Services\Payment;

use App\Services\Payment\DTO\PaymentRequest;
use DOMDocument;
use DOMElement;

class PaymentXmlBuilder
{
    /**
     * Build XML from payment request
     */
    public function build(PaymentRequest $payment): string
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('PaymentRequestMessage');
        $dom->appendChild($root);

        $this->addTransferInfo($dom, $root, $payment);
        $this->addSenderInfo($dom, $root, $payment);
        $this->addReceiverInfo($dom, $root, $payment);

        if ($payment->hasNotes()) {
            $this->addNotes($dom, $root, $payment);
        }

        if ($payment->shouldIncludePaymentType()) {
            $this->addElement($dom, $root, 'PaymentType', $payment->paymentType);
        }

        if ($payment->shouldIncludeChargeDetails()) {
            $this->addElement($dom, $root, 'ChargeDetails', $payment->chargeDetails);
        }

        return $dom->saveXML();
    }

    private function addTransferInfo(DOMDocument $dom, DOMElement $root, PaymentRequest $payment): void
    {
        $transferInfo = $dom->createElement('TransferInfo');
        $root->appendChild($transferInfo);

        $this->addElement($dom, $transferInfo, 'Reference', $payment->reference);
        $this->addElement($dom, $transferInfo, 'Date', $payment->date->format('Y-m-d H:i:sP'));
        $this->addElement($dom, $transferInfo, 'Amount', $payment->amount->toDecimalString());
        $this->addElement($dom, $transferInfo, 'Currency', $payment->amount->getCurrency());
    }

    private function addSenderInfo(DOMDocument $dom, DOMElement $root, PaymentRequest $payment): void
    {
        $senderInfo = $dom->createElement('SenderInfo');
        $root->appendChild($senderInfo);

        $this->addElement($dom, $senderInfo, 'AccountNumber', $payment->senderAccountNumber);
    }

    private function addReceiverInfo(DOMDocument $dom, DOMElement $root, PaymentRequest $payment): void
    {
        $receiverInfo = $dom->createElement('ReceiverInfo');
        $root->appendChild($receiverInfo);

        $this->addElement($dom, $receiverInfo, 'BankCode', $payment->receiverBankCode);
        $this->addElement($dom, $receiverInfo, 'AccountNumber', $payment->receiverAccountNumber);
        $this->addElement($dom, $receiverInfo, 'BeneficiaryName', $payment->beneficiaryName);
    }

    private function addNotes(DOMDocument $dom, DOMElement $root, PaymentRequest $payment): void
    {
        $notes = $dom->createElement('Notes');
        $root->appendChild($notes);

        foreach ($payment->notes as $note) {
            $this->addElement($dom, $notes, 'Note', $note);
        }
    }

    private function addElement(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
    {
        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode($value));
        $parent->appendChild($element);
    }

    public function validate(PaymentRequest $payment): array
    {
        $errors = [];

        if (empty($payment->reference)) {
            $errors[] = 'Reference is required';
        }

        if (!$payment->amount->isPositive()) {
            $errors[] = 'Amount must be positive';
        }

        if (empty($payment->senderAccountNumber)) {
            $errors[] = 'Sender account number is required';
        }

        if (empty($payment->receiverBankCode)) {
            $errors[] = 'Receiver bank code is required';
        }

        if (empty($payment->receiverAccountNumber)) {
            $errors[] = 'Receiver account number is required';
        }

        if (empty($payment->beneficiaryName)) {
            $errors[] = 'Beneficiary name is required';
        }

        return $errors;
    }

    public function buildWithValidation(PaymentRequest $payment): string
    {
        $errors = $this->validate($payment);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Payment validation failed: ' . implode(', ', $errors));
        }

        return $this->build($payment);
    }
}
