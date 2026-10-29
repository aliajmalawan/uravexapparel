<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Request;

/**
 * Contract every payment method implements. Checkout and webhooks only talk
 * to this interface, so adding a provider never requires changing checkout.
 */
interface PaymentProvider
{
    public function code(): string;

    public function label(): string;

    /** Short customer-facing description shown at checkout. */
    public function description(): string;

    /** False for providers listed for future integration but not yet built. */
    public function isImplemented(): bool;

    /** Credentials / settings complete for the current mode. */
    public function isConfigured(): bool;

    /** Admin toggle in Settings → Payments. */
    public function isEnabled(): bool;

    /** Extra per-order rules (e.g. COD max amount, courier COD availability). */
    public function availableFor(int $totalMinor, bool $codAllowedByRate): bool;

    /** Initial order status for this method: 'confirmed' or 'awaiting_payment'. */
    public function initialOrderStatus(): string;

    /** Initial payment row status. */
    public function initialPaymentStatus(): string;

    /** Whether stock reservation expires if payment does not arrive. */
    public function reservationExpires(): bool;

    /**
     * Begins payment after the order is committed.
     * @return array{type:'none'|'redirect'|'instructions', url?:string}
     */
    public function start(array $order, array $payment): array;

    /**
     * Verifies a webhook's signature and returns a normalized event, or null
     * when the signature is invalid.
     *
     * @return array{id:string, type:string, reference:string, status:string, amount_minor:int, currency:string, payload:array}|null
     */
    public function parseWebhook(Request $request): ?array;

    /**
     * Asks the provider's API for the authoritative status of a payment.
     * Returns one of: paid, pending, failed, cancelled, expired — or null if
     * the provider cannot be reached.
     */
    public function fetchStatus(array $payment): ?string;

    public function supportsRefunds(): bool;

    /** Issues a refund through the provider. Returns provider reference. */
    public function refund(array $payment, int $amountMinor, string $reason): string;
}
