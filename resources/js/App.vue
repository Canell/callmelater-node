<template>
    <div id="app">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white navbar-cml" v-if="isAuthenticated">
            <div class="container-fluid">
                <router-link class="navbar-brand" to="/">
                    <strong>CallMeLater</strong>
                </router-link>

                <div class="d-flex align-items-center">
                    <a href="#" class="text-decoration-none text-muted me-3">Pricing</a>
                    <a href="#" class="text-decoration-none text-muted me-3">Docs</a>
                    <a href="#" class="text-decoration-none text-muted me-3">Blog</a>
                    <a href="#" class="text-decoration-none text-muted me-3">About</a>
                    <div class="dropdown">
                        <a href="#" class="text-decoration-none text-dark dropdown-toggle" data-bs-toggle="dropdown">
                            Account
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" @click.prevent="logout">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main content -->
        <main>
            <router-view />
        </main>
    </div>
</template>

<script>
export default {
    name: 'App',
    computed: {
        isAuthenticated() {
            return !!localStorage.getItem('token');
        }
    },
    methods: {
        logout() {
            localStorage.removeItem('token');
            this.$router.push({ name: 'login' });
        }
    }
};
</script>
