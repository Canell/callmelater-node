import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';

// Import pages
import Home from './pages/Home.vue';
import Pricing from './pages/Pricing.vue';
import Dashboard from './pages/Dashboard.vue';
import Login from './pages/Login.vue';
import Register from './pages/Register.vue';
import CreateAction from './pages/CreateAction.vue';
import ActionDetail from './pages/ActionDetail.vue';
import Admin from './pages/Admin.vue';
import AdminStatus from './pages/AdminStatus.vue';
import UseCases from './pages/UseCases.vue';
import Status from './pages/Status.vue';
import ConsentResult from './pages/ConsentResult.vue';
import Settings from './pages/Settings.vue';
import Contact from './pages/Contact.vue';
import Terms from './pages/Terms.vue';
import Privacy from './pages/Privacy.vue';
import Cookies from './pages/Cookies.vue';
import SubscriptionResult from './pages/SubscriptionResult.vue';

// Define routes
const routes = [
    // Public marketing pages
    { path: '/', name: 'home', component: Home },
    { path: '/pricing', name: 'pricing', component: Pricing },
    { path: '/use-cases', name: 'use-cases', component: UseCases },
    { path: '/contact', name: 'contact', component: Contact },
    { path: '/terms', name: 'terms', component: Terms },
    { path: '/privacy', name: 'privacy', component: Privacy },
    { path: '/cookies', name: 'cookies', component: Cookies },
    { path: '/status', name: 'status', component: Status, meta: { hideNavFooter: true } },

    // Consent result pages (public, no auth required)
    { path: '/consent/accepted', name: 'consent-accepted', component: ConsentResult, meta: { hideNavFooter: true } },
    { path: '/consent/declined', name: 'consent-declined', component: ConsentResult, meta: { hideNavFooter: true } },
    { path: '/consent/unsubscribed', name: 'consent-unsubscribed', component: ConsentResult, meta: { hideNavFooter: true } },
    { path: '/consent/error', name: 'consent-error', component: ConsentResult, meta: { hideNavFooter: true } },

    // Subscription result pages
    { path: '/subscription/result', name: 'subscription-result', component: SubscriptionResult, meta: { hideNavFooter: true } },

    // Auth pages
    { path: '/login', name: 'login', component: Login, meta: { guest: true } },
    { path: '/register', name: 'register', component: Register, meta: { guest: true } },

    // App pages (authenticated)
    { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
    { path: '/actions/create', name: 'create-action', component: CreateAction, meta: { requiresAuth: true } },
    { path: '/actions/:id', name: 'action-detail', component: ActionDetail, meta: { requiresAuth: true } },
    { path: '/settings', name: 'settings', component: Settings, meta: { requiresAuth: true } },

    // Admin pages (requires admin role - API enforces)
    { path: '/admin', name: 'admin', component: Admin, meta: { requiresAuth: true } },
    { path: '/admin/status', name: 'admin-status', component: AdminStatus, meta: { requiresAuth: true } },
];

// Create router
const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        // If browser back/forward, restore saved position
        if (savedPosition) {
            return savedPosition;
        }
        // Otherwise scroll to top
        return { top: 0 };
    },
});

// Navigation guard for auth
router.beforeEach((to, from, next) => {
    // Check for magic link auth signal
    if (to.query.auth === 'magic') {
        localStorage.setItem('token', 'authenticated');
        // Remove the query param and continue
        const { auth, ...query } = to.query;
        next({ path: to.path, query, replace: true });
        return;
    }

    const isAuthenticated = localStorage.getItem('token');

    if (to.meta.requiresAuth && !isAuthenticated) {
        next({ name: 'login' });
    } else if (to.meta.guest && isAuthenticated) {
        next({ name: 'dashboard' });
    } else {
        next();
    }
});

// Expose router for use in components
export { router };

// Create and mount app
const app = createApp(App);
app.use(router);
app.mount('#app');
