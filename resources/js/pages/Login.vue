<template>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-4">
                <div class="text-center mb-4">
                    <h2><strong>CallMeLater</strong></h2>
                    <p class="text-muted">Sign in to your account</p>
                </div>

                <div class="card card-cml p-4">
                    <form @submit.prevent="login">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                v-model="form.email"
                                required
                                autofocus
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
                            >
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" v-model="form.remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>

                        <div v-if="error" class="alert alert-danger" role="alert">
                            {{ error }}
                        </div>

                        <button type="submit" class="btn btn-cml-primary w-100" :disabled="loading">
                            <span v-if="loading">Signing in...</span>
                            <span v-else>Sign in</span>
                        </button>
                    </form>
                </div>

                <div class="text-center mt-3">
                    <router-link to="/register" class="text-decoration-none">
                        Don't have an account? Sign up
                    </router-link>
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
            form: {
                email: '',
                password: '',
                remember: false,
            },
            loading: false,
            error: null,
        };
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
                    remember: this.form.remember,
                });

                // Store a flag that user is authenticated
                localStorage.setItem('token', 'authenticated');

                // Redirect to dashboard
                this.$router.push({ name: 'dashboard' });
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
    },
};
</script>
