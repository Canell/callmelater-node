<template>
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Actions</h2>
            <div class="d-flex align-items-center gap-3">
                <!-- Auto-refresh indicator -->
                <div class="auto-refresh-indicator d-flex align-items-center gap-2">
                    <button
                        class="btn btn-sm"
                        :class="autoRefresh ? 'btn-outline-success' : 'btn-outline-secondary'"
                        @click="toggleAutoRefresh"
                        :title="autoRefresh ? 'Auto-refresh enabled (click to pause)' : 'Auto-refresh paused (click to resume)'"
                    >
                        <span v-if="autoRefresh" class="refresh-dot"></span>
                        <span v-else>&#10074;&#10074;</span>
                    </button>
                    <small class="text-muted" v-if="lastRefresh">{{ formatLastRefresh() }}</small>
                </div>
                <router-link to="/actions/create" class="btn btn-cml-primary">
                    Add Action
                </router-link>
            </div>
        </div>

        <!-- Filters -->
        <div class="card card-cml mb-4">
            <div class="card-body d-flex gap-3">
                <input
                    type="text"
                    class="form-control"
                    placeholder="Search actions..."
                    v-model="searchQuery"
                    style="max-width: 300px;"
                >
                <select class="form-select" v-model="statusFilter" style="max-width: 200px;">
                    <option value="">All statuses</option>
                    <option value="pending_resolution">Pending</option>
                    <option value="resolved">Scheduled</option>
                    <option value="awaiting_response">Awaiting Response</option>
                    <option value="executed">Executed</option>
                    <option value="failed">Failed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select class="form-select" v-model="typeFilter" style="max-width: 150px;">
                    <option value="">All types</option>
                    <option value="http">HTTP</option>
                    <option value="reminder">Reminder</option>
                </select>
            </div>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-muted" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="actions.length === 0" class="text-center py-5">
            <h5 class="text-muted mb-2">No actions yet</h5>
            <p class="text-muted mb-4">Actions are scheduled HTTP calls or reminders that run in the future.</p>
            <router-link to="/actions/create" class="btn btn-cml-primary">
                Create your first action
            </router-link>
        </div>

        <!-- Actions table -->
        <div v-else class="card card-cml">
            <div class="table-responsive">
                <table class="table table-cml mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Scheduled For</th>
                            <th class="text-muted">Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="action in filteredActions" :key="action.id">
                            <td>
                                <router-link :to="`/actions/${action.id}`" class="text-decoration-none">
                                    {{ action.name }}
                                </router-link>
                            </td>
                            <td>
                                <span class="type-label">
                                    <span v-if="action.type === 'http'">&#128279;</span>
                                    <span v-else>&#128276;</span>
                                    {{ action.type === 'http' ? 'HTTP' : 'Reminder' }}
                                </span>
                            </td>
                            <td>
                                <span :class="['badge', statusBadgeClass(action.status)]">
                                    {{ formatStatus(action.status) }}
                                </span>
                            </td>
                            <td>{{ formatDate(action.execute_at) }}</td>
                            <td class="text-muted">{{ formatDate(action.created_at) }}</td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                                        &#8943;
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <router-link :to="`/actions/${action.id}`" class="dropdown-item">
                                                View Details
                                            </router-link>
                                        </li>
                                        <li>
                                            <router-link :to="`/actions/create?clone=${action.id}`" class="dropdown-item">
                                                Clone
                                            </router-link>
                                        </li>
                                        <li v-if="canCancel(action.status)">
                                            <button class="dropdown-item text-danger" @click="cancelAction(action)">
                                                Cancel
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination">
                    <li class="page-item" :class="{ disabled: meta.current_page === 1 }">
                        <button class="page-link" @click="loadActions(meta.current_page - 1)">Previous</button>
                    </li>
                    <li class="page-item" :class="{ disabled: meta.current_page === meta.last_page }">
                        <button class="page-link" @click="loadActions(meta.current_page + 1)">Next</button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { useActionStatus } from '../composables/useActionStatus';
import { formatDate } from '../utils/dateFormatting';

const { formatStatus, statusBadgeClass, canCancel } = useActionStatus();

export default {
    name: 'Dashboard',
    data() {
        return {
            actions: [],
            meta: {},
            loading: true,
            searchQuery: '',
            statusFilter: '',
            typeFilter: '',
            // Auto-refresh
            autoRefresh: true,
            refreshInterval: null,
            refreshSeconds: 30,
            lastRefresh: null,
        };
    },
    computed: {
        filteredActions() {
            let filtered = this.actions;

            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(a => a.name.toLowerCase().includes(query));
            }

            return filtered;
        }
    },
    mounted() {
        this.loadActions();
        this.startAutoRefresh();
    },
    beforeUnmount() {
        this.stopAutoRefresh();
    },
    watch: {
        statusFilter() {
            this.loadActions();
        },
        typeFilter() {
            this.loadActions();
        }
    },
    methods: {
        formatDate,
        formatStatus,
        statusBadgeClass,
        canCancel,
        async loadActions(page = 1, silent = false) {
            if (!silent) this.loading = true;
            try {
                const params = { page };
                if (this.statusFilter) params.status = this.statusFilter;
                if (this.typeFilter) params.type = this.typeFilter;

                const response = await axios.get('/api/v1/actions', { params });
                this.actions = response.data.data;
                this.meta = response.data.meta;
                this.lastRefresh = new Date();
            } catch (err) {
                console.error('Failed to load actions:', err);
            } finally {
                this.loading = false;
            }
        },
        startAutoRefresh() {
            if (this.refreshInterval) return;
            this.refreshInterval = setInterval(() => {
                if (this.autoRefresh && !document.hidden) {
                    this.loadActions(this.meta.current_page || 1, true);
                }
            }, this.refreshSeconds * 1000);
        },
        stopAutoRefresh() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        },
        toggleAutoRefresh() {
            this.autoRefresh = !this.autoRefresh;
        },
        formatLastRefresh() {
            if (!this.lastRefresh) return '';
            const now = new Date();
            const diff = Math.floor((now - this.lastRefresh) / 1000);
            if (diff < 5) return 'just now';
            if (diff < 60) return `${diff}s ago`;
            return `${Math.floor(diff / 60)}m ago`;
        },
        async cancelAction(action) {
            if (!confirm(`Cancel action "${action.name}"?`)) return;

            try {
                await axios.delete(`/api/v1/actions/${action.id}`);
                this.loadActions();
            } catch (err) {
                alert(err.response?.data?.message || 'Failed to cancel action');
            }
        }
    }
};
</script>

<style scoped>
/* Fix dropdown being clipped by table-responsive overflow */
.table-responsive {
    overflow: visible;
}

/* Auto-refresh indicator */
.refresh-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    background-color: #22c55e;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.4;
    }
}

/* Type label with icon */
.type-label {
    font-size: 0.9em;
    color: #6b7280;
}

/* Re-enable horizontal scroll only when needed on small screens */
@media (max-width: 768px) {
    .table-responsive {
        overflow-x: auto;
    }

    /* Use fixed positioning for dropdown on mobile */
    .table-responsive .dropdown-menu {
        position: fixed;
    }
}
</style>
