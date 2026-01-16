<template>
    <div class="contact-page">
        <section class="contact-hero">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-6 text-center">
                        <h1 class="display-5 fw-bold mb-3">Contact Us</h1>
                        <p class="lead text-muted">Have a question or need help? We'd love to hear from you.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="card card-cml">
                            <div class="card-body p-4">
                                <!-- Success message -->
                                <div v-if="submitted" class="text-center py-4">
                                    <div class="mb-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                            <polyline points="22 4 12 14.01 9 11.01"/>
                                        </svg>
                                    </div>
                                    <h4>Message Sent!</h4>
                                    <p class="text-muted">Thanks for reaching out. We'll get back to you as soon as possible.</p>
                                    <button class="btn btn-outline-secondary" @click="resetForm">Send Another Message</button>
                                </div>

                                <!-- Contact form -->
                                <form v-else @submit.prevent="submitForm">
                                    <div class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            v-model="form.name"
                                            required
                                            placeholder="Your name"
                                        >
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input
                                            type="email"
                                            class="form-control"
                                            v-model="form.email"
                                            required
                                            placeholder="you@example.com"
                                        >
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Subject</label>
                                        <select class="form-select" v-model="form.subject" required>
                                            <option value="">Select a topic...</option>
                                            <option value="general">General Inquiry</option>
                                            <option value="support">Technical Support</option>
                                            <option value="billing">Billing Question</option>
                                            <option value="enterprise">Enterprise Sales</option>
                                            <option value="feedback">Feedback</option>
                                        </select>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label">Message</label>
                                        <textarea
                                            class="form-control"
                                            v-model="form.message"
                                            rows="5"
                                            required
                                            placeholder="How can we help?"
                                        ></textarea>
                                    </div>

                                    <div v-if="error" class="alert alert-danger mb-3">
                                        {{ error }}
                                    </div>

                                    <button type="submit" class="btn btn-cml-primary w-100" :disabled="submitting">
                                        {{ submitting ? 'Sending...' : 'Send Message' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const RECAPTCHA_SITE_KEY = import.meta.env.VITE_RECAPTCHA_SITE_KEY;

const form = ref({
    name: '',
    email: '',
    subject: '',
    message: '',
});

const submitting = ref(false);
const submitted = ref(false);
const error = ref(null);
const recaptchaLoaded = ref(false);

// Load reCAPTCHA script
function loadRecaptcha() {
    if (!RECAPTCHA_SITE_KEY || window.grecaptcha) {
        recaptchaLoaded.value = !!window.grecaptcha;
        return;
    }

    const script = document.createElement('script');
    script.src = `https://www.google.com/recaptcha/api.js?render=${RECAPTCHA_SITE_KEY}`;
    script.async = true;
    script.defer = true;
    script.onload = () => {
        recaptchaLoaded.value = true;
    };
    document.head.appendChild(script);
}

// Get reCAPTCHA token
async function getRecaptchaToken() {
    if (!RECAPTCHA_SITE_KEY || !window.grecaptcha) {
        return null;
    }

    return new Promise((resolve) => {
        window.grecaptcha.ready(() => {
            window.grecaptcha
                .execute(RECAPTCHA_SITE_KEY, { action: 'contact' })
                .then(resolve)
                .catch(() => resolve(null));
        });
    });
}

// Pre-fill email if user is authenticated
onMounted(async () => {
    loadRecaptcha();

    const token = localStorage.getItem('token');
    if (token) {
        try {
            const response = await axios.get('/api/user');
            form.value.name = response.data.name || '';
            form.value.email = response.data.email || '';
        } catch (err) {
            // Ignore - user might not be authenticated
        }
    }
});

// Hide reCAPTCHA badge when leaving page
onUnmounted(() => {
    const badge = document.querySelector('.grecaptcha-badge');
    if (badge) {
        badge.style.visibility = 'hidden';
    }
});

async function submitForm() {
    submitting.value = true;
    error.value = null;

    try {
        // Get reCAPTCHA token
        const recaptchaToken = await getRecaptchaToken();

        await axios.post('/api/contact', {
            ...form.value,
            recaptcha_token: recaptchaToken,
        });
        submitted.value = true;
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to send message. Please try again.';
    } finally {
        submitting.value = false;
    }
}

function resetForm() {
    form.value = {
        name: '',
        email: '',
        subject: '',
        message: '',
    };
    submitted.value = false;
    error.value = null;
}
</script>

<style scoped>
.contact-hero {
    padding: 4rem 0 2rem;
    background-color: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}

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
</style>
