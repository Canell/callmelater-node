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

    <!-- SDKs & Integrations -->
    <section class="section-light">
      <div class="container">
        <h2 class="section-title text-center mb-2">Integrate in minutes</h2>
        <p class="section-subtitle text-center mb-5">Official SDKs and integrations — or use the REST API from any language</p>
        <div class="row g-4 justify-content-center">
          <div class="col-md-6 col-lg-3">
            <a href="https://github.com/Canell/callmelater-node" target="_blank" rel="noopener" class="integration-card">
              <div class="integration-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 256 289"><path fill="#539E43" d="M128 288.464c-3.975 0-7.685-1.06-11.13-2.915l-35.247-20.936c-5.3-2.915-2.65-3.975-1.06-4.505 7.155-2.385 8.48-2.915 15.9-7.156.796-.53 1.856-.265 2.65.265l27.032 16.166c1.06.53 2.385.53 3.18 0l105.74-61.217c1.06-.53 1.59-1.59 1.59-2.915V83.08c0-1.325-.53-2.385-1.59-2.915l-105.74-60.953c-1.06-.53-2.385-.53-3.18 0L20.405 80.166c-1.06.53-1.59 1.855-1.59 2.915v122.17c0 1.06.53 2.385 1.59 2.915l28.887 16.695c15.636 7.95 25.44-1.325 25.44-10.6V93.68c0-1.59 1.325-3.18 3.18-3.18h13.25c1.59 0 3.18 1.325 3.18 3.18v120.58c0 20.936-11.396 33.126-31.272 33.126-6.095 0-10.865 0-24.38-6.625l-27.826-15.9C4.24 220.885 0 213.73 0 205.78V83.346c0-7.95 4.24-15.37 11.13-19.345L116.87 2.784c6.625-3.71 15.636-3.71 22.26 0L244.87 64c6.89 3.975 11.13 11.13 11.13 19.346V205.78c0 7.95-4.24 15.37-11.13 19.345l-105.74 61.217c-3.18 1.59-6.89 2.12-11.13 2.12zm32.596-84.009c-46.377 0-55.917-21.2-55.917-39.221 0-1.59 1.325-3.18 3.18-3.18h13.515c1.59 0 2.915 1.06 2.915 2.65 2.12 14.045 8.215 20.936 36.307 20.936 22.26 0 31.802-5.035 31.802-16.96 0-6.891-2.65-11.926-37.367-15.372-28.887-2.915-46.907-9.275-46.907-32.33 0-21.467 18.02-34.187 48.232-34.187 33.921 0 50.617 11.66 52.737 37.102 0 .795-.265 1.59-.795 2.385-.53.53-1.325 1.06-2.12 1.06h-13.78c-1.325 0-2.65-1.06-2.915-2.385-3.18-14.575-11.395-19.345-33.126-19.345-24.38 0-27.296 8.48-27.296 14.84 0 7.686 3.445 10.07 36.307 14.31 32.596 4.24 47.967 10.336 47.967 33.127-.265 23.321-19.345 36.572-53.002 36.572z"/></svg>
              </div>
              <h5>Node.js SDK</h5>
              <p>TypeScript, zero dependencies, ESM + CJS</p>
              <code class="integration-install">npm install callmelater</code>
            </a>
          </div>
          <div class="col-md-6 col-lg-3">
            <a href="https://github.com/Canell/callmelater-laravel" target="_blank" rel="noopener" class="integration-card">
              <div class="integration-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 256 264"><path fill="#FF2D20" d="M255.856 59.62c.095.351.144.713.144 1.077v56.568c0 1.478-.79 2.843-2.073 3.578L206.45 148.18v56.2c0 1.478-.79 2.843-2.073 3.578l-95.958 55.398c-.21.121-.436.206-.663.282l-.252.098c-.267.088-.554.13-.838.13-.284 0-.571-.042-.838-.13a2.155 2.155 0 0 1-.252-.098 4.153 4.153 0 0 1-.663-.282L9.622 207.958a4.128 4.128 0 0 1-2.073-3.578V36.057c0-.364.049-.726.144-1.077l.028-.11c.049-.186.114-.371.193-.546.035-.077.07-.153.112-.226.056-.097.112-.19.175-.282.044-.066.094-.127.142-.19.063-.083.133-.16.204-.235.056-.055.108-.113.169-.163.076-.066.16-.126.24-.186.06-.042.114-.09.18-.126L56.685 3.16a4.129 4.129 0 0 1 4.147 0L108.38 30.9c.065.037.12.084.18.126.08.06.163.12.24.186.06.05.112.108.168.163.071.074.14.152.204.235.049.062.098.124.142.19.063.091.12.185.175.282.042.073.077.149.112.226.079.175.144.36.193.546l.028.11c.095.351.144.713.144 1.077v106.2l41.106-23.73V59.62c0-.364.049-.726.144-1.077l.028-.11c.049-.186.114-.371.193-.546.035-.077.07-.153.112-.226.056-.097.112-.19.175-.282.044-.066.094-.127.142-.19.063-.083.133-.16.204-.235.056-.055.108-.113.169-.163.076-.066.16-.126.24-.186.06-.042.114-.09.18-.126l47.548-27.472a4.129 4.129 0 0 1 4.147 0l47.548 27.472c.066.037.12.084.18.126.08.06.164.12.24.186.061.05.113.108.17.163.07.074.14.152.203.235.049.062.098.124.142.19.063.091.12.185.175.282.043.073.077.149.112.226.079.175.144.36.194.546zM247.46 114.165V64.892l-17.269 9.968-23.837 13.762v49.273zm-47.548 81.9V146.79l-23.46 13.43-71.04 40.637v49.898zM12.445 40.259v163.497l91.46 52.818V207.09L56.91 183.07l-.042-.028-.026-.018c-.075-.052-.158-.107-.233-.168-.052-.044-.1-.093-.15-.14l-.018-.018c-.067-.065-.133-.137-.193-.211-.047-.058-.088-.12-.13-.184l-.012-.022c-.052-.08-.098-.164-.138-.252a3.644 3.644 0 0 1-.098-.233l-.012-.028c-.035-.097-.063-.196-.084-.298a3.5 3.5 0 0 1-.042-.3l-.007-.035c-.014-.1-.021-.2-.021-.3V68.032L33.09 54.27l-20.645-14.01zm44.24-30.007L15.584 33.247l41.066 23.707 41.066-23.674-41.03-23.028zm22.482 205.327l23.804-13.638V68.032L85.702 77.83l-23.838 13.762v133.987zm135.834-170.78l-41.1 23.707 41.1 23.674 41.033-23.674-41.033-23.706zm-4.147 54.485L187.2 85.522l-17.268-9.968v49.273l23.837 13.762 17.269 9.935v-49.273zm-91.46 126.316l60.742-34.742 30.667-17.556-41.033-23.673-47.476 27.403-44.9 25.928z"/></svg>
              </div>
              <h5>Laravel SDK</h5>
              <p>Fluent API, Facades, webhook handling</p>
              <code class="integration-install">composer require callmelater/laravel</code>
            </a>
          </div>
          <div class="col-md-6 col-lg-3">
            <a href="https://github.com/Canell/callmelater-n8n-node" target="_blank" rel="noopener" class="integration-card">
              <div class="integration-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="#EA4B71" d="M12.8 3.6c-1 0-1.8.8-1.8 1.8s.8 1.8 1.8 1.8 1.8-.8 1.8-1.8-.8-1.8-1.8-1.8m-6.6 6c-1 0-1.8.8-1.8 1.8s.8 1.8 1.8 1.8 1.8-.8 1.8-1.8-.8-1.8-1.8-1.8m13.2 0c-1 0-1.8.8-1.8 1.8s.8 1.8 1.8 1.8 1.8-.8 1.8-1.8-.8-1.8-1.8-1.8m-6.6 6c-1 0-1.8.8-1.8 1.8s.8 1.8 1.8 1.8 1.8-.8 1.8-1.8-.8-1.8-1.8-1.8"/></svg>
              </div>
              <h5>n8n Node</h5>
              <p>Visual workflows, trigger &amp; action nodes</p>
              <code class="integration-install">Community node</code>
            </a>
          </div>
          <div class="col-md-6 col-lg-3">
            <a :href="docsUrl" class="integration-card">
              <div class="integration-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#6b7280" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
              </div>
              <h5>REST API</h5>
              <p>Use from any language with raw HTTP</p>
              <code class="integration-install">curl, Python, Go, Ruby, Java...</code>
            </a>
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

/* Integration Cards */
.integration-card {
  display: block;
  padding: 1.5rem;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  height: 100%;
  text-align: center;
  text-decoration: none;
  color: inherit;
  transition: box-shadow 0.2s, border-color 0.2s;
}

.integration-card:hover {
  box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.1);
  border-color: #22c55e;
  color: inherit;
  text-decoration: none;
}

.integration-icon {
  width: 56px;
  height: 56px;
  margin: 0 auto 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.integration-card h5 {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  margin-bottom: 0.375rem;
}

.integration-card p {
  font-size: 0.8125rem;
  color: #6b7280;
  margin-bottom: 0.75rem;
  line-height: 1.5;
}

.integration-install {
  display: inline-block;
  font-size: 0.75rem;
  color: #22c55e;
  background: #ecfdf5;
  padding: 0.25rem 0.625rem;
  border-radius: 0.25rem;
  font-family: 'SF Mono', Monaco, 'Cascadia Code', Consolas, monospace;
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
