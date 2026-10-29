# Marketplace (multi-seller)

Other brands can open a shop on URAVEX, list products and earn from sales. URAVEX's own products have no seller (`products.seller_id IS NULL`).

## Money flow

Money only moves one way. Sellers never deposit money, "recharge" a wallet, or pay to fulfil orders.

1. **Checkout.** Each seller line gets a snapshot of its seller, commission and seller share (`order_items.seller_id`, `commission_minor`, `seller_amount_minor`). Commission is a percentage of the line total after discounts, rounded half-up. Customer shipping fees stay with URAVEX.
2. **Payment confirmed** (crypto approved by staff, or COD collected). The seller share is credited as a **pending** `sale` entry in `seller_ledger`. It is credited once per line, enforced by a unique `sale_item_id`.
3. **Order delivered.** Pending entries get `available_at = delivered + hold_days` (default 7 days).
4. **Withdrawal.** The seller requests up to the available balance. Rules:
   - The seller must re-enter their password.
   - Payouts go to their saved USDT TRC20 address.
   - There is a minimum amount, and only one request can be open at a time.
   - The seller row is locked during the request, so parallel requests cannot double-spend.
   
   The amount leaves the balance immediately as a `withdrawal` entry.
5. **Payout.** The owner sends the USDT from the store wallet and records the TXID in Admin → Withdrawals (TXIDs are unique), or rejects the request. A rejection adds a `withdrawal_reversal` entry, which returns the money to the seller's balance.
6. **Refunds** reverse the seller's share:
   - Item refunds reverse that item's share.
   - Amount-only refunds reverse a proportional share.
   - Full refunds reverse everything left.
   
   A reversal stays pending if the sale was still pending. If the seller has already withdrawn the money, the reversal can make their balance negative, which is offset against future sales.

`seller_ledger` is append-only. Balance = `SUM(amount_minor)`: entries whose `available_at` is NULL or in the future are pending, and the rest are available.

## Sellers

- **Applying:** a seller applies at `/sell` (an account is required). Staff with `sellers.manage` (manager, owner) approve or reject the application, and suspend or reinstate sellers later, in Admin → Sellers. Rejecting or suspending requires a reason, and the seller is emailed.
- **Suspension:** a suspended seller's products disappear from the store immediately (the Catalog only shows products from approved sellers). Their items can't be bought, and they can't withdraw.
- **Products:** a new product is a draft until the seller clicks Submit, which puts it in review. Staff then approve it (it goes live) or send it back with a note. If a live product's title, description, category or price changes, it goes back to review. Stock changes don't.
- **Seller panel (`/seller`):**
  - Dashboard with balances.
  - Products, including sizes/stock and photos.
  - Orders: the seller sees only their own lines and the ship-to address, and adds carrier and tracking.
  - Wallet: history and withdrawals.
  - Shop settings: changing the payout address requires the password and sends an email.
- **Isolation:** every seller query is scoped to the seller's own account (tests cover cross-seller access).

## Settings (owner)

Admin → Sellers → Marketplace settings:
- Applications open or closed.
- Commission % (default 10). You can also set a per-seller override on the seller's page.
- Hold days.
- Minimum withdrawal.

Commission is fixed on each order when it is placed.

## Not included yet

- Automatic on-chain payouts: you send each payout from your wallet and record the TXID.
- Seller-to-buyer messaging.
- Per-seller shipping rates.
- Automatic splitting of an order's status per seller: staff still mark the whole order shipped/delivered, and sellers add tracking for their own items.
