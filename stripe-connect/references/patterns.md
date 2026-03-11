# Stripe Connect — Detailed Patterns & Code Examples

Source project: `triple-base-system` (Laravel 12, `stancl/tenancy`, separate DB per tenant)

## File Locations

| File | Purpose |
|------|---------|
| `app/Tenants/Support/Payments/StripeConnectGatewayService.php` | Core service: createPaymentIntent, handleWebhook, processRefund, downloadReceipt |
| `app/Tenants/Front/Registrations/Services/CheckoutPaymentService.php` | Orchestrates intent creation, draft order, gateway key resolution |
| `app/Platform/Http/Controllers/StripeConnectWebhookController.php` | Platform webhook controller — signature verify, tenant routing |
| `app/Jobs/Payments/RetrieveGatewayFee.php` | Queued job: retrieves balance_transaction fee with 3 retries |
| `resources/js/front-checkout.js` | Alpine.js checkout form with Stripe Elements + polling |
| `resources/js/front-donation.js` | Same pattern for donation flow |

---

## Full Webhook Controller Pattern

```php
// app/Platform/Http/Controllers/StripeConnectWebhookController.php
public function __invoke(Request $request): JsonResponse
{
    $webhookSecret = systemConfig('payment_gateways_settings.stripe_connect_webhook_secret');

    $event = Webhook::constructEvent(
        $request->getContent(),
        (string) $request->header('Stripe-Signature', ''),
        $webhookSecret
    );

    $accountId = $event->account ?? null;
    if (!$accountId) {
        return response()->json([], 200); // platform-level event, ignore
    }

    $tenantEvent = TenantEvent::where('stripe_connect_account_id', $accountId)->first();
    $tenant = Tenant::find($tenantEvent->tenant_id);

    tenancy()->initialize($tenant);
    try {
        $this->factory->make(PaymentGateway::StripeConnect)->handleWebhook($request);
    } finally {
        tenancy()->end(); // ALWAYS end tenancy
    }

    return response()->json([], 200);
}
```

---

## Webhook Event Routing (inside tenant context)

```php
// StripeConnectGatewayService::handleWebhook()
match ($eventType) {
    'payment_intent.processing'      => $this->handlePaymentIntentProcessing($order),
    'payment_intent.succeeded'       => $this->handlePaymentIntentSucceeded($order, $object),
    'payment_intent.payment_failed'  => $this->handlePaymentIntentFailed($order),
    'charge.updated'                 => $this->handleChargeUpdated($order, $object),
    // Refund events handled separately before this match:
    // 'charge.refunded', 'refund.created', 'refund.updated' → handleRefundWebhook()
    default => null,
};
```

For `charge.updated`, extract `payment_intent` field (not `id`) as the payment intent ID:
```php
$paymentIntentId = $eventType === 'charge.updated' ? $object['payment_intent'] : $object['id'];
```

---

## RetrieveGatewayFee Job

```php
class RetrieveGatewayFee implements ShouldQueue
{
    public int $tries = 3;

    public function handle(): void
    {
        // Get connected account ID for direct charges
        $requestOptions = [];
        if ($paymentSettings->paymentGateway() === PaymentGateway::StripeConnect) {
            $connectedAccountId = $paymentSettings->stripeConnectAccountId();
            if ($connectedAccountId) {
                $requestOptions = ['stripe_account' => $connectedAccountId];
            }
        }

        $balanceTransaction = $stripe->balanceTransactions->retrieve(
            $this->balanceTransactionId,
            null,
            $requestOptions
        );

        if (!$balanceTransaction || !isset($balanceTransaction->fee)) {
            if ($this->attempts() >= $this->tries) {
                Log::error('Max attempts reached');
                return;
            }
            $this->release(10); // retry after 10 seconds
            return;
        }

        $gatewayFee = (float) ($balanceTransaction->fee / 100); // cents to dollars
        $order->payment_meta = array_merge($paymentMeta, ['gateway_fee' => $gatewayFee]);
        $order->saveOrFail();
    }
}
```

---

## Frontend: Complete Stripe Instance Cache + Init

```javascript
// resources/js/front-checkout.js
const stripePromiseCache = new Map();

async function getStripeInstance(publishableKey, stripeAccount = null) {
    if (!publishableKey) return null;
    const cacheKey = stripeAccount ? `${publishableKey}|${stripeAccount}` : publishableKey;
    if (!stripePromiseCache.has(cacheKey)) {
        const options = stripeAccount ? { stripeAccount } : {};
        stripePromiseCache.set(cacheKey, loadStripe(publishableKey, options));
    }
    return stripePromiseCache.get(cacheKey);
}

// After receiving payment intent response:
const { publishable_key, client_secret, stripe_connect_account_id } = responseData;
this.stripe = await getStripeInstance(publishableKey, stripeConnectAccountId ?? null);

this.elements = this.stripe.elements({ clientSecret });
this.paymentElement = this.elements.create('payment');
this.paymentElement.mount(this.$refs.paymentElement);
```

---

## Refund: Full Three-Step Lookup

```php
// Step 1: by Stripe refund ID stored on our refund record
$refund = Refund::query()->where('payment_refund_id', $refundId)->first();

// Step 2: by our internal refund ID stored in Stripe's metadata
if (!$refund && isset($metadata['refund_id'])) {
    $refund = Refund::query()->find((int) $metadata['refund_id']);
}

// Step 3: by order + amount + status (most fragile, only if above fail)
if (!$refund && isset($metadata['order_id'])) {
    $order = Order::query()->find((int) $metadata['order_id']);
    if ($order && $amount) {
        $refund = Refund::query()
            ->where('order_id', $order->id)
            ->where('amount', $amount)
            ->where('status', RefundStatus::Processing)
            ->latest()
            ->first();
    }
}
```

---

## Refund Creation (with stripe_account)

```php
$requestOptions = ['stripe_account' => $connectedAccountId];

$paymentIntent = $stripe->paymentIntents->retrieve(
    $order->payment_intent_id,
    ['expand' => ['charges']],
    $requestOptions  // must pass stripe_account
);

// Try charges from expanded intent first; fall back to charges->all()
$charges = $paymentIntent->charges->data ?? [];
if (empty($charges)) {
    $chargesList = $stripe->charges->all(
        ['payment_intent' => $order->payment_intent_id, 'limit' => 1],
        $requestOptions
    );
    $charges = $chargesList->data ?? [];
}

$stripeRefund = $stripe->refunds->create([
    'charge' => $charge->id,
    'amount' => $refund->amount,
    'metadata' => [
        'order_id'      => (string) $order->id,
        'refund_id'     => (string) $refund->id,  // enables step-2 lookup above
        'refund_number' => $refund->refund_number,
    ],
], $requestOptions);
```

---

## Configuration Keys

```php
// Platform config (central DB, Configuration model)
systemConfig('payment_gateways_settings.stripe_connect_client_secret')
systemConfig('payment_gateways_settings.stripe_connect_publishable_key')
systemConfig('payment_gateways_settings.stripe_connect_webhook_secret')

// Per-event connected account (tenant_events table, central DB)
$tenantEvent->stripe_connect_account_id

// Tenant-side (read from PaymentSettings entity)
$paymentSettings->stripeConnectAccountId()
$paymentSettings->secretKey()
$paymentSettings->publishableKey()
```

---

## Order Status Polling (Frontend)

After `stripe.confirmPayment()` succeeds client-side, close the payment sheet and poll the backend:

```javascript
// Poll every 5 seconds, max 60 attempts (5 minutes)
this.pollingInterval = setInterval(() => this.checkOrderStatus(), 5000);

// Backend order status endpoint returns:
// { is_completed: bool, is_failed: bool, is_processing: bool, redirect_url: string|null }
```

This avoids race conditions where the webhook hasn't fired yet.

---

## Test Patterns (StripeConnectWebhookTest.php)

- Construct webhook payloads manually with `account` field set to connected account ID
- Use `Webhook::constructEvent` bypass (mock or use test webhook secret)
- Seed `TenantEvent::stripe_connect_account_id` to match the payload's `account`
- Test that missing `account` field returns 200 without processing
- Test idempotency: send same `payment_intent.succeeded` twice, verify order only processes once
- Test refund three-step lookup by varying which metadata fields are present
