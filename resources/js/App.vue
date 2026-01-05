<template>
    <div id="app">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-cml">
            <div class="container">
                <!-- Logo -->
                <router-link class="navbar-brand d-flex align-items-center" to="/">
                    <span class="logo-icon me-2">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" stroke="#22C55E" stroke-width="2"/>
                            <path d="M12 6v6l4 2" stroke="#22C55E" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="fw-semibold">CallMeLater</span>
                </router-link>

                <!-- Mobile toggle -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Nav links -->
                <div class="collapse navbar-collapse" id="navbarNav">
                    <!-- Left side links -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <router-link class="nav-link" to="/pricing">Pricing</router-link>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="https://docs.callmelater.io">Docs</a>
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

        <!-- Main content -->
        <main>
            <router-view />
        </main>

        <!-- Footer (only on public pages) -->
        <footer class="footer-cml" v-if="showFooter">
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
                            <li><router-link to="/pricing">Pricing</router-link></li>
                            <li><a href="https://docs.callmelater.io">Docs</a></li>
                            <li><a href="https://docs.callmelater.io/api">API</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-2">
                        <h6 class="footer-heading">Resources</h6>
                        <ul class="list-unstyled footer-links">
                            <li><a href="https://status.callmelater.io">Status</a></li>
                            <li><a href="https://blog.callmelater.io">Blog</a></li>
                            <li><a href="mailto:support@callmelater.io">Contact</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-2">
                        <h6 class="footer-heading">Legal</h6>
                        <ul class="list-unstyled footer-links">
                            <li><a href="/terms">Terms</a></li>
                            <li><a href="/privacy">Privacy</a></li>
                        </ul>
                    </div>
                    <div class="col-6 col-md-2">
                        <h6 class="footer-heading">Connect</h6>
                        <ul class="list-unstyled footer-links">
                            <li><a href="https://github.com/callmelater">GitHub</a></li>
                            <li><a href="https://twitter.com/callmelater">Twitter</a></li>
                        </ul>
                    </div>
                </div>
                <hr class="footer-divider">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <p class="text-muted small mb-2 mb-md-0">
                        &copy; {{ currentYear }} CallMeLater. All rights reserved.
                    </p>
                    <p class="text-muted small mb-0">
                        Made with care for developers who value reliability.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'App',
    data() {
        return {
            user: null,
        };
    },
    computed: {
        isAuthenticated() {
            return !!localStorage.getItem('token');
        },
        isAdmin() {
            return this.user?.is_admin === true;
        },
        showFooter() {
            // Show footer on public pages (home, pricing, login, register)
            const publicRoutes = ['home', 'pricing', 'login', 'register'];
            return publicRoutes.includes(this.$route.name);
        },
        currentYear() {
            return new Date().getFullYear();
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
            } catch (err) {
                // Token might be invalid
                localStorage.removeItem('token');
            }
        },
        async logout() {
            try {
                await axios.post('/logout');
            } catch (err) {
                // Ignore logout errors
            }
            localStorage.removeItem('token');
            this.user = null;
            this.$router.push({ name: 'home' });
        }
    },
    watch: {
        isAuthenticated(newVal) {
            if (newVal) {
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
</style>
