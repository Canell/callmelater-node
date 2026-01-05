# CallMeLater — Domain Verification & Notification Opt‑In Design (V1)

## Scope

This document defines two **user‑safety and abuse‑prevention flows** to be included in **v1**:

1. **Domain Ownership Verification** (DNS / file based)
2. **Explicit Email Notification Opt‑In / Opt‑Out**

These flows are designed to:
- reduce spam and abuse
- protect third‑party domains and end‑users
- preserve a low‑friction onboarding for developers
- align with CallMeLater’s reliability positioning

---

## Part 1 — Domain Ownership Verification

### 1.1 Problem Being Solved

Without domain verification, users could:
- send repeated webhooks to domains they do not control
- generate spam or abuse complaints
- harm CallMeLater’s reputation

This is **not an SSRF problem** (handled separately).  
This is an **abuse / trust / reputation** problem.

---

## 1.2 When Verification Is Required (V1 Policy)

Verification is **not required by default**.

A domain becomes **verification‑required** when **any one** of the following thresholds is crossed:

- > **10 executions per day** to the same domain
- > **100 executions per month** to the same domain
- Any **SMS or Email reminder** targeting that domain

Below these thresholds, execution is allowed without verification.

This keeps:
- onboarding friction low
- casual use simple
- abuse surface limited

### Enforcement Timing

✅ **Thresholds are checked at action creation time** (preemptive).

This means:
- developers know immediately if verification is required
- no surprises at execution time
- better UX and predictability

### Subdomain Handling

Each subdomain requires **separate verification**.

- Verifying `api.example.com` does NOT verify `example.com`
- Verifying `example.com` does NOT verify `*.example.com`

This prevents subdomain takeover scenarios and ensures explicit control.

---

## 1.3 Verification Methods (Both Supported)

Users may verify a domain using **either** method.

### Option A — DNS TXT Record (Recommended)

User adds a TXT record:

```
callmelater-verification=<random_token>
```

Example:
```
callmelater-verification=clm_4f92a1d8c0
```

### Option B — HTTP File Verification

User hosts a file at:

```
https://example.com/.well-known/callmelater.txt
```

File contents:
```
callmelater-verification=clm_4f92a1d8c0
```

---

## 1.4 Verification Flow (Step by Step)

1. User schedules an action exceeding threshold
2. Execution is **paused**
3. API returns:
   ```json
   {
     "error": "domain_verification_required",
     "domain": "example.com",
     "verification_token": "clm_4f92a1d8c0"
   }
   ```
4. User adds DNS TXT or file
5. User clicks **“Verify domain”**
6. CallMeLater:
   - resolves DNS / fetches file
   - validates token
7. Domain marked as **verified**
8. Queued actions resume automatically

---

## 1.5 Verification Expiry & Renewal

### Expiry Policy

- ✅ **12 months validity** from verification date
- 🟡 **30-day grace period** after expiry
- 🔄 **Easy renewal** — re-verify using same method

### Grace Period Behavior

During the 30-day grace period:
- existing actions continue to execute
- new actions trigger a renewal warning
- after grace period: new actions are blocked until re-verified

### Renewal Flow

1. User receives email 30 days before expiry
2. User receives reminder at 7 days before expiry
3. After expiry: dashboard shows renewal prompt
4. Re-verification uses same token (no new DNS/file change needed if still present)

---

## 1.6 Verification Data Model

### `verified_domains`

| Field | Type | Notes |
|----|----|----|
| id | UUID | |
| user_id | UUID | owner |
| domain | TEXT | normalized (lowercase) |
| verification_token | TEXT | |
| verified_at | TIMESTAMPTZ | nullable |
| expires_at | TIMESTAMPTZ | verified_at + 12 months |
| method | ENUM | dns / file |
| created_at | TIMESTAMPTZ | |

---

## 1.7 Enforcement Rules (Important)

Even **verified domains** are still subject to:

- SSRF IP blocking
- private / loopback IP denial
- self‑target blocking
- rate limits
- quotas

**Verification never bypasses security rules.**

---

## Part 2 — Email Notification Opt‑In / Opt‑Out

### 2.1 Problem Being Solved

Human reminders and notifications:
- involve real people
- may reach third parties
- must respect consent expectations (GDPR / anti‑spam)

Explicit opt‑in is required.

---

## 2.2 Notification Types

CallMeLater distinguishes between:

- **System emails** (account, billing, alerts) → always allowed
- **Action‑triggered notifications** (reminders, escalations) → opt‑in required

This section covers **action‑triggered notifications only**.

---

## 2.3 Opt‑In Model

### Default Behavior (V1)

- **No reminder emails are sent unless explicitly opted‑in**
- Each recipient must confirm independently
- **No first-party trust** — even emails matching user's domain require separate opt-in

---

## 2.4 Opt‑In Flow (Recipient)

1. Action schedules a reminder email to `user@example.com`
2. Recipient receives **opt‑in request email**:
   > “You have been invited to receive reminders from CallMeLater.”
3. Email contains:
   - **Accept** button
   - **Decline** button
4. Until accepted:
   - no reminders are sent
5. Once accepted:
   - reminders are delivered
   - consent is stored

---

## 2.5 Opt‑Out Flow (Recipient)

Every reminder email includes:
- **Unsubscribe** link

Clicking it:
- immediately stops future reminders
- marks recipient as opted‑out

---

## 2.6 Enforcement Rules

- Reminder is **not sent** if recipient is not opted‑in
- Escalation skips opted‑out recipients
- Dispatcher must check consent before enqueueing email jobs

---

## 2.7 Opt‑In Spam Protection (Layered)

To prevent abuse of the opt-in email system, CallMeLater implements a **multi-layer protection strategy**.

### Layer 1 — Per-Recipient Rate Limiting (Mandatory)

Hard-limit opt-in emails per recipient address, regardless of sender:

| Window | Max Opt-In Emails |
|--------|-------------------|
| 24 hours | 1 |
| 7 days | 3 |
| 30 days | 10 |

When exceeded:
- further opt-in emails are **silently suppressed**
- action creation still succeeds
- sender sees a **warning** (not an error)

Why silent suppression?
- prevents attackers from probing limits
- avoids turning this into a brute-force oracle

### Layer 2 — Global Sender Limits (Mandatory)

Per user / API key:

| Plan | New Recipients / Day | Opt-In Emails / Day |
|------|---------------------|---------------------|
| Free | 5 | 10 |
| Pro | 50 | 100 |
| Business | 200 | 500 |

This stops:
- "spray many emails once" attacks
- scripted abuse

### Layer 3 — Opt-In Deduplication (Very Effective)

If a recipient already has:
- `status = pending` → reuse existing request, do NOT send another email
- `status = opted_out` → block silently, do NOT send

This alone eliminates a huge amount of spam potential.

### Layer 4 — Opt-Out Blacklist (Critical)

If a recipient clicks **Decline** or **Unsubscribe**:
- permanently suppress opt-in emails for that address
- applies **across all senders**
- only clearable by support (or never)

This is essential for trust and legal defensibility.

---

## 2.8 Creation-Time vs Send-Time Enforcement

### At Action Creation Time

- ✅ Allow action to be created
- ✅ Mark reminder as `awaiting_consent`
- ❌ Do NOT error — developers didn't do anything wrong

### At Email Send Time

Before sending opt-in email, check:
1. Recipient rate limits
2. Consent status
3. Suppression list

If blocked:
- Do not send
- Record suppression reason
- Show warning in dashboard

---

## 2.9 Consent Data Model

### `notification_consents`

| Field | Type | Notes |
|-------|------|-------|
| id | UUID | |
| email | TEXT | normalized (lowercase) |
| status | ENUM | pending / opted_in / opted_out |
| consented_at | TIMESTAMPTZ | nullable |
| revoked_at | TIMESTAMPTZ | nullable |
| source | ENUM | reminder / escalation |
| last_optin_sent_at | TIMESTAMPTZ | for rate limiting |
| optin_send_count_24h | INT | approximate counter |
| optin_send_count_7d | INT | approximate counter |
| optin_send_count_30d | INT | approximate counter |
| suppressed | BOOLEAN | permanent suppression |
| suppression_reason | TEXT | nullable |
| created_at | TIMESTAMPTZ | |

Note: Counters can be approximate — perfect accuracy not required.

---

## 2.10 UX / API Behavior

### What the Sender Sees

- Action created successfully
- Dashboard warning (if applicable):
  > "Opt-in email not sent — recipient was contacted recently."
- No error, no drama

### What the Recipient Sees

- At most **one** opt-in email in a reasonable window
- Clear **Accept** / **Decline** options
- No repeated harassment

### What NOT to Do

❌ Do NOT:
- require CAPTCHA on API usage
- expose opt-in limits in API responses
- retry opt-in emails aggressively
- allow different senders to bypass suppression

---

## Part 3 — UX & API Considerations

### Clear Developer Feedback
API responses must explicitly say:
- why an action is paused
- how to fix it
- next steps

No silent failures.

---

### Admin Visibility
Admin dashboard should show:
- unverified domains
- paused actions due to verification
- opt‑in vs opt‑out ratios

---

## Final Assessment

These flows:
- add **strong abuse protection**
- preserve developer ergonomics
- align with GDPR and consent expectations
- are feasible and scoped for v1

The threshold‑based verification approach is **the right balance** between:
- safety
- usability
- growth

---

## V1 Launch Checklist

### Domain Verification
- [ ] Domain verification enforcement at creation time
- [ ] DNS TXT + HTTP file verification support
- [ ] Subdomain-level verification (no wildcards)
- [ ] 12-month expiry with 30-day grace period
- [ ] Expiry reminder emails (30 days, 7 days before)

### Opt-In System
- [ ] Explicit opt‑in for reminder emails
- [ ] No first-party trust (all recipients require opt-in)
- [ ] Per-recipient rate limiting (1/24h, 3/7d, 10/30d)
- [ ] Global sender limits by plan
- [ ] Opt-in deduplication (reuse pending requests)
- [ ] Permanent opt-out blacklist across all senders
- [ ] Silent suppression (no errors, dashboard warnings only)

### Common
- [ ] Unsubscribe links in all emails
- [ ] Clear API error messages
- [ ] Admin visibility of blocked / paused actions
- [ ] Dashboard warnings for suppressed emails

Once implemented, CallMeLater is protected on both:
- **infrastructure safety**
- **user trust & consent**
- **spam / abuse prevention**
