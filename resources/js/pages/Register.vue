<template>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-4">
                <div class="text-center mb-4">
                    <h2><strong>CallMeLater</strong></h2>
                    <p class="text-muted">Create your account</p>
                </div>

                <!-- Success: Email verification required -->
                <div v-if="registered" class="card card-cml p-4 text-center">
                    <div class="mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                        </svg>
                    </div>
                    <h4>Check your email</h4>
                    <p class="text-muted mb-3">
                        We've sent a verification link to <strong>{{ form.email }}</strong>.
                        Please click the link to activate your account.
                    </p>
                    <p class="small text-muted mb-3">
                        Didn't receive the email? Check your spam folder or
                        <button type="button" class="btn btn-link btn-sm p-0" @click="resendVerification" :disabled="resending">
                            {{ resending ? 'Sending...' : 'resend verification email' }}
                        </button>
                    </p>
                    <router-link to="/login" class="btn btn-cml-primary">
                        Continue to Login
                    </router-link>
                </div>

                <!-- Registration Form -->
                <div v-else class="card card-cml p-4">
                    <form @submit.prevent="register">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input
                                type="text"
                                class="form-control"
                                id="name"
                                v-model="form.name"
                                required
                                autofocus
                            >
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                v-model="form.email"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                v-model="form.password"
                                required
                                minlength="8"
                            >
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                            <input
                                type="password"
                                class="form-control"
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                required
                            >
                        </div>

                        <div v-if="error" class="alert alert-danger" role="alert">
                            {{ error }}
                        </div>

                        <div v-if="errors.length" class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                <li v-for="err in errors" :key="err">{{ err }}</li>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-cml-primary w-100" :disabled="loading">
                            <span v-if="loading">Creating account...</span>
                            <span v-else>Create account</span>
                        </button>
                    </form>
                </div>

                <div v-if="!registered" class="text-center mt-3">
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
                name: '',
                email: '',
                password: '',
                password_confirmation: '',
            },
            loading: false,
            error: null,
            errors: [],
            registered: false,
            resending: false,
        };
    },
    methods: {
        async register() {
            this.loading = true;
            this.error = null;
            this.errors = [];

            try {
                // Get CSRF cookie first
                await axios.get('/sanctum/csrf-cookie');

                // Attempt registration
                await axios.post('/register', {
                    name: this.form.name,
                    email: this.form.email,
                    password: this.form.password,
                    password_confirmation: this.form.password_confirmation,
                });

                // Show verification required message
                this.registered = true;
            } catch (err) {
                if (err.response?.status === 422) {
                    const data = err.response.data;
                    if (data.errors) {
                        this.errors = Object.values(data.errors).flat();
                    } else {
                        this.error = data.message || 'Validation failed';
                    }
                } else {
                    this.error = 'An error occurred. Please try again.';
                }
            } finally {
                this.loading = false;
            }
        },
        async resendVerification() {
            this.resending = true;
            try {
                await axios.post('/email/verification-notification');
                alert('Verification email sent!');
            } catch (err) {
                alert('Failed to send verification email. Please try again.');
            } finally {
                this.resending = false;
            }
        },
    },
};
</script>
