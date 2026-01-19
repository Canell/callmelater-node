<template>
    <div id="app">
        <!-- Toast Notifications -->
        <Toast ref="toast" />

        <!-- Top Navigation -->
        <nav v-if="!hideNavFooter" class="navbar navbar-expand-lg navbar-cml">
            <div class="container">
                <!-- Logo (regular link - home page served by Blade) -->
                <a class="navbar-brand d-flex align-items-center" href="/">
                    <span class="logo-icon me-2">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" stroke="#22C55E" stroke-width="2"/>
                            <path d="M12 6v6l4 2" stroke="#22C55E" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="fw-semibold">CallMeLater</span>
                </a>

                <!-- Mobile toggle -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Nav links -->
                <div class="collapse navbar-collapse" id="navbarNav">
                    <!-- Left side links (regular links for SEO - pages served by Blade) -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="/use-cases">Use Cases</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/pricing">Pricing</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :href="docsUrl">Docs</a>
                        </li>
                    </ul>

                    <!-- Right side links -->
                    <ul class="navbar-nav">
                        <template v-if="isAuthenticated">
                            <li class="nav-item">
                                <router-link class="nav-link" to="/dashboard">Dashboard</router-link>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    Account
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><router-link class="dropdown-item" to="/settings">Settings</router-link></li>
                                    <li><router-link class="dropdown-item" to="/admin" v-if="isAdmin">Admin</router-link></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" @click.prevent="logout">Log Out</a></li>
                                </ul>
                            </li>
                        </template>
                        <template v-else>
                            <li class="nav-item">
                                <router-link class="nav-link" to="/login">Log In</router-link>
                            </li>
                            <li class="nav-item">
                                <router-link class="btn btn-cml-primary btn-sm ms-2" to="/register">Sign Up</router-link>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Email Verification Banner -->
        <div v-if="showVerificationBanner" class="verification-banner">
            <div class="container d-flex align-items-center justify-content-between py-2">
                <span>
                    <strong>Please verify your email address.</strong>
                    Check your inbox for a verification link.
                </span>
                <button class="btn btn-sm btn-outline-light" @click="resendVerification" :disabled="resending">
                    {{ resending ? 'Sending...' : 'Resend Email' }}
                </button>
            </div>
        </div>

        <!-- Main content -->
        <main>
            <router-view />
        </main>

        <!-- Footer (only on public pages) -->
        <footer class="footer-cml" v-if="showFooter && !hideNavFooter">
            <div class="container">
                <div class="row">
                    <div class="col-12 col-md-4 mb-4 mb-md-0">
                        <div class="d-flex align-items-center mb-3">
                            <span class="logo-icon me-2">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="10" stroke="#22C55E" stroke-width="2"/>
                                    <path d="M12 6v6l4 2" stroke="#22C55E" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <span class="fw-semibold">CallMeLater</span>
                        </div>
                        <p class="text-muted small mb-0">
                            Reliable scheduled actions for developers.<br>
                            Never miss a webhook again.
                        </p>
                    </div>
                    <div class="col-6 col-md-2">
                        <h6 class="footer-heading">Product</h6>
                        <ul class="list-unstyled footer-links">
                            <li><a href="/use-cases">Use Cases</a></li>
                            <li><a href="/pricing">Pricing</a></li>
                            <li><a :href="docsUrl">Docs</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-2">
                        <h6 class="footer-heading">Resources</h6>
                        <ul class="list-unstyled footer-links">
                            <li><a :href="statusUrl">Status</a></li>
                            <li><a href="/contact">Contact</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-2">
                        <h6 class="footer-heading">Legal</h6>
                        <ul class="list-unstyled footer-links">
                            <li><a href="/terms">Terms</a></li>
                            <li><a href="/privacy">Privacy</a></li>
                            <li><a href="/cookies">Cookies</a></li>
                        </ul>
                    </div>
                </div>
                <hr class="footer-divider">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <p class="text-muted small mb-2 mb-md-0">
                        &copy; {{ currentYear }} CallMeLater. All rights reserved.
                    </p>
                    <p class="text-muted small mb-0">
                        Made with care in Rixensart, Belgium.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<script>
import axios from 'axios';
import Toast from './components/Toast.vue';

export default {
    name: 'App',
    components: {
        Toast,
    },
    data() {
        return {
            user: null,
            resending: false,
            authToken: localStorage.getItem('token'),
        };
    },
    provide() {
        return {
            toast: {
                success: (msg, title) => this.$refs.toast?.success(msg, title),
                error: (msg, title) => this.$refs.toast?.error(msg, title),
                warning: (msg, title) => this.$refs.toast?.warning(msg, title),
                info: (msg, title) => this.$refs.toast?.info(msg, title),
            },
        };
    },
    computed: {
        appDomain() {
            return import.meta.env.VITE_APP_DOMAIN || 'callmelater.io';
        },
        docsUrl() {
            return `https://docs.${this.appDomain}`;
        },
        statusUrl() {
            return '/status';
        },
        isAuthenticated() {
            return !!this.authToken;
        },
        isAdmin() {
            return this.user?.is_admin === true;
        },
        showFooter() {
            // Show footer on auth pages (login, register)
            // Note: Marketing pages are now served by Blade with their own footer
            const publicRoutes = ['login', 'register'];
            return publicRoutes.includes(this.$route.name);
        },
        hideNavFooter() {
            return this.$route.meta?.hideNavFooter === true;
        },
        currentYear() {
            return new Date().getFullYear();
        },
        emailVerified() {
            return this.user?.email_verified_at != null;
        },
        showVerificationBanner() {
            return this.isAuthenticated && this.user && !this.emailVerified && !this.hideNavFooter;
        }
    },
    async mounted() {
        if (this.isAuthenticated) {
            await this.fetchUser();
        }
    },
    methods: {
        async fetchUser() {
            try {
                const response = await axios.get('/api/user');
                this.user = response.data;
                // Store user's timezone for use in date formatting
                if (this.user.timezone) {
                    localStorage.setItem('userTimezone', this.user.timezone);
                }
            } catch (err) {
                // Only logout on 401 Unauthorized (session expired)
                // 419 CSRF errors are handled by axios interceptor
                if (err.response?.status === 401) {
                    localStorage.removeItem('token');
                    localStorage.removeItem('userTimezone');
                    this.authToken = null;
                    this.user = null;
                }
            }
        },
        async logout() {
            try {
                await axios.post('/logout');
            } catch (err) {
                // Ignore logout errors
            }
            localStorage.removeItem('token');
            localStorage.removeItem('userTimezone');
            this.authToken = null;
            this.user = null;
            // Redirect to home page (served by Blade)
            window.location.href = '/';
        },
        async resendVerification() {
            this.resending = true;
            try {
                await axios.post('/email/verification-notification');
                this.$refs.toast?.success('Verification email sent! Please check your inbox.');
            } catch (err) {
                this.$refs.toast?.error('Failed to send verification email. Please try again.');
            } finally {
                this.resending = false;
            }
        }
    },
    watch: {
        // Watch route changes to detect magic link auth and sync auth state
        '$route'() {
            // Sync authToken with localStorage (detects magic link auth)
            const currentToken = localStorage.getItem('token');
            if (this.authToken !== currentToken) {
                this.authToken = currentToken;
            }
            // If authenticated but no user data, fetch it
            if (this.authToken && !this.user) {
                this.fetchUser();
            }
        }
    }
};
</script>

<style>
/* Navbar */
.navbar-cml {
    background-color: #fff;
    border-bottom: 1px solid #e5e7eb;
    padding: 0.75rem 0;
}

.navbar-cml .navbar-brand {
    color: #111827;
    font-size: 1.125rem;
}

.navbar-cml .nav-link {
    color: #6b7280;
    font-size: 0.9375rem;
    padding: 0.5rem 1rem;
}

.navbar-cml .nav-link:hover {
    color: #111827;
}

.navbar-cml .nav-link.router-link-active {
    color: #22C55E;
}

/* Footer */
.footer-cml {
    background-color: #f9fafb;
    border-top: 1px solid #e5e7eb;
    padding: 3rem 0 2rem;
    margin-top: auto;
}

.footer-heading {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.footer-links li {
    margin-bottom: 0.5rem;
}

.footer-links a {
    color: #6b7280;
    text-decoration: none;
    font-size: 0.875rem;
}

.footer-links a:hover {
    color: #22C55E;
}

.footer-divider {
    border-color: #e5e7eb;
    margin: 2rem 0 1.5rem;
}

/* Make the app full height for footer positioning */
#app {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

main {
    flex: 1;
}

/* Verification Banner */
.verification-banner {
    background-color: #f59e0b;
    color: white;
    font-size: 0.875rem;
}

.verification-banner .btn-outline-light {
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
}

.verification-banner .btn-outline-light:hover {
    background-color: rgba(255, 255, 255, 0.1);
    border-color: white;
}
</style>
