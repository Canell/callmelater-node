# CallMeLater — Email Copy (V1)

This document contains **ready-to-use email copy** for all emails introduced by:
- domain verification
- notification opt-in / opt-out

Tone goals:
- clear
- neutral
- respectful
- non-marketing
- legally safe (consent-focused)

---

## 1. Domain Verification Email (Developer)

### Subject
Action paused — verify ownership of {{domain}}

### Body
Hi {{user_name}},

You scheduled actions targeting **{{domain}}**.

To protect users and prevent abuse, CallMeLater requires domain ownership verification when a domain receives a high number of calls.

### What you need to do
Please verify that you control **{{domain}}** using **one of the following methods**.

#### Option 1 — DNS TXT record (recommended)
Add the following TXT record to your domain:

```
callmelater-verification={{verification_token}}
```

#### Option 2 — Verification file
Create a file at:

```
https://{{domain}}/.well-known/callmelater.txt
```

With this exact content:

```
callmelater-verification={{verification_token}}
```

Once done, click the button below.

👉 **Verify domain**

Your paused actions will resume automatically after verification.

If you have questions, reply to this email — we’re happy to help.

—  
CallMeLater

---

## 2. Domain Verification Success Email

### Subject
Domain verified — actions resumed for {{domain}}

### Body
Hi {{user_name}},

Your domain **{{domain}}** has been successfully verified.

All paused actions targeting this domain have now resumed.

No further action is required.

—  
CallMeLater

---

## 3. Reminder Email — Opt-In Request (Recipient)

### Subject
You’ve been invited to receive reminders

### Body
Hi,

{{sender_name}} has scheduled reminders that would be sent to **{{recipient_email}}**.

To respect your preferences, CallMeLater will only send reminders if you explicitly agree.

### What would you receive?
Short reminder emails related to tasks or approvals requested by {{sender_name}}.

### Your choice
👉 **Accept reminders**  
👉 **Decline**

If you decline, you won’t receive any reminders.

You can change your preference at any time.

—  
CallMeLater  
This request was sent on behalf of {{sender_name}}.

---

## 4. Reminder Email (Opted-In Recipient)

### Subject
Reminder — {{reminder_title}}

### Body
Hi,

This is a reminder from {{sender_name}}:

> {{reminder_message}}

### Your options
👉 **Yes**  
👉 **No**  
👉 **Snooze**

You can respond with one click — no login required.

—  
CallMeLater

---

## 5. Reminder Escalation Email

### Subject
Reminder pending — escalation notice

### Body
Hi,

This is a follow-up reminder regarding:

> {{reminder_message}}

We haven’t received a response yet.

Please choose an option below.

👉 **Yes**  
👉 **No**  
👉 **Snooze**

—  
CallMeLater

---

## 6. Unsubscribe / Opt-Out Confirmation Email

### Subject
You’ve unsubscribed from reminders

### Body
Hi,

You’ve successfully unsubscribed from CallMeLater reminder emails.

You will no longer receive action-triggered reminders.

If this was a mistake, you can opt back in at any time by accepting a future invitation.

—  
CallMeLater

---

## 7. Legal & Footer Notes (All Emails)

All reminder-related emails must include:
- sender name
- reason for email
- one-click unsubscribe link
- support contact

System emails (billing, account, security) are not affected by opt-in status.

---

## Final Notes

- No marketing language in reminder emails
- No tracking pixels required
- Copy is intentionally neutral and factual
- One-click actions must use signed, expiring URLs

This copy is suitable for v1 launch and compliant with consent-first principles.
