<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Request;
use App\Services\Settings;

abstract class BaseProvider implements PaymentProvider
{
    abstract public function defaultLabel(): string;

    abstract public function defaultDescription(): string;

    /** Name shown to customers — admin override (Settings → Payments) or default. */
    public function label(): string
    {
        $custom = trim((string) Settings::get('payments.methods.' . $this->code() . '.label', ''));
        return $custom !== '' ? $custom : $this->defaultLabel();
    }

    public function description(): string
    {
        $custom = trim((string) Settings::get('payments.methods.' . $this->code() . '.description', ''));
        return $custom !== '' ? $custom : $this->defaultDescription();
    }

    /** Checkout display order (lower first). */
    public function sortOrder(): int
    {
        return (int) Settings::get('payments.methods.' . $this->code() . '.sort', 100);
    }

    public function isImplemented(): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return (bool) Settings::get('payments.' . $this->code() . '.enabled', false);
    }

    public function availableFor(int $totalMinor, bool $codAllowedByRate): bool
    {
        return true;
    }

    public function reservationExpires(): bool
    {
        return false;
    }

    public function start(array $order, array $payment): array
    {
        return ['type' => 'none'];
    }

    public function parseWebhook(Request $request): ?array
    {
        return null;
    }

    public function fetchStatus(array $payment): ?string
    {
        return null;
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    public function refund(array $payment, int $amountMinor, string $reason): string
    {
        throw new \LogicException($this->label() . ' does not support API refunds. Record the refund manually.');
    }
}
