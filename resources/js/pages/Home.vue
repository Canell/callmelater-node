<template>
  <div class="home-page">
    <!-- Email Verified Success Modal -->
    <div v-if="showVerifiedModal" class="verified-overlay" @click.self="closeVerifiedModal">
      <div class="verified-modal">
        <div class="verified-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
        </div>
        <h3>Email Verified!</h3>
        <p class="text-muted">Your email has been verified successfully. You can now log in to your account.</p>
        <router-link to="/login" class="btn btn-cml-primary btn-lg w-100" @click="closeVerifiedModal">
          Log In to Your Account
        </router-link>
      </div>
    </div>

    <!-- Hero Section -->
    <section class="hero-section">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-6">
            <h1 class="hero-title">
              Schedule future actions<br>
              <span class="text-green">you can actually rely on</span>
            </h1>
            <p class="hero-subtitle">
              Trigger HTTP webhooks or send human confirmations at any time in the future.
              Durable, retryable, and transparent. No cron jobs. No infrastructure.
            </p>
            <div class="hero-cta">
              <router-link to="/register" class="btn btn-cml-primary btn-lg me-3">
                Start Free
              </router-link>
              <a :href="docsUrl" class="btn btn-outline-secondary btn-lg">
                Read the Docs
              </a>
            </div>
            <p class="hero-note">
              Free tier includes 100 actions/month. No credit card required.
            </p>
          </div>
          <div class="col-lg-6">
            <div class="hero-code-wrapper">
              <div class="code-label">Create a scheduled action</div>
              <CodeTabs :examples="createHttpAction" default-tab="curl" />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- How It Works Animation -->
    <section class="animation-section">
      <div class="container">
        <HeroAnimation />
      </div>
    </section>

    <!-- Problem Statement -->
    <section class="section-light">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-8 text-center">
            <h2 class="section-title">"Do this later" is where things break</h2>
            <p class="section-subtitle">
              You need to call an API in three days, clean up data after a trial ends,
              or remind someone to approve a deployment. And then: the server restarts,
              a job fails silently, the reminder is ignored. No one knows what happened.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Grid -->
    <section class="section-white">
      <div class="container">
        <h2 class="section-title text-center mb-5">Built for reliability</h2>
        <div class="row g-4">
          <div class="col-md-6 col-lg-3">
            <div class="feature-card">
              <div class="feature-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
              </div>
              <h5>Automatic Retries</h5>
              <p>Exponential backoff when things fail. Configure attempts and timing to match your needs.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="feature-card">
              <div class="feature-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                </svg>
              </div>
              <h5>Durable Storage</h5>
              <p>Actions survive restarts and crashes. Your scheduled work is never lost.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="feature-card">
              <div class="feature-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
              </div>
              <h5>Full Audit Trail</h5>
              <p>See every attempt, response code, and timing. Debug with confidence.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="feature-card">
              <div class="feature-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
              <h5>Secure by Default</h5>
              <p>HMAC signatures, SSRF protection, and encrypted storage. Ship with confidence.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Two Action Types -->
    <section class="section-light">
      <div class="container">
        <div class="row align-items-center mb-5 pb-4">
          <div class="col-lg-5">
            <span class="feature-label">HTTP Webhooks</span>
            <h3 class="feature-title">Trigger any API at the right time</h3>
            <p class="feature-description">
              Schedule a POST, GET, or any HTTP method to fire at a specific time.
              Perfect for trial expirations, follow-ups, cleanup jobs, or any delayed workflow.
            </p>
            <ul class="feature-list">
              <li>Configurable retry strategy</li>
              <li>Custom headers and body</li>
              <li>Webhook signature verification</li>
              <li>Idempotency key support</li>
            </ul>
          </div>
          <div class="col-lg-7">
            <div class="code-block">
              <pre><code>{
  "type": "http",
  "name": "Expire trial subscription",
  "intent": { "delay": "14d" },
  "http_request": {
    "method": "POST",
    "url": "https://api.example.com/subscriptions/expire",
    "headers": { "X-API-Key": "..." },
    "body": { "user_id": 12345 }
  },
  "max_attempts": 5,
  "retry_strategy": "exponential"
}</code></pre>
            </div>
          </div>
        </div>

        <div class="row align-items-center flex-lg-row-reverse">
          <div class="col-lg-5">
            <span class="feature-label">Human Reminders</span>
            <h3 class="feature-title">Get confirmation before acting</h3>
            <p class="feature-description">
              Sometimes a human needs to approve first. Send reminders via email or SMS
              with one-click Yes/No/Snooze buttons. No account needed to respond.
            </p>
            <ul class="feature-list">
              <li>Email and SMS delivery</li>
              <li>One-click response buttons</li>
              <li>Configurable snooze limits</li>
              <li>Automatic escalation</li>
            </ul>
          </div>
          <div class="col-lg-7">
            <div class="code-block">
              <pre><code>{
  "type": "reminder",
  "name": "Approve production deploy",
  "intent": { "preset": "in_1_hour" },
  "message": "Ready to deploy v2.1 to production?",
  "escalation_rules": {
    "recipients": ["ops@example.com", "+1234567890"],
    "channels": ["email", "sms"]
  },
  "confirmation_mode": "first_response",
  "max_snoozes": 3
}</code></pre>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Integrations / Use Cases -->
    <section class="section-white">
      <div class="container">
        <h2 class="section-title text-center mb-2">Common use cases</h2>
        <p class="section-subtitle text-center mb-5">Replace fragile cron jobs and manual reminders</p>
        <div class="row g-4">
          <div class="col-md-4">
            <div class="use-case-card">
              <h5>Trial Expirations</h5>
              <p>Automatically trigger downgrade logic when free trials end.</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="use-case-card">
              <h5>Follow-up Sequences</h5>
              <p>Send onboarding emails or check-ins at the right intervals.</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="use-case-card">
              <h5>Deployment Approvals</h5>
              <p>Get human sign-off before releases go live.</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="use-case-card">
              <h5>Data Cleanup</h5>
              <p>Schedule deletion of temporary data after retention periods.</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="use-case-card">
              <h5>Invoice Reminders</h5>
              <p>Ping customers about upcoming or overdue payments.</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="use-case-card">
              <h5>Maintenance Windows</h5>
              <p>Trigger system maintenance at off-peak hours.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
      <div class="container text-center">
        <h2 class="cta-title">Ready to make "later" reliable?</h2>
        <p class="cta-subtitle">Start scheduling actions in under a minute. Free tier included.</p>
        <div class="cta-buttons">
          <router-link to="/register" class="btn btn-light btn-lg me-3">
            Get Started Free
          </router-link>
          <router-link to="/pricing" class="btn btn-outline-light btn-lg">
            View Pricing
          </router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CodeTabs from '../components/CodeTabs.vue';
import HeroAnimation from '../components/HeroAnimation.vue';
import { createHttpAction, createReminderAction, httpActionJson, reminderActionJson } from '../data/codeExamples';

const route = useRoute();
const router = useRouter();
const showVerifiedModal = ref(false);

const appDomain = import.meta.env.VITE_APP_DOMAIN || 'callmelater.io';
const docsUrl = `https://docs.${appDomain}`;

onMounted(() => {
  // Check for verified=1 query param
  if (route.query.verified === '1') {
    showVerifiedModal.value = true;
    // Clean up the URL
    router.replace({ path: '/', query: {} });
  }
});

function closeVerifiedModal() {
  showVerifiedModal.value = false;
}
</script>

<style scoped>
/* Verified Modal */
.verified-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
}

.verified-modal {
  background: #fff;
  border-radius: 0.75rem;
  padding: 2.5rem;
  max-width: 400px;
  width: 90%;
  text-align: center;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.verified-icon {
  margin-bottom: 1.5rem;
}

.verified-modal h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  margin-bottom: 0.75rem;
}

.verified-modal p {
  margin-bottom: 1.5rem;
}

/* Animation Section */
.animation-section {
  padding: 2rem 0 3rem;
  background: #fff;
  border-bottom: 1px solid #f3f4f6;
}

/* Hero Section */
.hero-section {
  padding: 4rem 0 5rem;
  background: linear-gradient(180deg, #f9fafb 0%, #fff 100%);
}

.hero-title {
  font-size: 2.75rem;
  font-weight: 700;
  line-height: 1.2;
  color: #111827;
  margin-bottom: 1.5rem;
}

.text-green {
  color: #22C55E;
}

.hero-subtitle {
  font-size: 1.125rem;
  color: #6b7280;
  line-height: 1.7;
  margin-bottom: 2rem;
}

.hero-cta {
  margin-bottom: 1rem;
}

.hero-note {
  font-size: 0.875rem;
  color: #9ca3af;
}

/* Hero Code */
.hero-code-wrapper {
  margin-top: 1rem;
}

.code-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.75rem;
}

/* Code Block (for JSON examples) */
.code-block {
  background: #1f2937;
  border-radius: 0.5rem;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.code-block pre {
  margin: 0;
  padding: 1.25rem;
  overflow-x: auto;
}

.code-block code {
  color: #e5e7eb;
  font-family: 'SF Mono', Monaco, 'Cascadia Code', Consolas, monospace;
  font-size: 0.8125rem;
  line-height: 1.6;
}

/* Sections */
.section-light {
  padding: 5rem 0;
  background: #f9fafb;
}

.section-white {
  padding: 5rem 0;
  background: #fff;
}

.section-title {
  font-size: 1.875rem;
  font-weight: 700;
  color: #111827;
  margin-bottom: 1rem;
}

.section-subtitle {
  font-size: 1.125rem;
  color: #6b7280;
  max-width: 600px;
  margin: 0 auto;
}

/* Feature Cards */
.feature-card {
  padding: 1.5rem;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  height: 100%;
  transition: box-shadow 0.2s;
}

.feature-card:hover {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.feature-icon {
  width: 48px;
  height: 48px;
  background: #ecfdf5;
  color: #22c55e;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}

.feature-card h5 {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  margin-bottom: 0.5rem;
}

.feature-card p {
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 0;
  line-height: 1.6;
}

/* Feature Sections */
.feature-label {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 600;
  color: #22c55e;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.75rem;
}

.feature-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  margin-bottom: 1rem;
}

.feature-description {
  font-size: 1rem;
  color: #6b7280;
  line-height: 1.7;
  margin-bottom: 1.5rem;
}

.feature-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.feature-list li {
  position: relative;
  padding-left: 1.5rem;
  margin-bottom: 0.5rem;
  font-size: 0.9375rem;
  color: #374151;
}

.feature-list li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: #22c55e;
  font-weight: 600;
}

/* Use Case Cards */
.use-case-card {
  padding: 1.25rem;
  background: #f9fafb;
  border-radius: 0.5rem;
  height: 100%;
}

.use-case-card h5 {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  margin-bottom: 0.5rem;
}

.use-case-card p {
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 0;
}

/* CTA Section */
.cta-section {
  padding: 5rem 0;
  background: #22c55e;
}

.cta-title {
  font-size: 2rem;
  font-weight: 700;
  color: #fff;
  margin-bottom: 1rem;
}

.cta-subtitle {
  font-size: 1.125rem;
  color: rgba(255, 255, 255, 0.9);
  margin-bottom: 2rem;
}

.cta-buttons .btn-light {
  background: #fff;
  color: #22c55e;
  border: none;
}

.cta-buttons .btn-light:hover {
  background: #f9fafb;
}

.cta-buttons .btn-outline-light {
  border-color: rgba(255, 255, 255, 0.5);
}

.cta-buttons .btn-outline-light:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: #fff;
}

/* Responsive */
@media (max-width: 991px) {
  .hero-title {
    font-size: 2rem;
  }

  .code-block {
    margin-top: 2rem;
  }
}
</style>
