<template>
  <div class="use-cases-page">
    <!-- Hero -->
    <section class="use-cases-hero">
      <div class="container text-center">
        <h1 class="display-5 fw-bold mb-3">Built for real-world scheduling</h1>
        <p class="lead text-muted mb-4">
          Some things should happen later — reliably — and sometimes only after a human decision.
        </p>
        <p class="text-muted">
          Instead of building cron jobs, queues, retries, approval flows, and reminder systems yourself,
          CallMeLater gives you a single, reliable primitive to schedule actions in the future.
        </p>
      </div>
    </section>

    <!-- Developer Use Cases -->
    <section class="py-5">
      <div class="container">
        <div class="section-header text-center mb-5">
          <span class="section-badge">Developer & System</span>
          <h2 class="fw-bold mt-3">Automated scheduling for your backend</h2>
          <p class="text-muted">Replace cron jobs, queues, and retry systems with a single API call</p>
        </div>

        <div class="row g-4">
          <!-- Trial Expiration -->
          <div class="col-lg-6">
            <div class="use-case-card h-100">
              <div class="use-case-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              </div>
              <h4 class="fw-semibold mb-2">Trial Expiration Cleanup</h4>
              <p class="text-muted mb-3">
                Automatically clean up resources when a trial ends — unless the user upgrades.
              </p>
              <div class="scenario mb-3">
                <strong class="small text-uppercase text-muted">Scenario</strong>
                <p class="small mb-0">A user starts a 14-day trial. If they don't upgrade, you must revoke access and clean up data.</p>
              </div>
              <ul class="benefits-list">
                <li>Schedule a cleanup webhook at <code>trial_end_at</code></li>
                <li>Cancel the action instantly if the user upgrades</li>
                <li>No cron jobs, no background workers to maintain</li>
              </ul>
            </div>
          </div>

          <!-- Delayed Retries -->
          <div class="col-lg-6">
            <div class="use-case-card h-100">
              <div class="use-case-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
              </div>
              <h4 class="fw-semibold mb-2">Delayed Retries Without Cron</h4>
              <p class="text-muted mb-3">
                Retry an operation hours or days later without keeping state.
              </p>
              <div class="scenario mb-3">
                <strong class="small text-uppercase text-muted">Scenario</strong>
                <p class="small mb-0">An external API is down. Retrying every minute is pointless.</p>
              </div>
              <ul class="benefits-list">
                <li>Schedule retries with exponential backoff</li>
                <li>Attempts and outcomes are fully logged</li>
                <li>Your app stays stateless</li>
              </ul>
            </div>
          </div>

          <!-- Grace Period -->
          <div class="col-lg-6">
            <div class="use-case-card h-100">
              <div class="use-case-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </div>
              <h4 class="fw-semibold mb-2">Grace Period Enforcement</h4>
              <p class="text-muted mb-3">
                "We'll delete this in 7 days unless..."
              </p>
              <div class="scenario mb-3">
                <strong class="small text-uppercase text-muted">Scenario</strong>
                <p class="small mb-0">Compliance rules require a grace period before deletion.</p>
              </div>
              <ul class="benefits-list">
                <li>Schedule a deletion action</li>
                <li>Send a reminder before execution</li>
                <li>Allow snooze or cancellation</li>
              </ul>
            </div>
          </div>

          <!-- Deferred Side Effects -->
          <div class="col-lg-6">
            <div class="use-case-card h-100">
              <div class="use-case-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </div>
              <h4 class="fw-semibold mb-2">Deferred Side Effects</h4>
              <p class="text-muted mb-3">
                Trigger follow-up actions later — exactly once.
              </p>
              <div class="scenario mb-3">
                <strong class="small text-uppercase text-muted">Examples</strong>
                <p class="small mb-0">Delayed partner notifications, post-processing jobs, background audits</p>
              </div>
              <ul class="benefits-list">
                <li>One scheduled webhook</li>
                <li>Guaranteed attempts</li>
                <li>Idempotent execution</li>
              </ul>
            </div>
          </div>

          <!-- External System Follow-Ups -->
          <div class="col-lg-6 offset-lg-3">
            <div class="use-case-card h-100">
              <div class="use-case-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
              </div>
              <h4 class="fw-semibold mb-2">External System Follow-Ups</h4>
              <p class="text-muted mb-3">
                Check back later when another system catches up.
              </p>
              <div class="scenario mb-3">
                <strong class="small text-uppercase text-muted">Scenario</strong>
                <p class="small mb-0">You integrate with a system that requires manual review.</p>
              </div>
              <ul class="benefits-list">
                <li>Schedule a follow-up check</li>
                <li>Reschedule automatically if unresolved</li>
                <li>No polling infrastructure required</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Code Example -->
    <section class="py-5 code-section">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-5 mb-4 mb-lg-0">
            <h3 class="fw-bold mb-3">Simple API, powerful results</h3>
            <p class="text-muted mb-4">
              Schedule a webhook in a single API call. No infrastructure to manage,
              no workers to maintain, no state to track.
            </p>
            <router-link to="/register" class="btn btn-cml-primary">
              Get Started Free
            </router-link>
          </div>
          <div class="col-lg-7">
            <CodeTabs :examples="createHttpAction" default-tab="curl" />
          </div>
        </div>
      </div>
    </section>

    <!-- Human Use Cases -->
    <section class="py-5 bg-light">
      <div class="container">
        <div class="section-header text-center mb-5">
          <span class="section-badge section-badge-secondary">Human-in-the-Loop</span>
          <h2 class="fw-bold mt-3">When humans need to decide</h2>
          <p class="text-muted">Interactive reminders that require confirmation before executing</p>
        </div>

        <div class="row g-4">
          <!-- Did You Do X? -->
          <div class="col-lg-6">
            <div class="use-case-card h-100">
              <div class="use-case-icon use-case-icon-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              </div>
              <h4 class="fw-semibold mb-2">"Did You Do X?" Reminders</h4>
              <p class="text-muted mb-3">
                Lightweight accountability without task management tools.
              </p>
              <div class="scenario mb-3">
                <strong class="small text-uppercase text-muted">Scenario</strong>
                <p class="small mb-0">You need confirmation that something was done.</p>
              </div>
              <ul class="benefits-list">
                <li>Send a reminder email</li>
                <li>One-click Yes / No / Snooze</li>
                <li>No login required for recipients</li>
              </ul>
            </div>
          </div>

          <!-- Approval Before Action -->
          <div class="col-lg-6">
            <div class="use-case-card h-100">
              <div class="use-case-icon use-case-icon-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
              </div>
              <h4 class="fw-semibold mb-2">Approval Before Action</h4>
              <p class="text-muted mb-3">
                Require human approval before executing risky actions.
              </p>
              <div class="scenario mb-3">
                <strong class="small text-uppercase text-muted">Scenario</strong>
                <p class="small mb-0">A destructive operation must be approved before proceeding.</p>
              </div>
              <ul class="benefits-list">
                <li>Reminder is sent instead of execution</li>
                <li>"Yes" triggers the webhook</li>
                <li>"No" cancels the action</li>
              </ul>
            </div>
          </div>

          <!-- Escalation -->
          <div class="col-lg-6">
            <div class="use-case-card h-100">
              <div class="use-case-icon use-case-icon-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              </div>
              <h4 class="fw-semibold mb-2">Escalation When No One Responds</h4>
              <p class="text-muted mb-3">
                If nobody replies, notify someone else.
              </p>
              <div class="scenario mb-3">
                <strong class="small text-uppercase text-muted">Scenario</strong>
                <p class="small mb-0">Critical actions cannot be ignored.</p>
              </div>
              <ul class="benefits-list">
                <li>Primary reminder sent</li>
                <li>Automatic escalation after timeout</li>
                <li>Full audit trail</li>
              </ul>
            </div>
          </div>

          <!-- Team Confirmation -->
          <div class="col-lg-6">
            <div class="use-case-card h-100">
              <div class="use-case-icon use-case-icon-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
              <h4 class="fw-semibold mb-2">Team Confirmation Workflows</h4>
              <p class="text-muted mb-3">
                "Everyone must confirm" vs "first response wins."
              </p>
              <div class="scenario mb-3">
                <strong class="small text-uppercase text-muted">Scenario</strong>
                <p class="small mb-0">Safety-critical or compliance steps require team sign-off.</p>
              </div>
              <ul class="benefits-list">
                <li>Multiple recipients</li>
                <li>Configurable confirmation mode</li>
                <li>Execution depends on responses</li>
              </ul>
            </div>
          </div>

          <!-- Manual Reminders -->
          <div class="col-lg-6 offset-lg-3">
            <div class="use-case-card h-100">
              <div class="use-case-icon use-case-icon-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              </div>
              <h4 class="fw-semibold mb-2">Manual Reminders Without Building an App</h4>
              <p class="text-muted mb-3">
                Use CallMeLater as a simple reminder engine.
              </p>
              <div class="scenario mb-3">
                <strong class="small text-uppercase text-muted">Scenario</strong>
                <p class="small mb-0">You want reminders without building UI or mobile apps.</p>
              </div>
              <ul class="benefits-list">
                <li>Create reminders via dashboard or API</li>
                <li>Email-based interaction only</li>
                <li>No accounts required for recipients</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Flow Diagrams -->
    <section class="py-5">
      <div class="container">
        <div class="section-header text-center mb-5">
          <h2 class="fw-bold">How it works</h2>
          <p class="text-muted">Simple flows, reliable execution</p>
        </div>

        <div class="row g-4">
          <div class="col-lg-6">
            <div class="flow-card">
              <h5 class="fw-semibold mb-4">Scheduled HTTP Action</h5>
              <div class="flow-steps">
                <div class="flow-step">
                  <div class="flow-step-number">1</div>
                  <div class="flow-step-content">
                    <strong>Create action</strong>
                    <span class="text-muted">via API or dashboard</span>
                  </div>
                </div>
                <div class="flow-arrow"></div>
                <div class="flow-step">
                  <div class="flow-step-number">2</div>
                  <div class="flow-step-content">
                    <strong>Stored & scheduled</strong>
                    <span class="text-muted">in durable queue</span>
                  </div>
                </div>
                <div class="flow-arrow"></div>
                <div class="flow-step">
                  <div class="flow-step-number">3</div>
                  <div class="flow-step-content">
                    <strong>Dispatched at time</strong>
                    <span class="text-muted">locked for execution</span>
                  </div>
                </div>
                <div class="flow-arrow"></div>
                <div class="flow-step">
                  <div class="flow-step-number">4</div>
                  <div class="flow-step-content">
                    <strong>HTTP request sent</strong>
                    <span class="text-muted">with retry on failure</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="flow-card">
              <h5 class="fw-semibold mb-4">Human Approval Flow</h5>
              <div class="flow-steps">
                <div class="flow-step">
                  <div class="flow-step-number">1</div>
                  <div class="flow-step-content">
                    <strong>Create reminder</strong>
                    <span class="text-muted">with recipients</span>
                  </div>
                </div>
                <div class="flow-arrow"></div>
                <div class="flow-step">
                  <div class="flow-step-number">2</div>
                  <div class="flow-step-content">
                    <strong>Email sent</strong>
                    <span class="text-muted">with Yes/No/Snooze</span>
                  </div>
                </div>
                <div class="flow-arrow"></div>
                <div class="flow-step">
                  <div class="flow-step-number">3</div>
                  <div class="flow-step-content">
                    <strong>Recipient responds</strong>
                    <span class="text-muted">one-click action</span>
                  </div>
                </div>
                <div class="flow-arrow"></div>
                <div class="flow-step">
                  <div class="flow-step-number">4</div>
                  <div class="flow-step-content">
                    <strong>Webhook triggered</strong>
                    <span class="text-muted">with response data</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Final Thought -->
    <section class="py-5 bg-light">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-8 text-center">
            <h3 class="fw-bold mb-3">Not a task manager. Not a cron replacement.</h3>
            <p class="lead text-muted">
              CallMeLater is a <strong>reliable bridge between time, systems, and people</strong> —
              designed to be simple, auditable, and predictable.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
      <div class="container text-center">
        <h2 class="mb-3 fw-bold text-white">Ready to schedule your first action?</h2>
        <p class="lead mb-4 text-white opacity-90">Get started in minutes. No credit card required.</p>
        <router-link to="/register" class="btn btn-light btn-lg px-4">Create Free Account</router-link>
      </div>
    </section>
  </div>
</template>

<script setup>
import CodeTabs from '../components/CodeTabs.vue';
import { createHttpAction } from '../data/codeExamples';
</script>

<style scoped>
/* Hero */
.use-cases-hero {
  padding: 4rem 0 3rem;
  background-color: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

/* Section Header */
.section-header {
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
}

.section-badge {
  display: inline-block;
  background-color: #dcfce7;
  color: #15803d;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.section-badge-secondary {
  background-color: #e0e7ff;
  color: #4338ca;
}

/* Use Case Cards */
.use-case-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.5rem;
  transition: transform 0.2s, box-shadow 0.2s;
}

.use-case-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
}

.use-case-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  background-color: #dcfce7;
  color: #15803d;
  border-radius: 12px;
  margin-bottom: 1rem;
}

.use-case-icon-secondary {
  background-color: #e0e7ff;
  color: #4338ca;
}

.scenario {
  background-color: #f9fafb;
  border-radius: 8px;
  padding: 0.75rem;
}

.benefits-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.benefits-list li {
  padding: 0.375rem 0;
  padding-left: 1.5rem;
  position: relative;
  font-size: 0.9375rem;
  color: #374151;
}

.benefits-list li::before {
  content: "\2713";
  position: absolute;
  left: 0;
  color: #22C55E;
  font-weight: 600;
}

/* Code Section */
.code-section {
  background-color: #1e293b;
  color: white;
}

.code-section .text-muted {
  color: #94a3b8 !important;
}

/* Flow Cards */
.flow-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.5rem;
}

.flow-steps {
  display: flex;
  flex-direction: column;
}

.flow-step {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.flow-step-number {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  background-color: #22C55E;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
}

.flow-step-content {
  display: flex;
  flex-direction: column;
}

.flow-step-content strong {
  color: #111827;
}

.flow-step-content .text-muted {
  font-size: 0.875rem;
}

.flow-arrow {
  width: 2px;
  height: 20px;
  background-color: #d1d5db;
  margin-left: 15px;
  margin-top: 4px;
  margin-bottom: 4px;
}

/* Buttons */
.btn-cml-primary {
  background-color: #22C55E;
  border-color: #22C55E;
  color: white;
  font-weight: 500;
}

.btn-cml-primary:hover {
  background-color: #16a34a;
  border-color: #16a34a;
  color: white;
}

/* CTA Section */
.cta-section {
  padding: 4rem 0;
  background: linear-gradient(135deg, #22C55E 0%, #16a34a 100%);
}

.cta-section .btn-light {
  color: #15803d;
  font-weight: 600;
}

.cta-section .btn-light:hover {
  background-color: #f0fdf4;
  color: #166534;
}
</style>
