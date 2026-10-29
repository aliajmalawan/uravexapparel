<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Order lifecycle state machine (pure). Payment status is tracked separately
 * on the order (payment_status) and on each payment row.
 */
final class OrderState
{
    public const STATUSES = [
        'pending' => 'Pending',
        'awaiting_payment' => 'Awaiting Payment',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'packed' => 'Packed',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ];

    private const TRANSITIONS = [
        'pending' => ['awaiting_payment', 'confirmed', 'cancelled'],
        'awaiting_payment' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled', 'refunded'],
        'processing' => ['packed', 'cancelled', 'refunded'],
        'packed' => ['shipped', 'cancelled', 'refunded'],
        'shipped' => ['delivered'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    /** Statuses whose orders count as sales in analytics. */
    public const SALE_STATUSES = ['confirmed', 'processing', 'packed', 'shipped', 'delivered', 'refunded'];

    /** Statuses a customer sees as progress steps. */
    public const TIMELINE = ['confirmed', 'processing', 'packed', 'shipped', 'delivered'];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @return list<string> */
    public static function next(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    /**
     * Statuses staff may pick manually from the order screen. Refunded is only
     * reachable through the refund flow; shipped requires tracking details;
     * confirmed from awaiting_payment requires verified payment.
     *
     * @return list<string>
     */
    public static function manualNext(array $order): array
    {
        $out = [];
        foreach (self::next($order['status']) as $to) {
            if ($to === 'refunded') {
                continue;
            }
            if ($to === 'confirmed' && $order['status'] === 'awaiting_payment') {
                continue;
            }
            if ($to === 'cancelled' && in_array($order['payment_status'], ['paid', 'partially_refunded'], true)) {
                // Money was collected: use the refund flow instead.
                continue;
            }
            $out[] = $to;
        }
        return $out;
    }

    public static function isTerminal(string $status): bool
    {
        return self::TRANSITIONS[$status] === [];
    }

    public static function label(string $status): string
    {
        return self::STATUSES[$status] ?? ucfirst($status);
    }
}
