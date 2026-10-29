# Payments

Payment methods are modular adapters implementing `App\Payments\PaymentProvider`, registered in
`App\Payments\Registry`. A method is shown at checkout only when it is **implemented**, **configured**
(credentials/settings present) and **enabled** (Admin → Settings → Payments), and when the order meets its
rules (e.g. COD maximum, courier COD availability). The server re-checks all of this at order placement.

## Live methods: crypto only

The store accepts **USDT (TRC20)**, **USDT (ERC20)**, **Bitcoin** and **Ether** (`App\Payments\CryptoTransferProvider`). All other methods below are still in the code (for historical orders and the test suite) but are **not registered** unless `Registry::$legacy` is true, which only the test bootstrap sets.

- **Setup:** Admin → Settings → Payments: paste a receiving wallet address for each method. Each address is checked against its network format (TRON `T…`, Ethereum `0x…`, Bitcoin `bc1…`/`1…`/`3…`). A method shows at checkout only when it is switched on and has a valid address.
- **Amounts:** USDT is charged 1:1 with the order total (USD/USDT stores; any other currency is converted with the live USD rate). BTC/ETH use the live price (Binance, with CoinGecko as backup; cached for 20 min), rounded **up** to 8 decimals. BTC/ETH are hidden while no fresh price is available. The amount, asset, network, rate and address are saved on the payment when the order is placed (`payments.pay_*`).
- **Flow:** the order waits for payment with stock reserved for `checkout.reservation_minutes`. The customer submits the transaction hash, which is checked against the network format and must not already be used on another order. Orders with a submitted hash are never auto-cancelled. Staff open the order, use the explorer link (Tronscan, Etherscan or mempool.space), check the network, receiving address and amount, then approve (order confirmed, payment marked paid, customer emailed) or reject (the customer can resubmit). Every decision is audited.
- **Not automated:** there is no on-chain watcher; verification is manual. The site never holds private keys.

## Legacy methods (not offered)

| Method | Status | Notes |
|---|---|---|
| Cash on Delivery | ✅ Working | Order is *Confirmed* immediately; payment stays *unpaid* until staff record collection (audited). Optional maximum order value. |
| Bank transfer | ✅ Working | Enable after entering bank name, account title and account number/IBAN. Order is *Awaiting Payment*; customer submits transaction ID + optional receipt (stored privately); authorised staff approve/reject. Unverified orders auto-cancel after N days (configurable). |
| Sandbox test gateway | ✅ Development only | Simulated hosted checkout + HMAC-signed webhook + server-side status lookup. Never available when `APP_ENV=production`. Orders are flagged `is_test` and excluded from analytics. |
| Safepay | ⏳ Not implemented | Requires approved merchant account and API keys. |
| PayFast (Pakistan) | ⏳ Not implemented | Requires approved merchant account. |
| JazzCash | ⏳ Not implemented | Requires merchant account (merchant ID, password, integrity salt). |
| Easypaisa | ⏳ Not implemented | Requires merchant account (store ID, hash key). |
| International cards | ⏳ Not implemented | Use an eligible provider with a merchant account that supports PKR/your settlement currency. |

The "not implemented" providers are listed in the admin with the credentials they will need, but are
never offered to customers and their webhook URLs return 404. **No integration was written from memory or
guesswork** — each must be built against the provider's current official documentation once you have
merchant access.

## Safety rules the pipeline already enforces

- Orders become paid only via: staff COD collection, staff bank-transfer verification, or a provider
  webhook whose **signature is valid** *and* whose status is **confirmed by calling the provider's API**
  (`fetchStatus`). Reaching a success/return URL never marks an order paid (the return page only triggers a
  server-side status lookup).
- Webhook events are stored in `payment_events` with `UNIQUE(provider, provider_event_id)`; replays are
  acknowledged and ignored. Processing failures delete the event row so the provider's retry is processed.
- Amount and currency in the event must equal the payment record, otherwise the event is flagged
  (`amount_mismatch`) and not applied.
- Online payments reserve stock until `reservation_expires_at` (Settings → Checkout); cron cancels and
  restocks unpaid orders after re-checking the provider status. Payments arriving after cancellation are
  flagged for refund.
- Card numbers/CVVs are never collected or stored by this application — cards must be entered on the
  provider's hosted page. Secrets live only in `.env`, never in the browser or logs (the logger redacts keys,
  tokens and card-like numbers).

## Adding a provider (e.g. Safepay)

1. Obtain merchant approval and **sandbox** credentials. Read the provider's official API and webhook
   documentation (payment session creation, redirect/hosted checkout, webhook signature scheme, status
   query endpoint, refund endpoint, test cards).
2. Add credentials to `.env` (`SAFEPAY_*` placeholders already exist) and expose them in `app/Core/Config.php`.
3. Create `app/Payments/SafepayProvider.php` extending `BaseProvider`:
   - `isConfigured()` — all required keys present for the current `PAYMENT_MODE`.
   - `initialOrderStatus()` → `awaiting_payment`; `reservationExpires()` → `true`.
   - `start($order, $payment)` — create a payment session via the official API (server-side, with
     the order number as reference and amount in the provider's expected unit), store the provider's
     session id in `payments.provider_reference`, return `['type' => 'redirect', 'url' => …]`.
   - `parseWebhook($request)` — verify the signature exactly as documented (constant-time comparison,
     timestamp tolerance), return the normalized event or `null`.
   - `fetchStatus($payment)` — query the official status endpoint; map to `paid|pending|failed|cancelled|expired`.
   - `refund()` / `supportsRefunds()` if the API supports refunds.
4. Register it in `Registry::all()` in place of the `UnavailableProvider` entry, add its hosted-page origin
   to `Security::PAYMENT_FORM_ORIGINS` if it uses a form POST, and add an enable toggle in
   `SettingsSchema` (`payments_safepay`) and the Payments settings view.
5. Configure the webhook URL in the provider dashboard: `https://<your-domain>/webhooks/safepay`.
6. Test end-to-end in sandbox: success, decline, cancel, expiry, duplicate webhook, tampered webhook, refund.
   Add integration tests mirroring `tests/IntegrationTest.php` → "Webhooks".
7. Switch `PAYMENT_MODE=live` and live keys only after sandbox sign-off.
