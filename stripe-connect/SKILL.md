---
name: stripe-connect
description: Implements Stripe Connect direct charges for multi-tenant platforms. Use when adding Stripe Connect payment processing, implementing connected account payments, handling webhooks for connected accounts, or processing refunds on connected accounts. Key triggers: "stripe connect", "connected account", "direct charges", "stripe_account".
metadata:
  author: youxingxiang
  version: 1.0.0
  category: payments
---

# Stripe Connect Direct Charges — Implementation Guide

This skill captures production-tested patterns from a Laravel multi-tenant SaaS. Follow all patterns below before writing any code.

## 1. Architecture: Always Use Direct Charges

- PaymentIntents are created **ON** the connected account, not the platform account
- Use platform secret key + `stripe_account` option on **every** API call to that account
- Platform stays out of money flow; funds go directly to the connected account
- No `transfer_data` or `application_fee_amount` — pure direct charges

## 2. Backend: Creating PaymentIntents

```php
$stripe = new StripeClient($platformSecretKey); // platform key, not connected account key
$intent = $stripe->paymentIntents->create(
    [
        'amount'       => $amount,
        'currency'     => $currency,
        'description'  => $description,
        'metadata'     => ['order_id' => $orderId, ...],
        'automatic_payment_methods' => ['enabled' => true],
    ],
    [
        'idempotency_key' => $idempotencyKey,
        'stripe_account'  => $connectedAccountId,  // REQUIRED
    ]
);
// Return to frontend:
return [
    'publishable_key'           => $publishableKey,
    'client_secret'             => $intent->client_secret,
    'payment_intent_id'         => $intent->id,
    'stripe_connect_account_id' => $connectedAccountId,
];
```

## 3. Frontend: Load Stripe with stripeAccount (CRITICAL)

Without `{ stripeAccount }`, card payments fail silently — no error, just no card field.

```javascript
// Cache per `${publishableKey}|${stripeAccount}` to handle multiple accounts
const stripePromiseCache = new Map();

async function getStripeInstance(publishableKey, stripeAccount = null) {
    const cacheKey = stripeAccount ? `${publishableKey}|${stripeAccount}` : publishableKey;
    if (!stripePromiseCache.has(cacheKey)) {
        const options = stripeAccount ? { stripeAccount } : {};
        stripePromiseCache.set(cacheKey, loadStripe(publishableKey, options));
    }
    return stripePromiseCache.get(cacheKey);
}

// Usage — stripeConnectAccountId comes from the payment intent response:
this.stripe = await getStripeInstance(publishableKey, stripeConnectAccountId ?? null);
```

## 4. Webhook: Platform-Level Routing

- Register ONE webhook on the central/platform domain
- Verify with the **platform** webhook secret (not a connected account secret)
- `$event->account` is set for connected-account events; absent for platform events
- If `$event->account` is absent → return 200 silently (correct for direct charges)
- Route to tenant: `TenantEvent::where('stripe_connect_account_id', $accountId)->first()`
- Initialize tenancy, handle, then always call `tenancy()->end()` in a `finally` block

```php
$accountId = $event->account ?? null;
if (!$accountId) {
    return response()->json([], 200); // platform event, not a connected-account event
}
$tenantEvent = TenantEvent::where('stripe_connect_account_id', $accountId)->first();
```

## 5. Every API Call Needs stripe_account

Balance transactions, refunds, receipt downloads — **all** must pass `stripe_account`:

```php
$requestOptions = ['stripe_account' => $connectedAccountId];

// Retrieve balance transaction
$stripe->balanceTransactions->retrieve($btxnId, null, $requestOptions);

// Retrieve payment intent with expanded charges
$stripe->paymentIntents->retrieve($intentId, ['expand' => ['charges']], $requestOptions);

// Create refund
$stripe->refunds->create(['charge' => $chargeId, 'amount' => $amount], $requestOptions);
```

## 6. Balance Transactions Are Async

Never retrieve inline. Use a queued job with retries:

```php
// Dispatch from charge.updated webhook handler:
RetrieveGatewayFee::dispatch($order->id, $balanceTransactionId);

// Job: $tries = 3; release(10) on null/missing fee; give up at max attempts
```

## 7. Refund Lookup: Three-Step Fallback

```php
// 1. By Stripe refund ID
$refund = Refund::where('payment_refund_id', $stripeRefundId)->first();

// 2. By internal refund ID in metadata
if (!$refund && isset($metadata['refund_id'])) {
    $refund = Refund::find((int) $metadata['refund_id']);
}

// 3. By order + amount + Processing status (last resort)
if (!$refund && isset($metadata['order_id'])) {
    $order = Order::find((int) $metadata['order_id']);
    if ($order && $amount) {
        $refund = Refund::where('order_id', $order->id)
            ->where('amount', $amount)
            ->where('status', RefundStatus::Processing)
            ->latest()->first();
    }
}
```

## 8. Idempotency Guard for payment_intent.succeeded

```php
$ignoreStatuses = [OrderStatus::Paid, OrderStatus::PartiallyRefunded,
                   OrderStatus::Refunded, OrderStatus::Cancelled];
if (in_array($order->status, $ignoreStatuses, true)) {
    return; // already in final status, skip
}
// Also verify intent ID matches:
if ($order->payment_intent_id !== $webhookPaymentIntentId) {
    return; // mismatch, skip
}
```

## 9. Configuration Storage

- Platform API keys → `Configuration` model via `Configuration::set($key, $value)` and `systemConfig($key)`
- Connected account ID → `tenant_events.stripe_connect_account_id` column (per-tenant DB)
- Never store Stripe Connect credentials in `.env` or tenant config tables

## 10. Payment Method Resolution (Fallback Chain)

When handling `payment_intent.succeeded`, resolve payment method in this order:
1. `$paymentIntent['charges']['data'][0]['payment_method_details']['type']`
2. `$paymentIntent['payment_method']['type']`
3. `$paymentIntent['payment_method_types'][0]` (only if single element)

Map `'card'` → `PaymentMethod::CreditDebitCards`, `'paynow'` → `PaymentMethod::PayNow`.

## Key Gotchas

| Gotcha | Fix |
|--------|-----|
| Card field doesn't appear | Pass `{ stripeAccount }` to `loadStripe()` |
| Balance transaction 404 | It's async — use queued job with retries |
| Webhook events not routing | Check `$event->account`; absent = platform event |
| Refund 404 on Stripe | Pass `stripe_account` to every refund API call |
| Duplicate order completion | Guard with `payment_intent_id` match + final status check |
| Config not found | Use `Configuration` model, not `.env` |

## Reference Files (this project)

See `references/patterns.md` for full code examples and file paths.

- `app/Tenants/Support/Payments/StripeConnectGatewayService.php` — core service
- `app/Tenants/Front/Registrations/Services/CheckoutPaymentService.php` — intent creation
- `app/Platform/Http/Controllers/StripeConnectWebhookController.php` — webhook routing
- `app/Jobs/Payments/RetrieveGatewayFee.php` — async balance transaction job
- `resources/js/front-checkout.js` — frontend Stripe initialization
