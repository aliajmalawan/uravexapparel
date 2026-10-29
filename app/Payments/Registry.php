<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Payment method registry. To add a provider: implement PaymentProvider and
 * register it here — checkout, admin settings and webhooks pick it up.
 */
final class Registry
{
    /** @var array<string,PaymentProvider>|null */
    private static ?array $providers = null;

    /**
     * The store accepts crypto only (USDT TRC20/ERC20, BTC, ETH). The former
     * methods below stay in the codebase for historical orders and the test
     * suite, and are registered only when payments.legacy_methods is true.
     */
    public static bool $legacy = false;

    /** Display names for methods that are no longer offered (historical orders). */
    private const RETIRED_LABELS = [
        'cod' => 'Cash on Delivery', 'bank_transfer' => 'Bank Transfer', 'sandbox' => 'Test payment (sandbox)',
        'safepay' => 'Safepay', 'payfast' => 'PayFast', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa',
    ];

    /** @return array<string,PaymentProvider> */
    public static function all(): array
    {
        if (self::$providers === null) {
            $list = array_map(static fn ($code) => new CryptoTransferProvider($code), array_keys(CryptoTransferProvider::METHODS));
            if (self::$legacy) {
                array_push($list, ...self::legacyProviders());
            }
            self::$providers = [];
            foreach ($list as $p) {
                self::$providers[$p->code()] = $p;
            }
        }
        return self::$providers;
    }

    /** Label for any method code, including retired ones on old orders. */
    public static function label(string $code): string
    {
        return self::get($code)?->label() ?? self::RETIRED_LABELS[$code] ?? ucwords(str_replace('_', ' ', $code));
    }

    /** @return list<PaymentProvider> */
    private static function legacyProviders(): array
    {
        return [
                new CodProvider(),
                new BankTransferProvider(),
                new SandboxProvider(),
                new UnavailableProvider('safepay', 'Safepay (cards & wallets)', 'Pakistani gateway for cards and wallets. Requires an approved Safepay merchant account and API keys.', ['SAFEPAY_API_KEY', 'SAFEPAY_SECRET_KEY', 'SAFEPAY_WEBHOOK_SECRET']),
                new UnavailableProvider('payfast', 'PayFast (Pakistan)', 'Requires an approved PayFast merchant account (merchant ID + secured key).', ['PAYFAST_MERCHANT_ID', 'PAYFAST_SECURED_KEY']),
                new UnavailableProvider('jazzcash', 'JazzCash', 'Requires a JazzCash merchant account (merchant ID, password, integrity salt).', ['JAZZCASH_MERCHANT_ID', 'JAZZCASH_PASSWORD', 'JAZZCASH_INTEGRITY_SALT']),
                new UnavailableProvider('easypaisa', 'Easypaisa', 'Requires an Easypaisa merchant account (store ID + hash key).', ['EASYPAISA_STORE_ID', 'EASYPAISA_HASH_KEY']),
        ];
    }

    public static function get(string $code): ?PaymentProvider
    {
        return self::all()[$code] ?? null;
    }

    /**
     * Methods a customer may choose: implemented, configured, enabled and
     * allowed for this order.
     *
     * @return array<string,PaymentProvider>
     */
    public static function available(int $totalMinor, bool $codAllowedByRate = true): array
    {
        $list = array_filter(
            self::all(),
            static fn (PaymentProvider $p) => $p->isImplemented() && $p->isConfigured() && $p->isEnabled() && $p->availableFor($totalMinor, $codAllowedByRate)
        );
        uasort($list, static fn ($a, $b) => ($a instanceof BaseProvider ? $a->sortOrder() : 100) <=> ($b instanceof BaseProvider ? $b->sortOrder() : 100));
        return $list;
    }

    public static function reset(): void
    {
        self::$providers = null;
    }
}
