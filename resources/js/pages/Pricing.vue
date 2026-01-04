<template>
  <div class="pricing-page">
    <!-- Header -->
    <section class="py-5 bg-light">
      <div class="container text-center">
        <h1 class="display-5 fw-bold mb-3">Simple, transparent pricing</h1>
        <p class="lead text-secondary">Start free. Scale as you grow.</p>
      </div>
    </section>

    <!-- Pricing Cards -->
    <section class="py-5">
      <div class="container">
        <div class="row g-4 justify-content-center">
          <!-- Free -->
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 border">
              <div class="card-body">
                <h5 class="card-title">Free</h5>
                <div class="display-6 fw-bold mb-3">$0<small class="fs-6 fw-normal text-secondary">/month</small></div>
                <p class="text-secondary small">For trying things out</p>
                <hr>
                <ul class="list-unstyled mb-4">
                  <li class="mb-2">✓ 100 actions/month</li>
                  <li class="mb-2">✓ 3 retry attempts</li>
                  <li class="mb-2">✓ Email reminders only</li>
                  <li class="mb-2">✓ 7-day history</li>
                  <li class="mb-2 text-secondary">✗ Webhook signatures</li>
                  <li class="mb-2 text-secondary">✗ SMS reminders</li>
                </ul>
                <router-link to="/register" class="btn btn-outline-primary w-100">Get Started</router-link>
              </div>
            </div>
          </div>

          <!-- Pro -->
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-primary border-2">
              <div class="card-header bg-primary text-white text-center py-2">
                Most Popular
              </div>
              <div class="card-body">
                <h5 class="card-title">Pro</h5>
                <div class="display-6 fw-bold mb-3">$29<small class="fs-6 fw-normal text-secondary">/month</small></div>
                <p class="text-secondary small">For growing projects</p>
                <hr>
                <ul class="list-unstyled mb-4">
                  <li class="mb-2">✓ 5,000 actions/month</li>
                  <li class="mb-2">✓ 10 retry attempts</li>
                  <li class="mb-2">✓ Email + SMS reminders</li>
                  <li class="mb-2">✓ 90-day history</li>
                  <li class="mb-2">✓ Webhook signatures</li>
                  <li class="mb-2">✓ Priority support</li>
                </ul>
                <button @click="subscribe('pro')" class="btn btn-primary w-100" :disabled="loading">
                  {{ loading ? 'Loading...' : 'Subscribe' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Business -->
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 border">
              <div class="card-body">
                <h5 class="card-title">Business</h5>
                <div class="display-6 fw-bold mb-3">$99<small class="fs-6 fw-normal text-secondary">/month</small></div>
                <p class="text-secondary small">For teams and scale</p>
                <hr>
                <ul class="list-unstyled mb-4">
                  <li class="mb-2">✓ 25,000 actions/month</li>
                  <li class="mb-2">✓ Unlimited retries</li>
                  <li class="mb-2">✓ Email + SMS reminders</li>
                  <li class="mb-2">✓ 1-year history</li>
                  <li class="mb-2">✓ Webhook signatures</li>
                  <li class="mb-2">✓ Team features</li>
                  <li class="mb-2">✓ Escalation rules</li>
                  <li class="mb-2">✓ Dedicated support</li>
                </ul>
                <button @click="subscribe('business')" class="btn btn-outline-primary w-100" :disabled="loading">
                  {{ loading ? 'Loading...' : 'Subscribe' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Enterprise -->
          <div class="col-md-6 col-lg-3">
            <div class="card h-100 border">
              <div class="card-body">
                <h5 class="card-title">Enterprise</h5>
                <div class="display-6 fw-bold mb-3">Custom</div>
                <p class="text-secondary small">For large organizations</p>
                <hr>
                <ul class="list-unstyled mb-4">
                  <li class="mb-2">✓ Unlimited actions</li>
                  <li class="mb-2">✓ Unlimited retries</li>
                  <li class="mb-2">✓ Custom retention</li>
                  <li class="mb-2">✓ SLA guarantees</li>
                  <li class="mb-2">✓ On-premise option</li>
                  <li class="mb-2">✓ Dedicated infrastructure</li>
                  <li class="mb-2">✓ 24/7 support</li>
                </ul>
                <a href="mailto:sales@callmelater.io" class="btn btn-outline-secondary w-100">Contact Sales</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="py-5 bg-light">
      <div class="container">
        <h2 class="text-center mb-5">Frequently Asked Questions</h2>
        <div class="row justify-content-center">
          <div class="col-lg-8">
            <div class="accordion" id="faqAccordion">
              <div class="accordion-item">
                <h3 class="accordion-header">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                    What counts as an action?
                  </button>
                </h3>
                <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                  <div class="accordion-body">
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
                  <div class="accordion-body">
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
                  <div class="accordion-body">
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
                  <div class="accordion-body">
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
                  <div class="accordion-body">
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
    <section class="py-5 bg-primary text-white">
      <div class="container text-center">
        <h2 class="mb-3">Ready to get started?</h2>
        <p class="lead mb-4">Start free, upgrade when you need more.</p>
        <router-link to="/register" class="btn btn-light btn-lg">Create Free Account</router-link>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const loading = ref(false);

async function subscribe(plan) {
  // Check if user is authenticated
  const token = localStorage.getItem('token');
  if (!token) {
    // Redirect to register with plan param
    window.location.href = `/register?plan=${plan}`;
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
.pricing-page {
  margin-top: -1rem;
}

.card-header {
  font-size: 0.85rem;
  font-weight: 600;
  letter-spacing: 0.5px;
}
</style>
