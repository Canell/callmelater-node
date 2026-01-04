<template>
  <div class="marketing-page">
    <!-- Hero Section -->
    <section class="hero bg-primary text-white py-5">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-6">
            <h1 class="display-4 fw-bold mb-3">Make things happen later — without surprises</h1>
            <p class="lead mb-4">
              Schedule future actions and confirmations you can actually rely on.
              No cron jobs. No infrastructure to manage.
            </p>
            <div class="d-flex gap-3">
              <router-link to="/register" class="btn btn-light btn-lg">Get Started Free</router-link>
              <a href="https://docs.callmelater.io" class="btn btn-outline-light btn-lg">Documentation</a>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="code-preview bg-dark rounded p-4 mt-4 mt-lg-0">
              <pre class="text-light mb-0"><code>curl https://api.callmelater.io/v1/actions \
  -H "Authorization: Bearer sk_live_..." \
  -d '{
    "type": "http",
    "intent": { "preset": "tomorrow" },
    "http_request": {
      "url": "https://your-app.com/webhook",
      "body": { "event": "trial_expired" }
    }
  }'</code></pre>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Problem Section -->
    <section class="py-5">
      <div class="container">
        <div class="row">
          <div class="col-lg-6 mx-auto text-center">
            <h2 class="h3 mb-4">"Do this later" is where things usually break</h2>
            <p class="text-secondary">
              You plan to call an API in three days, clean up data after a trial ends,
              or remind someone to confirm an action. And then: the server restarts,
              a job fails silently, the reminder is ignored. No one knows what happened.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Solution Section -->
    <section class="py-5 bg-light">
      <div class="container">
        <h2 class="text-center mb-5">CallMeLater makes time-based work dependable</h2>
        <div class="row g-4">
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="feature-icon bg-primary bg-opacity-10 text-primary rounded-circle mb-3 mx-auto" style="width: 60px; height: 60px; line-height: 60px;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                  </svg>
                </div>
                <h5>Keeps Trying</h5>
                <p class="text-secondary small">Automatic retries with exponential backoff when things fail</p>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="feature-icon bg-primary bg-opacity-10 text-primary rounded-circle mb-3 mx-auto" style="width: 60px; height: 60px; line-height: 60px;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                  </svg>
                </div>
                <h5>Durable</h5>
                <p class="text-secondary small">Remembers even if your systems restart</p>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="feature-icon bg-primary bg-opacity-10 text-primary rounded-circle mb-3 mx-auto" style="width: 60px; height: 60px; line-height: 60px;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                    <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                  </svg>
                </div>
                <h5>Transparent</h5>
                <p class="text-secondary small">See exactly what happened — every attempt, every response</p>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="feature-icon bg-primary bg-opacity-10 text-primary rounded-circle mb-3 mx-auto" style="width: 60px; height: 60px; line-height: 60px;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5V2zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1H4z"/>
                  </svg>
                </div>
                <h5>Never Silent</h5>
                <p class="text-secondary small">Never disappears — you always know the state</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
      <div class="container">
        <div class="row align-items-center mb-5">
          <div class="col-lg-6">
            <h2 class="mb-3">Automated HTTP Calls</h2>
            <p class="text-secondary">
              Trigger an API call minutes, days, or months from now.
              Send your own data, get clear success or failure signals, see every attempt.
            </p>
            <ul class="list-unstyled">
              <li class="mb-2">✓ Trial expirations</li>
              <li class="mb-2">✓ Follow-up workflows</li>
              <li class="mb-2">✓ Delayed notifications</li>
              <li class="mb-2">✓ Clean-up jobs</li>
            </ul>
          </div>
          <div class="col-lg-6">
            <div class="bg-light rounded p-4">
              <pre class="mb-0"><code>{
  "type": "http",
  "intent": { "delay": "14d" },
  "http_request": {
    "method": "POST",
    "url": "https://api.example.com/webhook",
    "body": {
      "event": "trial_expired",
      "user_id": 42
    }
  },
  "max_attempts": 5,
  "retry_strategy": "exponential"
}</code></pre>
            </div>
          </div>
        </div>

        <div class="row align-items-center flex-lg-row-reverse">
          <div class="col-lg-6">
            <h2 class="mb-3">Human Confirmations</h2>
            <p class="text-secondary">
              Sometimes a person needs to decide first. Send a reminder with clear options:
              Yes, No, or Snooze. Recipients respond with one click — no account needed.
            </p>
            <ul class="list-unstyled">
              <li class="mb-2">✓ Email and SMS delivery</li>
              <li class="mb-2">✓ One-click responses</li>
              <li class="mb-2">✓ Automatic escalation</li>
              <li class="mb-2">✓ Snooze support</li>
            </ul>
          </div>
          <div class="col-lg-6">
            <div class="bg-light rounded p-4">
              <pre class="mb-0"><code>{
  "type": "reminder",
  "intent": { "preset": "tomorrow" },
  "reminder_message": "Approve the deployment?",
  "recipients": [
    "ops@example.com",
    "+1234567890"
  ],
  "confirmation_mode": "any",
  "max_snoozes": 3
}</code></pre>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Security Section -->
    <section class="py-5 bg-light">
      <div class="container">
        <h2 class="text-center mb-5">Built for Reliability</h2>
        <div class="row g-4">
          <div class="col-md-4">
            <h5>Webhook Signatures</h5>
            <p class="text-secondary small">
              Every request is signed with HMAC-SHA256. Verify authenticity on your end.
            </p>
          </div>
          <div class="col-md-4">
            <h5>Idempotency Built-in</h5>
            <p class="text-secondary small">
              Use idempotency keys to prevent duplicate actions. Cancel by key anytime.
            </p>
          </div>
          <div class="col-md-4">
            <h5>SSRF Protection</h5>
            <p class="text-secondary small">
              Requests to private IPs and internal networks are blocked automatically.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
      <div class="container text-center">
        <h2 class="mb-3">Get started in under a minute</h2>
        <p class="lead mb-4">Send your first delayed webhook in 60 seconds.</p>
        <div class="d-flex justify-content-center gap-3">
          <router-link to="/register" class="btn btn-light btn-lg">Start Free</router-link>
          <router-link to="/pricing" class="btn btn-outline-light btn-lg">View Pricing</router-link>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-white-50">
      <div class="container">
        <div class="row">
          <div class="col-md-4">
            <h5 class="text-white">CallMeLater</h5>
            <p class="small">Reliable scheduled actions for developers.</p>
          </div>
          <div class="col-md-4">
            <h6 class="text-white">Product</h6>
            <ul class="list-unstyled small">
              <li><router-link to="/pricing" class="text-white-50">Pricing</router-link></li>
              <li><a href="https://docs.callmelater.io" class="text-white-50">Documentation</a></li>
              <li><a href="https://status.callmelater.io" class="text-white-50">Status</a></li>
            </ul>
          </div>
          <div class="col-md-4">
            <h6 class="text-white">Legal</h6>
            <ul class="list-unstyled small">
              <li><a href="/privacy" class="text-white-50">Privacy Policy</a></li>
              <li><a href="/terms" class="text-white-50">Terms of Service</a></li>
            </ul>
          </div>
        </div>
        <hr class="my-4 border-secondary">
        <p class="small mb-0 text-center">&copy; {{ new Date().getFullYear() }} CallMeLater. All rights reserved.</p>
      </div>
    </footer>
  </div>
</template>

<script setup>
// Marketing homepage - no logic needed
</script>

<style scoped>
.marketing-page {
  margin-top: -1rem;
}

.hero {
  padding-top: 4rem;
  padding-bottom: 4rem;
}

.code-preview {
  font-size: 0.85rem;
  overflow-x: auto;
}

.code-preview pre {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
}

.feature-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}

pre {
  background: #f8f9fa;
  padding: 1rem;
  border-radius: 0.5rem;
  overflow-x: auto;
  font-size: 0.85rem;
}

code {
  font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
}
</style>
