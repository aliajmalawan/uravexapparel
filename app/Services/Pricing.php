<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Money;

/**
 * Pure pricing functions (no I/O) so they are fully unit-testable. All amounts
 * are integer minor units.
 */
final class Pricing
{
    /**
     * Effective unit price for a variant: variant override → product price,
     * with a sale price applied only when it is lower than the regular price.
     *
     * @return array{regular:int, price:int, on_sale:bool}
     */
    public static function unitPrice(array $product, array $variant = []): array
    {
        $variantPrice = isset($variant['price_minor']) ? (int) $variant['price_minor'] : null;
        $regular = $variantPrice ?? (int) $product['price_minor'];
        // A variant price override only takes the variant's own sale price;
        // otherwise a variant sale price wins over the product sale price.
        $saleRaw = $variantPrice !== null
            ? ($variant['sale_price_minor'] ?? null)
            : ($variant['sale_price_minor'] ?? $product['sale_price_minor'] ?? null);
        $sale = $saleRaw !== null ? (int) $saleRaw : null;
        $onSale = $sale !== null && $sale > 0 && $sale < $regular;
        return ['regular' => $regular, 'price' => $onSale ? $sale : $regular, 'on_sale' => $onSale];
    }

    /**
     * Computes order totals.
     *
     * @param list<array{key:string|int, unit:int, qty:int, eligible?:bool}> $lines
     * @param array|null $coupon  evaluated coupon: ['type','value','max_discount_minor','free_shipping'] or null
     * @param int|null   $shippingMinor  base shipping for the selected rate (null = not chosen yet)
     * @param int|null   $freeOverMinor  free shipping threshold for that rate (null = none)
     * @param int        $taxPercent     whole-percent tax applied on discounted subtotal (0 = none)
     *
     * @return array{subtotal:int, eligible_subtotal:int, discount:int, shipping:int, free_shipping:bool, tax:int, total:int, line_discounts:array<string|int,int>, lines:array<string|int,int>}
     */
    public static function totals(array $lines, ?array $coupon, ?int $shippingMinor, ?int $freeOverMinor = null, int $taxPercent = 0): array
    {
        $subtotal = 0;
        $eligible = 0;
        $lineTotals = [];
        $weights = [];
        $eligibleKeys = [];
        foreach ($lines as $l) {
            $qty = max(0, (int) $l['qty']);
            $lt = (int) $l['unit'] * $qty;
            $lineTotals[$l['key']] = $lt;
            $subtotal += $lt;
            if ($l['eligible'] ?? true) {
                $eligible += $lt;
                $eligibleKeys[] = $l['key'];
                $weights[] = $lt;
            }
        }

        $discount = 0;
        $freeShipping = false;
        if ($coupon) {
            $type = $coupon['type'];
            if ($type === 'percentage') {
                $discount = Money::percentOf($eligible, (int) $coupon['value']);
            } elseif ($type === 'fixed') {
                $discount = min((int) $coupon['value'], $eligible);
            }
            if (!empty($coupon['max_discount_minor'])) {
                $discount = min($discount, (int) $coupon['max_discount_minor']);
            }
            $freeShipping = $type === 'free_shipping' || !empty($coupon['free_shipping']);
        }
        $discount = max(0, min($discount, $subtotal));

        // Allocate the order-level discount across eligible lines (exact sum).
        $lineDiscounts = array_fill_keys(array_keys($lineTotals), 0);
        if ($discount > 0 && $eligibleKeys) {
            foreach (Money::allocate($discount, $weights) as $i => $part) {
                $lineDiscounts[$eligibleKeys[$i]] = $part;
            }
        }

        $afterDiscount = $subtotal - $discount;
        $shipping = 0;
        if ($shippingMinor !== null) {
            $qualifiesFree = $freeOverMinor !== null && $freeOverMinor > 0 && $afterDiscount >= $freeOverMinor;
            $shipping = ($freeShipping || $qualifiesFree) ? 0 : $shippingMinor;
            $freeShipping = $freeShipping || $qualifiesFree;
        }
        $tax = $taxPercent > 0 ? Money::percentOf($afterDiscount, $taxPercent) : 0;

        return [
            'subtotal' => $subtotal,
            'eligible_subtotal' => $eligible,
            'discount' => $discount,
            'shipping' => $shipping,
            'free_shipping' => $freeShipping,
            'tax' => $tax,
            'total' => $afterDiscount + $shipping + $tax,
            'line_discounts' => $lineDiscounts,
            'lines' => $lineTotals,
        ];
    }

    /** Fingerprint of what the customer was shown, to detect changes at submit. */
    public static function fingerprint(array $totals, array $lines): string
    {
        $items = [];
        foreach ($lines as $l) {
            $items[] = $l['key'] . ':' . $l['unit'] . 'x' . $l['qty'];
        }
        // Order-independent: the bag lists items as added, checkout reads them by variant id.
        sort($items, SORT_STRING);
        $parts = array_merge([$totals['subtotal'], $totals['discount'], $totals['shipping'], $totals['tax'], $totals['total']], $items);
        return hash('sha256', implode('|', $parts));
    }
}
