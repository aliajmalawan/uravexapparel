<?php

declare(strict_types=1);

namespace App\Payments;

use App\Services\Settings;

/** Cash on Delivery: order confirmed immediately; staff record collection. */
final class CodProvider extends BaseProvider
{
    public function code(): string
    {
        return 'cod';
    }

    public function defaultLabel(): string
    {
        return 'Cash on Delivery';
    }

    public function defaultDescription(): string
    {
        return (string) Settings::get('payments.cod.note', 'Pay in cash when your order arrives.');
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function availableFor(int $totalMinor, bool $codAllowedByRate): bool
    {
        $max = (int) Settings::get('payments.cod.max_order_minor', 0);
        return $codAllowedByRate && ($max === 0 || $totalMinor <= $max);
    }

    public function initialOrderStatus(): string
    {
        return 'confirmed';
    }

    public function initialPaymentStatus(): string
    {
        return 'pending';
    }
}
