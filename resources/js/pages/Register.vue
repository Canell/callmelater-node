<template>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-4">
                <div class="text-center mb-4">
                    <h2><strong>CallMeLater</strong></h2>
                    <p class="text-muted">Create your account</p>
                </div>

                <div class="card card-cml p-4">
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

                <div class="text-center mt-3">
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

                // Store a flag that user is authenticated
                localStorage.setItem('token', 'authenticated');

                // Redirect to dashboard
                this.$router.push({ name: 'dashboard' });
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
    },
};
</script>
