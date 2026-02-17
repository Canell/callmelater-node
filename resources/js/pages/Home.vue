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
              Forget cron jobs.<br>
              <span class="text-green">Schedule anything reliably.</span>
            </h1>
            <p class="hero-subtitle">
              Fire webhooks in 14 days. Get human approval before deploying.
              Send reminders to Slack or Teams. One API, zero infrastructure headaches.
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
            <h2 class="section-title">Stop babysitting your scheduled jobs</h2>
            <p class="section-subtitle">
              You've been there: the cron job silently failed, the retry logic has bugs,
              and nobody approved that deployment because the reminder got lost.
              CallMeLater handles all of this — so you can ship and sleep peacefully.
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
        <div class="row g-4 mt-2">
          <div class="col-md-6 col-lg-3">
            <div class="feature-card">
              <div class="feature-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
              </div>
              <h5>Coordination Keys</h5>
              <p>Group related actions. Replace old deployments, skip duplicates, or chain workflows.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="feature-card">
              <div class="feature-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <h5>Slack &amp; Teams</h5>
              <p>Send approvals to your team's channels. No context switching, faster responses.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="feature-card">
              <div class="feature-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h5>Timezone Aware</h5>
              <p>Schedule in any timezone. Presets like "tomorrow" or "next Monday" just work.</p>
            </div>
          </div>
          <div class="col-md-6 col-lg-3">
            <div class="feature-card">
              <div class="feature-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <h5>Manual Retry</h5>
              <p>Failed actions can be retried with one click. No data loss, no re-creation needed.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Three Superpowers -->
    <section class="section-light">
      <div class="container">
        <!-- Webhooks -->
        <div class="row align-items-center mb-5 pb-4">
          <div class="col-lg-5">
            <span class="feature-label">Scheduled Webhooks</span>
            <h3 class="feature-title">Fire any API call at the perfect moment</h3>
            <p class="feature-description">
              Trial expiring in 14 days? Clean up old data next week?
              Just tell us when and where — we'll handle retries, failures, and logging.
            </p>
            <ul class="feature-list">
              <li>Exponential backoff retries</li>
              <li>HMAC signature verification</li>
              <li>Full request/response logging</li>
              <li>Cancel anytime via API</li>
            </ul>
          </div>
          <div class="col-lg-7">
            <div class="code-block">
              <pre><code>{
  "name": "Expire trial",
  "schedule": { "wait": "14d" },
  "request": {
    "url": "https://api.example.com/trials/expire",
    "body": { "user_id": 12345 }
  },
  "max_attempts": 5
}</code></pre>
            </div>
          </div>
        </div>

        <!-- Human Approval -->
        <div class="row align-items-center flex-lg-row-reverse mb-5 pb-4">
          <div class="col-lg-5">
            <span class="feature-label">Human Approval</span>
            <h3 class="feature-title">Get a Yes before things happen</h3>
            <p class="feature-description">
              Need someone to approve a deployment? Send a reminder to email, SMS, Slack, or Teams.
              One-click buttons. No login required. Snooze if they need more time.
            </p>
            <ul class="feature-list">
              <li>Email, SMS, Slack &amp; Teams</li>
              <li>One-click Yes / No / Snooze</li>
              <li>Auto-escalation if ignored</li>
              <li>Callback webhook on response</li>
            </ul>
          </div>
          <div class="col-lg-7">
            <div class="code-block">
              <pre><code>{
  "mode": "approval",
  "name": "Deploy to production",
  "schedule": { "wait": "30m" },
  "gate": {
    "message": "Ready to deploy v2.1?",
    "recipients": ["channel:slack-deploys"],
    "channels": ["slack"],
    "max_snoozes": 3
  }
}</code></pre>
            </div>
          </div>
        </div>

        <!-- Workflows -->
        <div class="row align-items-center">
          <div class="col-lg-5">
            <span class="feature-label">Multi-Step Workflows</span>
            <h3 class="feature-title">Chain actions together</h3>
            <p class="feature-description">
              Complex processes need multiple steps: call an API, wait for approval,
              then trigger another webhook. Workflows let you orchestrate it all.
            </p>
            <ul class="feature-list">
              <li>Mix webhooks, approvals &amp; delays</li>
              <li>Pass data between steps</li>
              <li>Conditional execution</li>
              <li>Visual progress tracking</li>
            </ul>
          </div>
          <div class="col-lg-7">
            <div class="code-block">
              <pre><code>{
  "name": "User onboarding",
  "steps": [
    { "type": "webhook", "url": "https://api.example.com/users" },
    { "type": "approval", "message": "Approve new user?" },
    { "type": "wait", "delay": "1h" },
    { "type": "webhook", "url": "https://api.example.com/welcome" }
  ]
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
        <h2 class="cta-title">Ship it. Sleep peacefully.</h2>
        <p class="cta-subtitle">100 actions/month free. No credit card. Takes 30 seconds to start.</p>
        <div class="cta-buttons">
          <router-link to="/register" class="btn btn-light btn-lg me-3">
            Start Free
          </router-link>
          <a :href="docsUrl" class="btn btn-outline-light btn-lg">
            Read the Docs
          </a>
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
