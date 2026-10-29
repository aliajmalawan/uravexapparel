<?php

declare(strict_types=1);

namespace App\Payments;

use App\Core\Database;
use App\Core\Money;
use App\Services\CryptoPrices;
use App\Services\Currency;
use App\Services\Settings;

/**
 * Manual crypto payment to the store's own wallet: USDT on TRON (TRC20),
 * USDT on Ethereum (ERC20), Bitcoin, or Ether.
 *
 * At order time the exact amount, asset, network and wallet address are
 * snapshotted on the payment row (BTC/ETH amounts use the live price and are
 * rounded UP so the store is never under-quoted). The customer sends the
 * funds and submits the transaction hash; authorised staff verify it on the
 * blockchain explorer before the order is confirmed. Funds go straight to the
 * store's wallet — the site never holds keys.
 */
final class CryptoTransferProvider extends BaseProvider implements ManualPayment
{
    public const METHODS = [
        'usdt_trc20' => ['label' => 'USDT (TRC20)', 'asset' => 'USDT', 'network' => 'TRON (TRC20)', 'decimals' => 2, 'badge' => 'TRC20'],
        'usdt_erc20' => ['label' => 'USDT (ERC20)', 'asset' => 'USDT', 'network' => 'Ethereum (ERC20)', 'decimals' => 2, 'badge' => 'ERC20'],
        'btc' => ['label' => 'Bitcoin (BTC)', 'asset' => 'BTC', 'network' => 'Bitcoin', 'decimals' => 8, 'badge' => 'BTC'],
        'eth' => ['label' => 'Ether (ETH)', 'asset' => 'ETH', 'network' => 'Ethereum', 'decimals' => 8, 'badge' => 'ETH'],
    ];

    private const ADDRESS = [
        'usdt_trc20' => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
        'usdt_erc20' => '/^0x[0-9a-fA-F]{40}$/',
        'eth' => '/^0x[0-9a-fA-F]{40}$/',
        'btc' => '/^(bc1[02-9ac-hj-np-z]{11,71}|[13][1-9A-HJ-NP-Za-km-z]{25,34})$/',
    ];

    private const TXID = [
        'usdt_trc20' => '/^[0-9a-fA-F]{64}$/',
        'usdt_erc20' => '/^0x[0-9a-fA-F]{64}$/',
        'eth' => '/^0x[0-9a-fA-F]{64}$/',
        'btc' => '/^[0-9a-fA-F]{64}$/',
    ];

    private const EXPLORER = [
        'usdt_trc20' => 'https://tronscan.org/#/transaction/',
        'usdt_erc20' => 'https://etherscan.io/tx/',
        'eth' => 'https://etherscan.io/tx/',
        'btc' => 'https://mempool.space/tx/',
    ];

    public function __construct(private readonly string $code)
    {
        if (!isset(self::METHODS[$code])) {
            throw new \InvalidArgumentException("Unknown crypto method {$code}");
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function defaultLabel(): string
    {
        return self::METHODS[$this->code]['label'];
    }

    public function defaultDescription(): string
    {
        $m = self::METHODS[$this->code];
        return $m['asset'] === 'USDT'
            ? "Send USDT on the {$m['network']} network to our wallet, then submit the transaction hash."
            : "Send {$m['asset']} to our wallet (amount at the live rate), then submit the transaction hash.";
    }

    public function asset(): string
    {
        return self::METHODS[$this->code]['asset'];
    }

    public function network(): string
    {
        return self::METHODS[$this->code]['network'];
    }

    public function badge(): string
    {
        return self::METHODS[$this->code]['badge'];
    }

    public function address(): string
    {
        return trim((string) Settings::get('payments.' . $this->code . '.address', ''));
    }

    public static function validAddress(string $code, string $address): bool
    {
        return isset(self::ADDRESS[$code]) && (bool) preg_match(self::ADDRESS[$code], $address);
    }

    /** Wallet address set and valid for the network. */
    public function isConfigured(): bool
    {
        return self::validAddress($this->code, $this->address());
    }

    /** BTC/ETH can only be offered while a fresh price is available. */
    public function availableFor(int $totalMinor, bool $codAllowedByRate): bool
    {
        if ($this->asset() === 'USDT') {
            return true;
        }
        return CryptoPrices::price($this->asset()) !== null;
    }

    public function initialOrderStatus(): string
    {
        return 'awaiting_payment';
    }

    public function initialPaymentStatus(): string
    {
        return 'pending';
    }

    /** Stock is reserved for the payment window, then released if no transaction is submitted. */
    public function reservationExpires(): bool
    {
        return true;
    }

    /**
     * Amount to send, as a decimal string. The order total (store currency) is
     * converted to USDT (1:1 for USD/USDT stores, else via live exchange rates),
     * then to BTC/ETH at the live price — rounded up to the asset's precision.
     *
     * @return array{amount:string, rate:?string}|null
     */
    public function quote(int $totalMinor, string $currency): ?array
    {
        $usdt = self::toUsdt($totalMinor, $currency);
        if ($usdt === null) {
            return null;
        }
        $decimals = self::METHODS[$this->code]['decimals'];
        if ($this->asset() === 'USDT') {
            return ['amount' => self::roundUp($usdt, $decimals), 'rate' => null];
        }
        $price = CryptoPrices::price($this->asset());
        if ($price === null) {
            return null;
        }
        return ['amount' => self::roundUp(bcdiv($usdt, $price, 18), $decimals), 'rate' => $price];
    }

    /** Order total in USDT as a decimal string (null if no conversion is available). */
    public static function toUsdt(int $minor, string $currency): ?string
    {
        $major = bcdiv((string) $minor, (string) Money::factor($currency), 8);
        if (in_array($currency, ['USDT', 'USD'], true)) {
            return $major;
        }
        $rate = \App\Services\ExchangeRates::rate('USD', $currency);
        if ($rate === null && $currency === Currency::base() && \App\Services\ExchangeRates::refresh(true)) {
            $rate = \App\Services\ExchangeRates::rate('USD', $currency);
        }
        return $rate !== null ? bcmul($major, $rate, 8) : null;
    }

    private static function roundUp(string $value, int $decimals): string
    {
        $truncated = bcadd($value, '0', $decimals);
        if (bccomp($truncated, $value, 18) < 0) {
            $truncated = bcadd($truncated, '0.' . str_repeat('0', $decimals - 1) . '1', $decimals);
        }
        return $truncated;
    }

    /** Snapshots the quote on the payment (after the order is committed). */
    public function start(array $order, array $payment): array
    {
        $q = $this->quote((int) $order['total_minor'], (string) $order['currency']);
        Database::get()->exec(
            'UPDATE payments SET pay_asset = ?, pay_network = ?, pay_amount = ?, pay_rate = ?, pay_address = ? WHERE id = ? AND pay_amount IS NULL',
            [$this->asset(), $this->badge(), $q['amount'] ?? null, $q['rate'] ?? null, $this->address(), $payment['id']]
        );
        return ['type' => 'instructions'];
    }

    public function validReference(string $reference): bool
    {
        return (bool) preg_match(self::TXID[$this->code], $reference);
    }

    public function referenceLabel(): string
    {
        return $this->badge() . ' transaction hash (TXID)';
    }

    public function explorerUrl(string $reference): ?string
    {
        return $this->validReference($reference) ? self::EXPLORER[$this->code] . $reference : null;
    }
}
