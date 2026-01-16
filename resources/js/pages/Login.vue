<template>
    <div class="container">
        <div class="row justify-content-center py-5">
            <div class="col-lg-8 col-md-10">
                <div class="text-center mb-4">
                    <h2><strong>Log In to CallMeLater</strong></h2>
                </div>

                <!-- Error from URL params (e.g., magic link errors) -->
                <div v-if="urlError" class="alert alert-danger text-center mb-4" role="alert">
                    {{ urlErrorMessage }}
                </div>

                <div class="row">
                    <!-- Left Column: Magic Link Login -->
                    <div class="col-md-5 d-flex">
                        <div class="card card-cml p-4 w-100 d-flex flex-column">
                            <!-- Success state -->
                            <div v-if="magicLinkSent" class="text-center">
                                <div class="mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                        <polyline points="22 4 12 14.01 9 11.01"/>
                                    </svg>
                                </div>
                                <h5>Check your email</h5>
                                <p class="text-muted small mb-0">
                                    We've sent a magic link to <strong>{{ magicLinkForm.email }}</strong>.
                                    Click the link to log in.
                                </p>
                            </div>

                            <!-- Form state -->
                            <form v-else @submit.prevent="sendMagicLink" class="d-flex flex-column flex-grow-1">
                                <div class="flex-grow-1">
                                    <p class="mb-3">Enter your <strong>email address</strong>.</p>

                                    <div class="mb-3">
                                        <input
                                            type="email"
                                            class="form-control"
                                            placeholder="you@example.org"
                                            v-model="magicLinkForm.email"
                                            required
                                        >
                                    </div>

                                    <p class="text-muted small mb-3">
                                        <em>We will email you a magic log in link.</em>
                                    </p>

                                    <div v-if="magicLinkError" class="alert alert-danger small" role="alert">
                                        {{ magicLinkError }}
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success w-100" :disabled="magicLinkLoading">
                                    <span v-if="magicLinkLoading">Sending...</span>
                                    <span v-else>Email Me a Link</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="col-md-2 d-flex align-items-center justify-content-center">
                        <div class="text-muted py-4">or</div>
                    </div>

                    <!-- Right Column: Password Login -->
                    <div class="col-md-5 d-flex">
                        <div class="card card-cml p-4 w-100 d-flex flex-column">
                            <form @submit.prevent="login" class="d-flex flex-column flex-grow-1">
                                <div class="flex-grow-1">
                                    <p class="mb-3">Enter your <strong>email address</strong> and <strong>password</strong>.</p>

                                    <div class="mb-3">
                                        <input
                                            type="email"
                                            class="form-control"
                                            placeholder="you@example.org"
                                            v-model="form.email"
                                            required
                                        >
                                    </div>

                                    <div class="mb-3">
                                        <input
                                            type="password"
                                            class="form-control"
                                            placeholder="your password"
                                            v-model="form.password"
                                            required
                                        >
                                    </div>

                                    <div class="mb-3 text-end">
                                        <a href="#" class="small text-decoration-none" @click.prevent="showLostPasswordModal = true">
                                            Lost your password?
                                        </a>
                                    </div>

                                    <div v-if="error" class="alert alert-danger small" role="alert">
                                        {{ error }}
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success w-100" :disabled="loading">
                                    <span v-if="loading">Signing in...</span>
                                    <span v-else>Log In</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <router-link to="/register" class="text-decoration-none">
                        Don't have an account? Sign up
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Lost Password Modal -->
        <div v-if="showLostPasswordModal" class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Lost Password?</h5>
                        <button type="button" class="btn-close" @click="showLostPasswordModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <p>If you need to reset your password, please do the following:</p>
                        <ol>
                            <li>Log in using the <strong>Email Me a Link</strong> method.</li>
                            <li>Once in your account, go to <strong>Settings &rarr; Set Password</strong> to set a new password.</li>
                        </ol>
                        <p class="text-muted small mb-0">
                            Please contact us at <a href="mailto:support@callmelater.io">support@callmelater.io</a> in case you need assistance!
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="showLostPasswordModal = false">Got it</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'Login',
    data() {
        return {
            // Password login form
            form: {
                email: '',
                password: '',
            },
            loading: false,
            error: null,

            // Magic link form
            magicLinkForm: {
                email: '',
            },
            magicLinkLoading: false,
            magicLinkError: null,
            magicLinkSent: false,

            // Modal
            showLostPasswordModal: false,

            // URL error params
            urlError: null,
        };
    },
    computed: {
        urlErrorMessage() {
            const messages = {
                'invalid_link': 'Invalid or expired magic link. Please request a new one.',
                'link_expired': 'This magic link has expired. Please request a new one.',
                'link_already_used': 'This magic link has already been used. Please request a new one.',
                'user_not_found': 'User not found. Please sign up first.',
            };
            return messages[this.urlError] || 'An error occurred. Please try again.';
        },
    },
    mounted() {
        // Check for error in URL params
        const params = new URLSearchParams(window.location.search);
        this.urlError = params.get('error');

        // Pre-fill email from URL params (e.g., from invitation redirect)
        const email = params.get('email');
        if (email) {
            this.form.email = email;
            this.magicLinkForm.email = email;
        }
    },
    methods: {
        async login() {
            this.loading = true;
            this.error = null;

            try {
                // Get CSRF cookie first
                await axios.get('/sanctum/csrf-cookie');

                // Attempt login
                await axios.post('/login', {
                    email: this.form.email,
                    password: this.form.password,
                });

                // Store a flag that user is authenticated
                localStorage.setItem('token', 'authenticated');

                // Redirect to original destination or dashboard
                const redirect = this.$route.query.redirect;
                if (redirect && redirect.startsWith('/')) {
                    this.$router.push(redirect);
                } else {
                    this.$router.push({ name: 'dashboard' });
                }
            } catch (err) {
                if (err.response?.status === 422) {
                    this.error = err.response.data.message || 'Invalid credentials';
                } else {
                    this.error = 'An error occurred. Please try again.';
                }
            } finally {
                this.loading = false;
            }
        },

        async sendMagicLink() {
            this.magicLinkLoading = true;
            this.magicLinkError = null;

            try {
                // Get CSRF cookie first
                await axios.get('/sanctum/csrf-cookie');

                // Store redirect URL for after magic link verification
                const redirect = this.$route.query.redirect;
                if (redirect) {
                    localStorage.setItem('auth_redirect', redirect);
                }

                // Request magic link
                await axios.post('/auth/magic-link/send', {
                    email: this.magicLinkForm.email,
                });

                this.magicLinkSent = true;
            } catch (err) {
                if (err.response?.status === 429) {
                    this.magicLinkError = 'Too many requests. Please wait a few minutes.';
                } else if (err.response?.status === 422) {
                    this.magicLinkError = err.response.data.message || 'Invalid email address.';
                } else {
                    this.magicLinkError = 'An error occurred. Please try again.';
                }
            } finally {
                this.magicLinkLoading = false;
            }
        },
    },
};
</script>

<style scoped>
.modal.d-block {
    display: block !important;
}
</style>
