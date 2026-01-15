<template>
    <div class="container">
        <div class="row justify-content-center py-5">
            <div class="col-md-5">
                <div class="text-center mb-4">
                    <h2><strong>Create Your Account</strong></h2>
                </div>

                <!-- Success: Email sent -->
                <div v-if="emailSent" class="card card-cml p-4 text-center">
                    <div class="mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <h4>Check your email</h4>
                    <p class="text-muted mb-3">
                        We've sent a magic link to <strong>{{ form.email }}</strong>.
                        Click the link to complete your signup.
                    </p>
                    <p class="small text-muted mb-3">
                        Didn't receive the email? Check your spam folder or
                        <button type="button" class="btn btn-link btn-sm p-0" @click="resendEmail" :disabled="resending || resendCooldown > 0">
                            <span v-if="resending">Sending...</span>
                            <span v-else-if="resendCooldown > 0">wait {{ resendCooldown }}s</span>
                            <span v-else>resend the email</span>
                        </button>
                    </p>
                    <router-link to="/login" class="btn btn-cml-primary">
                        Back to Login
                    </router-link>
                </div>

                <!-- Registration Form -->
                <div v-else class="card card-cml p-4">
                    <form @submit.prevent="register">
                        <p class="mb-3">Enter your <strong>email address</strong>.</p>

                        <div class="mb-3">
                            <input
                                type="email"
                                class="form-control"
                                placeholder="you@example.org"
                                v-model="form.email"
                                required
                                autofocus
                            >
                        </div>

                        <p class="text-muted small mb-3">
                            <em>We will email you a magic log in link.</em>
                        </p>

                        <div v-if="error" class="alert alert-danger small" role="alert">
                            {{ error }}
                        </div>

                        <button type="submit" class="btn btn-success w-100" :disabled="loading">
                            <span v-if="loading">Sending...</span>
                            <span v-else>Email Me a Link</span>
                        </button>
                    </form>
                </div>

                <div v-if="!emailSent" class="text-center mt-3">
                    <router-link to="/login" class="text-decoration-none">
                        Already have an account? Sign in
                    </router-link>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'Register',
    data() {
        return {
            form: {
                email: '',
            },
            loading: false,
            error: null,
            emailSent: false,
            resending: false,
            resendCooldown: 0,
            cooldownTimer: null,
        };
    },
    beforeUnmount() {
        if (this.cooldownTimer) {
            clearInterval(this.cooldownTimer);
        }
    },
    methods: {
        async register() {
            this.loading = true;
            this.error = null;

            try {
                // Get CSRF cookie first
                await axios.get('/sanctum/csrf-cookie');

                // Request magic signup link
                await axios.post('/auth/magic-link/signup', {
                    email: this.form.email,
                });

                // Show success message
                this.emailSent = true;
            } catch (err) {
                if (err.response?.status === 429) {
                    this.error = 'Too many requests. Please wait a few minutes.';
                } else if (err.response?.status === 422) {
                    const data = err.response.data;
                    if (data.errors) {
                        this.error = Object.values(data.errors).flat().join(' ');
                    } else {
                        this.error = data.message || 'Invalid email address.';
                    }
                } else {
                    this.error = 'An error occurred. Please try again.';
                }
            } finally {
                this.loading = false;
            }
        },

        async resendEmail() {
            this.resending = true;
            try {
                await axios.get('/sanctum/csrf-cookie');
                await axios.post('/auth/magic-link/signup', {
                    email: this.form.email,
                });
                // Start 60 second cooldown
                this.startCooldown(60);
            } catch (err) {
                if (err.response?.status === 429) {
                    // Rate limited - start longer cooldown
                    this.startCooldown(120);
                    alert('Too many requests. Please wait a few minutes.');
                } else {
                    alert('Failed to resend email. Please try again.');
                }
            } finally {
                this.resending = false;
            }
        },

        startCooldown(seconds) {
            this.resendCooldown = seconds;
            if (this.cooldownTimer) {
                clearInterval(this.cooldownTimer);
            }
            this.cooldownTimer = setInterval(() => {
                this.resendCooldown--;
                if (this.resendCooldown <= 0) {
                    clearInterval(this.cooldownTimer);
                    this.cooldownTimer = null;
                }
            }, 1000);
        },
    },
};
</script>
