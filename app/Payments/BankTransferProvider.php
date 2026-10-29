<?php

declare(strict_types=1);

namespace App\Payments;

use App\Services\Settings;

/**
 * Manual bank transfer: customer sees configured account details, submits a
 * transaction reference (+ optional receipt), and the order stays awaiting
 * payment until authorised staff verify it.
 */
final class BankTransferProvider extends BaseProvider implements ManualPayment
{
    public function code(): string
    {
        return 'bank_transfer';
    }

    public function defaultLabel(): string
    {
        return 'Bank Transfer';
    }

    public function defaultDescription(): string
    {
        return 'Transfer to our bank account. Your order is confirmed once we verify the payment.';
    }

    /** Requires account details before it can be offered. */
    public function isConfigured(): bool
    {
        $s = Settings::get('payments.bank_transfer', []);
        return trim((string) ($s['bank_name'] ?? '')) !== ''
            && trim((string) ($s['account_title'] ?? '')) !== ''
            && (trim((string) ($s['account_number'] ?? '')) !== '' || trim((string) ($s['iban'] ?? '')) !== '');
    }

    public function initialOrderStatus(): string
    {
        return 'awaiting_payment';
    }

    public function initialPaymentStatus(): string
    {
        return 'pending';
    }

    public function start(array $order, array $payment): array
    {
        return ['type' => 'instructions'];
    }

    public function validReference(string $reference): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9 \-\/#.]{4,120}$/', $reference);
    }

    public function referenceLabel(): string
    {
        return 'Bank transaction ID';
    }

    public function explorerUrl(string $reference): ?string
    {
        return null;
    }
}
