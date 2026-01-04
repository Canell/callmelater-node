import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';

// Import pages
import Dashboard from './pages/Dashboard.vue';
import Login from './pages/Login.vue';
import Register from './pages/Register.vue';
import CreateAction from './pages/CreateAction.vue';
import ActionDetail from './pages/ActionDetail.vue';

// Define routes
const routes = [
    { path: '/', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
    { path: '/login', name: 'login', component: Login, meta: { guest: true } },
    { path: '/register', name: 'register', component: Register, meta: { guest: true } },
    { path: '/actions/create', name: 'create-action', component: CreateAction, meta: { requiresAuth: true } },
    { path: '/actions/:id', name: 'action-detail', component: ActionDetail, meta: { requiresAuth: true } },
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

// Create and mount app
const app = createApp(App);
app.use(router);
app.mount('#app');
