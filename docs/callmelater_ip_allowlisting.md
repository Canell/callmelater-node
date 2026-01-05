# CallMeLater — Firewall & IP Allowlisting Guidance

## Purpose

This document explains **how and when to inform users about IP allowlisting** for CallMeLater webhooks.

IP allowlisting is an **integration detail**, not a marketing feature.  
It must be communicated clearly, calmly, and only where it is relevant.

---

## Guiding Principle

> **IP allowlisting is optional and context-dependent.**

Some users will need it.  
Many will not.

The goal is to:
- make information easy to find
- avoid unnecessary friction
- build trust with clear operational guidance

---

## Where to Communicate IP Allowlisting

### 1. Documentation (Primary Location)

IP allowlisting details should live in the documentation under sections such as:
- “Receiving webhooks securely”
- “Firewall & network configuration”

The documentation must clearly state:
- outbound IP addresses used by CallMeLater
- whether these IPs are static
- how often they change (if applicable)
- what to do if the user uses a firewall or private network

This is table-stakes for developer-facing infrastructure products.

---

### 2. Webhook Creation UI (Contextual Notice)

When a user creates their **first webhook**, display a small, non-blocking notice:

> **Firewall configuration**  
> If your endpoint is protected by a firewall, make sure to allow incoming requests from CallMeLater’s outbound IP addresses.

Include:
- a link to the relevant documentation
- a copy button for IP addresses

This ensures the information is shown **only when relevant**.

---

### 3. Delivery Failure Hints (Supportive)

When a webhook delivery fails with network-related errors (e.g. timeout, connection refused), surface a hint such as:

> “This failure may be caused by firewall rules or network restrictions. See IP allowlisting documentation.”

This helps users self-diagnose issues without opening support tickets.

---

## Where NOT to Communicate This

IP allowlisting should **not** be mentioned on:

- Homepage
- Hero section
- Pricing page
- Marketing copy

Why:
- it adds unnecessary complexity
- it intimidates non-expert users
- it distracts from the product’s core value

This information belongs to implementation, not discovery.

---

## How to Phrase It (Tone Matters)

### Recommended phrasing

> “If your endpoint is protected by a firewall, you may need to allow incoming requests from CallMeLater’s outbound IP addresses.”

This wording is:
- optional
- calm
- factual

---

### Phrasing to avoid

- “You must open your firewall”
- “Webhooks will fail unless you allow our IPs”
- “Firewall configuration is required”

These sound alarming and are often untrue.

---

## What to Provide in Documentation

The documentation should include:

- ✅ List of outbound IP addresses
- ✅ Confirmation whether IPs are static
- ✅ Region-specific notes (if applicable)
- ✅ Recommended alternative: HMAC webhook signing
- ✅ Clear statement:

> “If you do not use IP allowlisting, no firewall configuration is required.”

That sentence alone reduces confusion significantly.

---

## Security Positioning

Many teams prefer **HMAC signature verification** over IP allowlisting.

CallMeLater should be positioned as:

> “Supporting both IP allowlisting and webhook signing.”

Not:
> “Requiring both.”

This aligns with modern security practices and developer expectations.

---

## Final Recommendation

✔️ Document IP allowlisting clearly  
✔️ Surface it contextually in the UI  
✔️ Keep the tone optional and calm  
✔️ Pair it with HMAC signing  
❌ Do not expose it in marketing pages  

Handled correctly, this guidance **builds trust without adding friction**.
