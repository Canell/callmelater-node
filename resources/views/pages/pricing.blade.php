@extends('layouts.marketing')

@section('title', 'Pricing - CallMeLater')
@section('description', 'Simple, transparent pricing. Start free. Scale as you grow. Choose the plan that fits your needs.')

@section('styles')
<style>
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
        content: "\2713";
        position: absolute;
        left: 0;
        color: #22C55E;
        font-weight: 600;
    }

    .feature-list li.excluded {
        color: #9ca3af;
    }

    .feature-list li.excluded::before {
        content: "\2717";
        position: absolute;
        left: 0;
        color: #d1d5db;
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

    /* Billing Toggle */
    .billing-toggle {
        border: 1px solid #e5e7eb;
    }

    .toggle-btn {
        background: transparent;
        border: none;
        padding: 0.5rem 1.25rem;
        font-size: 0.9375rem;
        font-weight: 500;
        color: #6b7280;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
    }

    .toggle-btn:hover {
        color: #374151;
    }

    .toggle-btn.active {
        background-color: #111827;
        color: white;
    }

    .save-badge {
        display: inline-block;
        background-color: #22C55E;
        color: white;
        font-size: 0.625rem;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: 6px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .toggle-btn.active .save-badge {
        background-color: #16a34a;
    }

    /* Price animation */
    .price-amount {
        display: inline-block;
        transition: transform 0.2s;
    }

    .annual-note {
        min-height: 1.25rem;
    }
</style>
@endsection

@section('content')
<div class="pricing-page">
    <!-- Header -->
    <section class="pricing-hero">
        <div class="container text-center">
            <h1 class="display-5 fw-bold mb-3">Simple, transparent pricing</h1>
            <p class="lead text-muted mb-4">Start free. Scale as you grow.</p>

            <!-- Billing Toggle -->
            <div class="billing-toggle d-inline-flex align-items-center bg-white rounded-pill p-1 shadow-sm">
                <button class="toggle-btn active" data-billing="monthly">
                    Monthly
                </button>
                <button class="toggle-btn" data-billing="annual">
                    Annual
                    <span class="save-badge">Save 17%</span>
                </button>
            </div>
        </div>
    </section>

    <!-- Pricing Cards -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4 justify-content-center">
                <!-- Free -->
                <div class="col-md-6 col-lg-3">
                    <div class="card pricing-card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title text-muted text-uppercase small fw-semibold">Free</h5>
                            <div class="price-display mb-1">&euro;0<span class="price-period">/month</span></div>
                            <div class="annual-note mb-3">&nbsp;</div>
                            <p class="text-muted small mb-4">For trying things out</p>
                            <ul class="feature-list mb-4">
                                <li class="included">100 actions/month</li>
                                <li class="included">3 retry attempts</li>
                                <li class="included">Email reminders</li>
                                <li class="included">7-day history</li>
                                <li class="included">2 action templates</li>
                                <li class="included">Webhook signatures</li>
                                <li class="excluded">0 SMS/month</li>
                                <li class="excluded">No callback webhooks</li>
                                <li class="excluded">No team workspaces</li>
                                <li class="excluded">Community support</li>
                            </ul>
                            <a href="/register" class="btn btn-outline-cml w-100 mt-auto">Get Started</a>
                        </div>
                    </div>
                </div>

                <!-- Pro -->
                <div class="col-md-6 col-lg-3">
                    <div class="card pricing-card pricing-card-featured h-100">
                        <div class="card-badge">Most Popular</div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title text-muted text-uppercase small fw-semibold">Pro</h5>
                            <div class="price-display mb-1">
                                <span class="price-amount" data-monthly="9" data-annual="7.50">&euro;9</span>
                                <span class="price-period">/month</span>
                            </div>
                            <div class="annual-note mb-3" style="display: none;">
                                <span class="text-success small fw-medium">&euro;90/year</span>
                                <span class="text-muted small"> (2 months free)</span>
                            </div>
                            <div class="monthly-spacer mb-3">&nbsp;</div>
                            <p class="text-muted small mb-4">For growing projects</p>
                            <ul class="feature-list mb-4">
                                <li class="included">5,000 actions/month</li>
                                <li class="included">10 retry attempts</li>
                                <li class="included">Email reminders</li>
                                <li class="included">Teams & Slack integration</li>
                                <li class="included">15 SMS/month</li>
                                <li class="included">90-day history</li>
                                <li class="included">20 action templates</li>
                                <li class="included">Callback webhooks</li>
                                <li class="excluded">No team workspaces</li>
                            </ul>
                            <button type="button" class="btn btn-cml-primary w-100 mt-auto subscribe-btn" data-plan="pro">Subscribe</button>
                        </div>
                    </div>
                </div>

                <!-- Business -->
                <div class="col-md-6 col-lg-3">
                    <div class="card pricing-card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title text-muted text-uppercase small fw-semibold">Business</h5>
                            <div class="price-display mb-1">
                                <span class="price-amount" data-monthly="39" data-annual="32.50">&euro;39</span>
                                <span class="price-period">/month</span>
                            </div>
                            <div class="annual-note mb-3" style="display: none;">
                                <span class="text-success small fw-medium">&euro;390/year</span>
                                <span class="text-muted small"> (2 months free)</span>
                            </div>
                            <div class="monthly-spacer mb-3">&nbsp;</div>
                            <p class="text-muted small mb-4">For teams and scale</p>
                            <ul class="feature-list mb-4">
                                <li class="included">25,000 actions/month</li>
                                <li class="included">Unlimited retries</li>
                                <li class="included">Email reminders</li>
                                <li class="included">Teams & Slack integration</li>
                                <li class="included">50 SMS/month</li>
                                <li class="included">1-year history</li>
                                <li class="included">Unlimited templates</li>
                                <li class="included">Workflows</li>
                                <li class="included">Team workspaces</li>
                                <li class="included">Priority email support</li>
                            </ul>
                            <button type="button" class="btn btn-outline-cml w-100 mt-auto subscribe-btn" data-plan="business">Subscribe</button>
                        </div>
                    </div>
                </div>

                <!-- Enterprise -->
                <div class="col-md-6 col-lg-3">
                    <div class="card pricing-card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title text-muted text-uppercase small fw-semibold">Enterprise</h5>
                            <div class="price-display mb-1">Custom</div>
                            <div class="annual-note mb-3">&nbsp;</div>
                            <p class="text-muted small mb-4">For large organizations</p>
                            <ul class="feature-list mb-4">
                                <li class="included">Everything in Business</li>
                                <li class="included">Unlimited actions</li>
                                <li class="included">Custom SMS quota</li>
                                <li class="included">Custom retention</li>
                                <li class="included">Unlimited workflows</li>
                                <li class="included">Dedicated support</li>
                                <li class="included">Custom SLA available</li>
                            </ul>
                            <a href="/contact" class="btn btn-outline-secondary w-100 mt-auto">Contact Sales</a>
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
                                    Yes! Use the toggle above to switch to annual billing and get 2 months free
                                    (pay for 10 months, get 12). That's a 17% discount compared to monthly billing.
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
            <a href="/register" class="btn btn-light btn-lg px-4">Create Free Account</a>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtns = document.querySelectorAll('.toggle-btn');
        const priceAmounts = document.querySelectorAll('.price-amount');
        const annualNotes = document.querySelectorAll('.annual-note');
        const monthlySpacers = document.querySelectorAll('.monthly-spacer');
        const subscribeBtns = document.querySelectorAll('.subscribe-btn');
        let currentBilling = 'monthly';

        // Billing toggle
        toggleBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const billing = this.dataset.billing;
                if (billing === currentBilling) return;

                currentBilling = billing;

                // Update toggle buttons
                toggleBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Update prices
                priceAmounts.forEach(function(price) {
                    const monthlyPrice = price.dataset.monthly;
                    const annualPrice = price.dataset.annual;
                    if (monthlyPrice && annualPrice) {
                        price.textContent = '€' + (billing === 'annual' ? annualPrice : monthlyPrice);
                    }
                });

                // Toggle annual notes
                annualNotes.forEach(function(note) {
                    note.style.display = billing === 'annual' ? 'block' : 'none';
                });

                monthlySpacers.forEach(function(spacer) {
                    spacer.style.display = billing === 'annual' ? 'none' : 'block';
                });
            });
        });

        // Helper to get CSRF token from cookie
        function getCsrfToken() {
            const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            return match ? decodeURIComponent(match[1]) : null;
        }

        // Subscribe button handlers
        subscribeBtns.forEach(function(btn) {
            btn.addEventListener('click', async function() {
                const plan = this.dataset.plan;
                const token = localStorage.getItem('token');

                // Not authenticated - redirect to register
                if (!token) {
                    window.location.href = '/register?plan=' + plan + '&billing=' + currentBilling;
                    return;
                }

                // Authenticated - call checkout API
                const originalText = this.textContent;
                this.disabled = true;
                this.textContent = 'Loading...';

                try {
                    // Ensure we have a CSRF token
                    let csrfToken = getCsrfToken();
                    if (!csrfToken) {
                        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
                        csrfToken = getCsrfToken();
                    }

                    const response = await fetch('/api/subscription/checkout', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-XSRF-TOKEN': csrfToken || ''
                        },
                        body: JSON.stringify({
                            plan: plan,
                            billing: currentBilling
                        })
                    });

                    // Handle 401 - token expired
                    if (response.status === 401) {
                        localStorage.removeItem('token');
                        window.location.href = '/register?plan=' + plan + '&billing=' + currentBilling;
                        return;
                    }

                    // Try to parse JSON response
                    let data;
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        data = await response.json();
                    } else {
                        // Non-JSON response (likely a server error page)
                        const text = await response.text();
                        console.error('Non-JSON response:', text);
                        this.disabled = false;
                        this.textContent = originalText;
                        alert('Server error. Please check your Stripe configuration.');
                        return;
                    }

                    if (!response.ok) {
                        // Error - redirect to subscription result page with actual error
                        const errorMsg = data.error || data.message || 'Subscription failed';
                        window.location.href = '/subscription/result?status=error&message=' + encodeURIComponent(errorMsg);
                        return;
                    }

                    // Redirect to Stripe Checkout for new subscriptions
                    if (data.checkout_url) {
                        window.location.href = data.checkout_url;
                    }
                    // Plan swap completed immediately
                    else if (data.message) {
                        window.location.href = '/subscription/result?status=changed&plan=' + plan;
                    }
                } catch (error) {
                    console.error('Subscription error:', error);
                    this.disabled = false;
                    this.textContent = originalText;
                    alert('Failed to start checkout: ' + error.message);
                }
            });
        });
    });
</script>
@endsection
