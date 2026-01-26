<template>
    <div class="container py-4">
        <!-- Confirm Modal -->
        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirm-text="confirmModal.confirmText"
            :variant="confirmModal.variant"
            @confirm="handleConfirm"
            @cancel="confirmModal.show = false"
        />

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Actions</h2>
            <router-link to="/actions/create" class="btn btn-cml-primary">
                Add Action
            </router-link>
        </div>

        <!-- Quota Widget -->
        <div class="row mb-4">
            <div class="col-md-6 col-lg-4">
                <QuotaWidget />
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
                <select class="form-select" v-model="modeFilter" style="max-width: 150px;">
                    <option value="">All modes</option>
                    <option value="immediate">Immediate</option>
                    <option value="gated">Gated</option>
                </select>
                <select v-if="coordinationKeys.length" class="form-select" v-model="coordinationKeyFilter" style="max-width: 200px;">
                    <option value="">All groups</option>
                    <option v-for="key in coordinationKeys" :key="key" :value="key">
                        {{ key }}
                    </option>
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
            <template v-if="searchQuery || statusFilter || modeFilter || coordinationKeyFilter">
                <h5 class="text-muted mb-2">No actions found</h5>
                <p class="text-muted mb-4">Try adjusting your search or filters.</p>
                <button class="btn btn-outline-secondary" @click="clearFilters">
                    Clear filters
                </button>
            </template>
            <template v-else>
                <h5 class="text-muted mb-2">No actions yet</h5>
                <p class="text-muted mb-4">Actions are scheduled HTTP calls or reminders that run in the future.</p>
                <router-link to="/actions/create" class="btn btn-cml-primary">
                    Create your first action
                </router-link>
            </template>
        </div>

        <!-- Actions table -->
        <div v-else class="card card-cml">
            <div class="table-responsive">
                <table class="table table-cml mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th>Scheduled For</th>
                            <th class="text-muted">Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="action in actions" :key="action.id">
                            <td>
                                <router-link :to="`/actions/${action.id}`" class="text-decoration-none">
                                    {{ action.name }}
                                </router-link>
                            </td>
                            <td>
                                <span class="mode-label">
                                    <span v-if="action.mode === 'immediate'">&#9889;</span>
                                    <span v-else>&#128275;</span>
                                    {{ action.mode === 'immediate' ? 'Immediate' : 'Gated' }}
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
                                            <button class="dropdown-item text-danger" @click="confirmCancelAction(action)">
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
                        <button class="page-link" @click="loadActions(meta.current_page - 1)">&laquo;</button>
                    </li>
                    <li
                        v-for="page in paginationPages"
                        :key="page"
                        class="page-item"
                        :class="{ active: page === meta.current_page, disabled: page === '...' }"
                    >
                        <button
                            class="page-link"
                            @click="page !== '...' && loadActions(page)"
                            :disabled="page === '...'"
                        >
                            {{ page }}
                        </button>
                    </li>
                    <li class="page-item" :class="{ disabled: meta.current_page === meta.last_page }">
                        <button class="page-link" @click="loadActions(meta.current_page + 1)">&raquo;</button>
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
import ConfirmModal from '../components/ConfirmModal.vue';
import QuotaWidget from '../components/QuotaWidget.vue';

const { formatStatus, statusBadgeClass, canCancel } = useActionStatus();

export default {
    name: 'Dashboard',
    components: {
        ConfirmModal,
        QuotaWidget,
    },
    inject: ['toast'],
    data() {
        return {
            actions: [],
            meta: {},
            loading: true,
            searchQuery: '',
            statusFilter: '',
            modeFilter: '',
            coordinationKeyFilter: '',
            coordinationKeys: [],
            // Auto-refresh (runs silently in background)
            refreshInterval: null,
            refreshSeconds: 30,
            // Search debounce
            searchDebounce: null,
            // Confirm modal
            confirmModal: {
                show: false,
                title: '',
                message: '',
                confirmText: 'Confirm',
                variant: 'warning',
                action: null,
                data: null,
            },
        };
    },
    computed: {
        paginationPages() {
            const current = this.meta.current_page;
            const last = this.meta.last_page;
            const pages = [];

            if (last <= 7) {
                // Show all pages if 7 or fewer
                for (let i = 1; i <= last; i++) pages.push(i);
            } else {
                // Always show first page
                pages.push(1);

                if (current > 3) {
                    pages.push('...');
                }

                // Pages around current
                const start = Math.max(2, current - 1);
                const end = Math.min(last - 1, current + 1);
                for (let i = start; i <= end; i++) {
                    pages.push(i);
                }

                if (current < last - 2) {
                    pages.push('...');
                }

                // Always show last page
                pages.push(last);
            }

            return pages;
        }
    },
    mounted() {
        this.loadActions();
        this.loadCoordinationKeys();
        this.startAutoRefresh();
    },
    beforeUnmount() {
        this.stopAutoRefresh();
    },
    watch: {
        searchQuery() {
            // Debounce search to avoid too many API calls
            clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => {
                this.loadActions();
            }, 300);
        },
        statusFilter() {
            this.loadActions();
        },
        modeFilter() {
            this.loadActions();
        },
        coordinationKeyFilter() {
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
                if (this.searchQuery) params.search = this.searchQuery;
                if (this.statusFilter) params.status = this.statusFilter;
                if (this.modeFilter) params.mode = this.modeFilter;
                if (this.coordinationKeyFilter) params.coordination_key = this.coordinationKeyFilter;

                const response = await axios.get('/api/v1/actions', { params });
                this.actions = response.data.data;
                this.meta = response.data.meta;
            } catch (err) {
                console.error('Failed to load actions:', err);
            } finally {
                this.loading = false;
            }
        },
        startAutoRefresh() {
            if (this.refreshInterval) return;
            this.refreshInterval = setInterval(() => {
                if (!document.hidden) {
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
        async loadCoordinationKeys() {
            try {
                const response = await axios.get('/api/v1/coordination-keys');
                this.coordinationKeys = response.data.keys || [];
            } catch (err) {
                console.error('Failed to load coordination keys:', err);
            }
        },
        confirmCancelAction(action) {
            this.confirmModal = {
                show: true,
                title: 'Cancel Action',
                message: `Cancel "${action.name}"? This action will not be executed.`,
                confirmText: 'Yes, Cancel',
                variant: 'danger',
                action: 'doCancelAction',
                data: action,
            };
        },
        handleConfirm() {
            const { action, data } = this.confirmModal;
            this.confirmModal.show = false;
            if (action && typeof this[action] === 'function') {
                this[action](data);
            }
        },
        async doCancelAction(action) {
            try {
                await axios.delete(`/api/v1/actions/${action.id}`);
                this.loadActions();
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to cancel action');
            }
        },
        clearFilters() {
            this.searchQuery = '';
            this.statusFilter = '';
            this.modeFilter = '';
            this.coordinationKeyFilter = '';
            this.loadActions();
        }
    }
};
</script>

<style scoped>
/* Fix dropdown being clipped by table-responsive overflow */
.table-responsive {
    overflow: visible;
}

/* Type label with icon */
.mode-label {
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
