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
import DocsWebhookSecurity from './pages/DocsWebhookSecurity.vue';

// Define routes
const routes = [
    // Public marketing pages
    { path: '/', name: 'home', component: Home },
    { path: '/pricing', name: 'pricing', component: Pricing },
    { path: '/use-cases', name: 'use-cases', component: UseCases },
    { path: '/status', name: 'status', component: Status, meta: { hideNavFooter: true } },

    // Documentation
    { path: '/docs/webhook-security', name: 'docs-webhook-security', component: DocsWebhookSecurity },

    // Auth pages
    { path: '/login', name: 'login', component: Login, meta: { guest: true } },
    { path: '/register', name: 'register', component: Register, meta: { guest: true } },

    // App pages (authenticated)
    { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
    { path: '/actions/create', name: 'create-action', component: CreateAction, meta: { requiresAuth: true } },
    { path: '/actions/:id', name: 'action-detail', component: ActionDetail, meta: { requiresAuth: true } },

    // Admin pages (requires admin role - API enforces)
    { path: '/admin', name: 'admin', component: Admin, meta: { requiresAuth: true } },
    { path: '/admin/status', name: 'admin-status', component: AdminStatus, meta: { requiresAuth: true } },
];

// Create router
const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Navigation guard for auth
router.beforeEach((to, from, next) => {
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
