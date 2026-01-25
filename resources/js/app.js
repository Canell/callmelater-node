import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';

// Import pages
// Note: Marketing pages (Home, Pricing, UseCases, Contact, Terms, Privacy, Cookies)
// are now served by Blade templates for SEO
import Dashboard from './pages/Dashboard.vue';
import Responses from './pages/Responses.vue';
import Login from './pages/Login.vue';
import Register from './pages/Register.vue';
import CreateAction from './pages/CreateAction.vue';
import ActionDetail from './pages/ActionDetail.vue';
import Admin from './pages/Admin.vue';
import AdminStatus from './pages/AdminStatus.vue';
import Status from './pages/Status.vue';
import Settings from './pages/Settings.vue';
import SubscriptionResult from './pages/SubscriptionResult.vue';
import AcceptInvitation from './pages/AcceptInvitation.vue';

// Define routes
// Marketing pages (/, /pricing, /use-cases, /contact, /terms, /privacy, /cookies)
// are handled by Laravel and served as Blade templates for SEO
const routes = [
    // Status page (Vue-rendered for real-time updates)
    { path: '/status', name: 'status', component: Status, meta: { hideNavFooter: true } },

    // Subscription result pages
    { path: '/subscription/result', name: 'subscription-result', component: SubscriptionResult, meta: { hideNavFooter: true } },

    // Team invitation
    { path: '/invitations/:token', name: 'accept-invitation', component: AcceptInvitation },

    // Auth pages
    { path: '/login', name: 'login', component: Login, meta: { guest: true } },
    { path: '/register', name: 'register', component: Register, meta: { guest: true } },

    // App pages (authenticated)
    { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
    { path: '/responses', name: 'responses', component: Responses, meta: { requiresAuth: true } },
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

// Handle timezone setup after signup
async function handleSignupTimezone() {
    const signupTimezone = localStorage.getItem('signupTimezone');
    if (!signupTimezone) return;

    try {
        // Update user's timezone in their profile
        await axios.put('/api/user/profile', { timezone: signupTimezone });
        // Store for use in date formatting
        localStorage.setItem('userTimezone', signupTimezone);
    } catch (err) {
        console.error('Failed to set signup timezone:', err);
    } finally {
        // Always clean up
        localStorage.removeItem('signupTimezone');
    }
}

// Navigation guard for auth
router.beforeEach((to, from, next) => {
    // Check for magic link auth signal
    if (to.query.auth === 'magic') {
        localStorage.setItem('token', 'authenticated');

        // Set up timezone for new signups (async, non-blocking)
        handleSignupTimezone();

        // Check for stored redirect from before magic link was sent
        const storedRedirect = localStorage.getItem('auth_redirect');
        if (storedRedirect) {
            localStorage.removeItem('auth_redirect');
            next({ path: storedRedirect, replace: true });
            return;
        }

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
