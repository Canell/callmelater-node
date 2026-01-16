# Stripe Setup Guide

This guide explains how to configure Stripe for CallMeLater subscriptions.

---

## 1. Create a Stripe Account

1. Go to [stripe.com](https://stripe.com) and create an account
2. Complete the account verification process
3. For testing, use Stripe's **Test Mode** (toggle in the dashboard header)

---

## 2. Get API Keys

1. Go to **Developers > API keys** in the Stripe Dashboard
2. Copy your keys:
   - **Publishable key** (`pk_test_...` or `pk_live_...`)
   - **Secret key** (`sk_test_...` or `sk_live_...`)

Add them to your `.env` file:

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
```

---

## 3. Create Products and Prices

Go to **Products** in the Stripe Dashboard and create the following:

### Product 1: Pro Plan

1. Click **Add product**
2. Name: `Pro`
3. Description: `For growing projects - 5,000 actions/month`
4. Add two prices:

| Price Name | Amount | Billing | Price ID |
|------------|--------|---------|----------|
| Pro Monthly | €19.00 | Monthly, recurring | Copy this ID |
| Pro Annual | €190.00 | Yearly, recurring | Copy this ID |

### Product 2: Business Plan

1. Click **Add product**
2. Name: `Business`
3. Description: `For teams and scale - 25,000 actions/month`
4. Add two prices:

| Price Name | Amount | Billing | Price ID |
|------------|--------|---------|----------|
| Business Monthly | €79.00 | Monthly, recurring | Copy this ID |
| Business Annual | €790.00 | Yearly, recurring | Copy this ID |

### Add Price IDs to Environment

Copy each price ID (starts with `price_`) and add to your `.env`:

```env
STRIPE_PRICE_PRO_MONTHLY=price_1abc...
STRIPE_PRICE_PRO_ANNUAL=price_2def...
STRIPE_PRICE_BUSINESS_MONTHLY=price_3ghi...
STRIPE_PRICE_BUSINESS_ANNUAL=price_4jkl...
```

---

## 4. Configure Webhooks

Webhooks allow Stripe to notify your application about subscription events.

### Create Webhook Endpoint

1. Go to **Developers > Webhooks**
2. Click **Add endpoint**
3. Enter your endpoint URL:
   - Production: `https://yourdomain.com/stripe/webhook`
   - Local testing: Use [Stripe CLI](#local-testing-with-stripe-cli)
4. Select events to listen to (click "Select events"):
   - `customer.subscription.created` - New subscription started
   - `customer.subscription.updated` - Plan changed, renewed, or status changed
   - `customer.subscription.deleted` - Subscription fully ended
   - `customer.updated` - Customer info changed
   - `invoice.payment_succeeded` - Payment successful
   - `invoice.payment_failed` - Payment failed (card declined, etc.)
   - `invoice.payment_action_required` - 3D Secure or action needed
5. Click **Add endpoint**
6. Copy the **Signing secret** (`whsec_...`)

Add to your `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_...
```

---

## 5. Configure Customer Portal

The Customer Portal allows users to manage their subscriptions.

1. Go to **Settings > Billing > Customer portal**
2. Configure the following:

### Business Information
- Add your business name and support links

### Functionality
Enable these features:
- **Invoices**: Allow customers to view invoice history (note: these are Stripe's standard invoices, see [Invoicing Compliance](#invoicing-compliance-eu) below)
- **Customer information**: Allow updating email and billing address
- **Payment methods**: Allow updating payment methods
- **Subscriptions**: Allow:
  - Switching plans (to upgrade/downgrade)
  - Canceling subscriptions
  - Pausing subscriptions (optional)

### Products
- Enable the Pro and Business products you created
- This allows customers to switch between plans

### Save
Click **Save** to apply changes.

---

## 6. Environment Variables Summary

Here's the complete list of Stripe-related environment variables:

```env
# Stripe API Keys
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Stripe Price IDs
STRIPE_PRICE_PRO_MONTHLY=price_...
STRIPE_PRICE_PRO_ANNUAL=price_...
STRIPE_PRICE_BUSINESS_MONTHLY=price_...
STRIPE_PRICE_BUSINESS_ANNUAL=price_...
```

---

## 7. Local Testing with Stripe CLI

For local development, use the Stripe CLI to forward webhooks:

### Install Stripe CLI

```bash
# macOS
brew install stripe/stripe-cli/stripe

# Linux
# Download from https://github.com/stripe/stripe-cli/releases
```

### Login and Forward Webhooks

```bash
# Login to your Stripe account
stripe login

# Forward webhooks to your local server
stripe listen --forward-to localhost:8000/stripe/webhook
```

The CLI will display a webhook signing secret. Use this for local testing:

```env
STRIPE_WEBHOOK_SECRET=whsec_... (from CLI output)
```

### Trigger Test Events

```bash
# Trigger a test subscription event
stripe trigger customer.subscription.created
```

---

## 8. Test Cards

Use these test card numbers in Test Mode:

| Card Number | Description |
|-------------|-------------|
| `4242 4242 4242 4242` | Successful payment |
| `4000 0000 0000 3220` | 3D Secure authentication required |
| `4000 0000 0000 9995` | Payment declined |

Use any future expiration date, any 3-digit CVC, and any postal code.

---

## 9. Go Live Checklist

Before switching to production:

1. [ ] Complete Stripe account verification
2. [ ] Switch from Test Mode to Live Mode in Stripe Dashboard
3. [ ] Create products and prices in Live Mode (repeat step 3)
4. [ ] Create webhook endpoint for production URL
5. [ ] Update `.env` with live keys and price IDs:
   - `STRIPE_KEY=pk_live_...`
   - `STRIPE_SECRET=sk_live_...`
   - `STRIPE_WEBHOOK_SECRET=whsec_...` (new one for live webhook)
   - Update all `STRIPE_PRICE_*` with live price IDs
6. [ ] Test a real subscription with a small amount
7. [ ] Configure Customer Portal in Live Mode

---

## 10. Invoicing Compliance (EU)

Stripe generates standard PDF invoices, but these are **not** PEPPOL-compliant e-invoices required for B2B transactions in Belgium and other EU countries.

### Options

#### Option 1: Stripe for payments, separate invoicing software

Use Stripe purely for payment collection, then generate compliant invoices through Belgian accounting software:

- **Billit** - Belgian e-invoicing, PEPPOL certified
- **Yuki** - Accounting with PEPPOL support
- **Octopus** - Belgian accounting software
- **ClearFacts** - Document processing with PEPPOL
- **Accountable** - For freelancers/small businesses

Workflow: Stripe webhook → Your app → Accounting software API → PEPPOL invoice

#### Option 2: Stripe integration tools

Third-party tools that sync with Stripe and handle EU tax/invoicing compliance:

- **Octobat** - EU VAT and invoicing compliance for Stripe
- **Quaderno** - Tax automation for SaaS businesses

#### Option 3: B2C only

If your customers are primarily consumers (not businesses), Stripe's standard invoices may be sufficient. PEPPOL requirements mainly apply to B2B transactions.

### Recommendation

For a Belgian SaaS:
1. Use Stripe for payment processing
2. Connect to Belgian accounting software (Billit, Yuki, etc.) for official invoicing
3. Sync subscription data via webhooks to generate compliant invoices

Consult with your accountant for specific requirements.

---

## 11. How Subscription Changes Are Handled

Laravel Cashier automatically processes webhook events and updates your database. Here's what happens for each scenario:

### Customer Cancels Subscription

1. Stripe sends `customer.subscription.updated` webhook
2. Cashier updates `subscriptions` table:
   - Sets `ends_at` to the end of the current billing period
   - Status remains `active` until that date
3. After `ends_at`, the customer is no longer considered "subscribed"
4. Your app's `getPlan()` method returns `free`

### Customer Changes Plan (Upgrade/Downgrade)

1. Stripe sends `customer.subscription.updated` webhook
2. Cashier updates `stripe_price` to the new price ID
3. Your app immediately reflects the new plan limits

### Payment Fails

1. Stripe sends `invoice.payment_failed` webhook
2. Cashier updates `stripe_status` to `past_due`
3. Stripe will retry payment based on your retry settings
4. If all retries fail, subscription is canceled

### Customer Resumes Canceled Subscription

1. If canceled but not yet expired, customer can resume via Customer Portal
2. Stripe sends `customer.subscription.updated` webhook
3. Cashier clears `ends_at`, subscription continues

### Checking Subscription Status in Code

```php
$account = $user->account;

// Is currently subscribed?
$account->subscribed('default');  // true/false

// Is on a specific plan?
$account->subscribedToPrice($proPriceId, 'default');

// Has canceled but still active until period ends?
$account->subscription('default')->canceled();  // true
$account->subscription('default')->onGracePeriod();  // true

// Get current plan name
$account->getPlan();  // 'free', 'pro', or 'business'
```

---

## Troubleshooting

### Webhook Errors

If webhooks fail:
1. Check **Developers > Webhooks > [your endpoint]** for failed events
2. Verify the `STRIPE_WEBHOOK_SECRET` matches the endpoint's signing secret
3. Ensure your server is accessible from the internet (for production)

### Subscription Not Created

If subscriptions don't appear in your database:
1. Check Laravel logs for errors
2. Verify webhook events are being received
3. Ensure the `subscriptions` table migration has run

### Price ID Not Found

If checkout fails with "Invalid plan":
1. Verify price IDs in `.env` match Stripe Dashboard
2. Clear config cache: `php artisan config:clear`
3. Check that prices are set to "Active" in Stripe

---

## Support

- [Stripe Documentation](https://stripe.com/docs)
- [Laravel Cashier Documentation](https://laravel.com/docs/billing)
- [Stripe Support](https://support.stripe.com)
