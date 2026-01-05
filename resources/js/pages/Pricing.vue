<template>
  <div class="pricing-page">
    <!-- Header -->
    <section class="pricing-hero">
      <div class="container text-center">
        <h1 class="display-5 fw-bold mb-3">Simple, transparent pricing</h1>
        <p class="lead text-muted">Start free. Scale as you grow.</p>
      </div>
    </section>

    <!-- Pricing Cards -->
    <section class="py-5">
      <div class="container">
        <div class="row g-4 justify-content-center">
          <!-- Free -->
          <div class="col-md-6 col-lg-3">
            <div class="card pricing-card h-100">
              <div class="card-body">
                <h5 class="card-title text-muted text-uppercase small fw-semibold">Free</h5>
                <div class="price-display mb-3">$0<span class="price-period">/month</span></div>
                <p class="text-muted small mb-4">For trying things out</p>
                <ul class="feature-list mb-4">
                  <li class="included">100 actions/month</li>
                  <li class="included">3 retry attempts</li>
                  <li class="included">Email reminders only</li>
                  <li class="included">7-day history</li>
                  <li class="excluded">Webhook signatures</li>
                  <li class="excluded">SMS reminders</li>
                </ul>
                <router-link to="/register" class="btn btn-outline-cml w-100">Get Started</router-link>
              </div>
            </div>
          </div>

          <!-- Pro -->
          <div class="col-md-6 col-lg-3">
            <div class="card pricing-card pricing-card-featured h-100">
              <div class="card-badge">Most Popular</div>
              <div class="card-body">
                <h5 class="card-title text-muted text-uppercase small fw-semibold">Pro</h5>
                <div class="price-display mb-3">$29<span class="price-period">/month</span></div>
                <p class="text-muted small mb-4">For growing projects</p>
                <ul class="feature-list mb-4">
                  <li class="included">5,000 actions/month</li>
                  <li class="included">10 retry attempts</li>
                  <li class="included">Email + SMS reminders</li>
                  <li class="included">90-day history</li>
                  <li class="included">Webhook signatures</li>
                  <li class="included">Priority support</li>
                </ul>
                <button @click="subscribe('pro')" class="btn btn-cml-primary w-100" :disabled="loading">
                  {{ loading ? 'Loading...' : 'Subscribe' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Business -->
          <div class="col-md-6 col-lg-3">
            <div class="card pricing-card h-100">
              <div class="card-body">
                <h5 class="card-title text-muted text-uppercase small fw-semibold">Business</h5>
                <div class="price-display mb-3">$99<span class="price-period">/month</span></div>
                <p class="text-muted small mb-4">For teams and scale</p>
                <ul class="feature-list mb-4">
                  <li class="included">25,000 actions/month</li>
                  <li class="included">Unlimited retries</li>
                  <li class="included">Email + SMS reminders</li>
                  <li class="included">1-year history</li>
                  <li class="included">Webhook signatures</li>
                  <li class="included">Team features</li>
                  <li class="included">Escalation rules</li>
                  <li class="included">Dedicated support</li>
                </ul>
                <button @click="subscribe('business')" class="btn btn-outline-cml w-100" :disabled="loading">
                  {{ loading ? 'Loading...' : 'Subscribe' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Enterprise -->
          <div class="col-md-6 col-lg-3">
            <div class="card pricing-card h-100">
              <div class="card-body">
                <h5 class="card-title text-muted text-uppercase small fw-semibold">Enterprise</h5>
                <div class="price-display mb-3">Custom</div>
                <p class="text-muted small mb-4">For large organizations</p>
                <ul class="feature-list mb-4">
                  <li class="included">Unlimited actions</li>
                  <li class="included">Unlimited retries</li>
                  <li class="included">Custom retention</li>
                  <li class="included">SLA guarantees</li>
                  <li class="included">On-premise option</li>
                  <li class="included">Dedicated infrastructure</li>
                  <li class="included">24/7 support</li>
                </ul>
                <a href="mailto:sales@callmelater.io" class="btn btn-outline-secondary w-100">Contact Sales</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="py-5 faq-section">
      <div class="container">
        <h2 class="text-center mb-5 fw-bold">Frequently Asked Questions</h2>
        <div class="row justify-content-center">
          <div class="col-lg-8">
            <div class="accordion accordion-cml" id="faqAccordion">
              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                    What counts as an action?
                  </button>
                </h3>
                <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                  <div class="accordion-body text-muted">
                    An action is any scheduled HTTP call or reminder. Each action counts once when created,
                    regardless of how many retry attempts it takes. Snoozed reminders don't count as new actions.
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                    Can I upgrade or downgrade anytime?
                  </button>
                </h3>
                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                  <div class="accordion-body text-muted">
                    Yes! You can upgrade instantly and the new limits apply immediately.
                    When downgrading, changes take effect at the start of your next billing cycle.
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                    What happens if I exceed my action limit?
                  </button>
                </h3>
                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                  <div class="accordion-body text-muted">
                    You'll receive a warning at 80% usage. If you hit the limit, new action creation will be blocked,
                    but existing scheduled actions will still execute. Upgrade anytime to continue.
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                    Do you offer annual billing?
                  </button>
                </h3>
                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                  <div class="accordion-body text-muted">
                    Yes! Annual billing gives you 2 months free (pay for 10, get 12).
                    Contact us to switch to annual billing.
                  </div>
                </div>
              </div>

              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                    Is there a free trial for paid plans?
                  </button>
                </h3>
                <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                  <div class="accordion-body text-muted">
                    The Free plan is essentially a permanent trial. When you're ready to upgrade,
                    you get immediate access to paid features with no trial period needed.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
      <div class="container text-center">
        <h2 class="mb-3 fw-bold text-white">Ready to get started?</h2>
        <p class="lead mb-4 text-white opacity-90">Start free, upgrade when you need more.</p>
        <router-link to="/register" class="btn btn-light btn-lg px-4">Create Free Account</router-link>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';

const router = useRouter();
const loading = ref(false);

async function subscribe(plan) {
  // Check if user is authenticated
  const token = localStorage.getItem('token');
  if (!token) {
    // Redirect to register with plan param
    router.push({ name: 'register', query: { plan } });
    return;
  }

  loading.value = true;

  try {
    const response = await axios.post('/api/subscription/checkout', {
      plan: plan
    });

    // Redirect to Stripe Checkout
    if (response.data.checkout_url) {
      window.location.href = response.data.checkout_url;
    }
  } catch (error) {
    console.error('Subscription error:', error);
    alert('Something went wrong. Please try again.');
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
/* Hero */
.pricing-hero {
  padding: 4rem 0 3rem;
  background-color: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

/* Pricing Cards */
.pricing-card {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  transition: transform 0.2s, box-shadow 0.2s;
}

.pricing-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
}

.pricing-card-featured {
  border: 2px solid #22C55E;
  position: relative;
}

.card-badge {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  background-color: #22C55E;
  color: white;
  padding: 4px 16px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.price-display {
  font-size: 2.5rem;
  font-weight: 700;
  color: #111827;
}

.price-period {
  font-size: 1rem;
  font-weight: 400;
  color: #6b7280;
}

/* Feature List */
.feature-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.feature-list li {
  padding: 0.5rem 0;
  padding-left: 1.75rem;
  position: relative;
  font-size: 0.9375rem;
  color: #374151;
}

.feature-list li.included::before {
  content: "✓";
  position: absolute;
  left: 0;
  color: #22C55E;
  font-weight: 600;
}

.feature-list li.excluded {
  color: #9ca3af;
}

.feature-list li.excluded::before {
  content: "✗";
  position: absolute;
  left: 0;
  color: #d1d5db;
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

.btn-cml-primary:disabled {
  background-color: #86efac;
  border-color: #86efac;
}

.btn-outline-cml {
  color: #22C55E;
  border-color: #22C55E;
  font-weight: 500;
}

.btn-outline-cml:hover {
  background-color: #22C55E;
  border-color: #22C55E;
  color: white;
}

/* FAQ Section */
.faq-section {
  background-color: #f9fafb;
}

.accordion-cml .accordion-item {
  border: 1px solid #e5e7eb;
  margin-bottom: 0.75rem;
  border-radius: 8px !important;
  overflow: hidden;
}

.accordion-cml .accordion-button {
  font-weight: 500;
  color: #111827;
}

.accordion-cml .accordion-button:not(.collapsed) {
  background-color: #f0fdf4;
  color: #15803d;
}

.accordion-cml .accordion-button:focus {
  box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25);
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
