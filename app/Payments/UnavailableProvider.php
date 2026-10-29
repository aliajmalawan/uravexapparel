<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Placeholder for an eligible provider (Safepay, PayFast, JazzCash, Easypaisa,
 * international cards) whose adapter has not been written because official
 * merchant/API access is not yet available. It is never offered at checkout
 * and its webhook endpoint returns 404. See docs/PAYMENTS.md for the steps to
 * implement one against the provider's official documentation.
 */
final class UnavailableProvider extends BaseProvider
{
    /** @param list<string> $envKeys */
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly string $notes,
        private readonly array $envKeys,
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function defaultLabel(): string
    {
        return $this->label;
    }

    public function defaultDescription(): string
    {
        return 'Pay securely online.';
    }

    /** Admin-facing setup notes. */
    public function notes(): string
    {
        return $this->notes;
    }

    public function isImplemented(): bool
    {
        return false;
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function isEnabled(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function envKeys(): array
    {
        return $this->envKeys;
    }

    public function initialOrderStatus(): string
    {
        return 'awaiting_payment';
    }

    public function initialPaymentStatus(): string
    {
        return 'pending';
    }
}
