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
            <h2 class="mb-0">Chains</h2>
            <router-link to="/chains/create" class="btn btn-cml-primary">
                Create Chain
            </router-link>
        </div>

        <!-- Info banner -->
        <div class="alert alert-info mb-4">
            <strong>Action Chains</strong> let you define multi-step workflows that execute sequentially.
            Each step can be an HTTP call, a human approval gate, or a delay.
        </div>

        <!-- Filters -->
        <div class="card card-cml mb-4">
            <div class="card-body d-flex gap-3">
                <input
                    type="text"
                    class="form-control"
                    placeholder="Search chains..."
                    v-model="searchQuery"
                    style="max-width: 300px;"
                >
                <select class="form-select" v-model="statusFilter" style="max-width: 200px;">
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="running">Running</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                    <option value="cancelled">Cancelled</option>
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
        <div v-else-if="chains.length === 0" class="text-center py-5">
            <template v-if="searchQuery || statusFilter">
                <h5 class="text-muted mb-2">No chains found</h5>
                <p class="text-muted mb-4">Try adjusting your search or filters.</p>
                <button class="btn btn-outline-secondary" @click="clearFilters">
                    Clear filters
                </button>
            </template>
            <template v-else>
                <h5 class="text-muted mb-2">No chains yet</h5>
                <p class="text-muted mb-4">Chains let you orchestrate multi-step workflows with human approval checkpoints.</p>
                <router-link to="/chains/create" class="btn btn-cml-primary">
                    Create your first chain
                </router-link>
            </template>
        </div>

        <!-- Chains table -->
        <div v-else class="card card-cml">
            <div class="table-responsive">
                <table class="table table-cml mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th class="text-muted">Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="chain in chains" :key="chain.id">
                            <td>
                                <router-link :to="`/chains/${chain.id}`" class="text-decoration-none">
                                    {{ chain.name }}
                                </router-link>
                            </td>
                            <td>
                                <div class="progress" style="width: 100px; height: 8px;">
                                    <div
                                        class="progress-bar"
                                        :class="progressBarClass(chain.status)"
                                        :style="{ width: progressPercent(chain) + '%' }"
                                    ></div>
                                </div>
                                <small class="text-muted">
                                    {{ chain.current_step }}/{{ chain.total_steps }} steps
                                </small>
                            </td>
                            <td>
                                <span :class="['badge', statusBadgeClass(chain.status)]">
                                    {{ formatStatus(chain.status) }}
                                </span>
                            </td>
                            <td>{{ chain.started_at ? formatDate(chain.started_at) : '-' }}</td>
                            <td class="text-muted">{{ formatDate(chain.created_at) }}</td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                                        &#8943;
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <router-link :to="`/chains/${chain.id}`" class="dropdown-item">
                                                View Details
                                            </router-link>
                                        </li>
                                        <li v-if="canCancel(chain.status)">
                                            <button class="dropdown-item text-danger" @click="confirmCancelChain(chain)">
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
                        <button class="page-link" @click="loadChains(meta.current_page - 1)">&laquo;</button>
                    </li>
                    <li
                        v-for="page in paginationPages"
                        :key="page"
                        class="page-item"
                        :class="{ active: page === meta.current_page, disabled: page === '...' }"
                    >
                        <button class="page-link" @click="page !== '...' && loadChains(page)">
                            {{ page }}
                        </button>
                    </li>
                    <li class="page-item" :class="{ disabled: meta.current_page === meta.last_page }">
                        <button class="page-link" @click="loadChains(meta.current_page + 1)">&raquo;</button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { formatDate } from '../utils/dateFormatting';
import ConfirmModal from '../components/ConfirmModal.vue';

export default {
    name: 'Chains',
    components: {
        ConfirmModal,
    },
    inject: ['toast'],
    data() {
        return {
            chains: [],
            meta: {},
            loading: true,
            searchQuery: '',
            statusFilter: '',
            searchDebounce: null,
            refreshInterval: null,
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
                for (let i = 1; i <= last; i++) pages.push(i);
            } else {
                pages.push(1);
                if (current > 3) pages.push('...');
                const start = Math.max(2, current - 1);
                const end = Math.min(last - 1, current + 1);
                for (let i = start; i <= end; i++) pages.push(i);
                if (current < last - 2) pages.push('...');
                pages.push(last);
            }
            return pages;
        }
    },
    mounted() {
        this.loadChains();
        this.startAutoRefresh();
    },
    beforeUnmount() {
        this.stopAutoRefresh();
    },
    watch: {
        searchQuery() {
            clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => this.loadChains(), 300);
        },
        statusFilter() {
            this.loadChains();
        }
    },
    methods: {
        formatDate,
        formatStatus(status) {
            const labels = {
                pending: 'Pending',
                running: 'Running',
                completed: 'Completed',
                failed: 'Failed',
                cancelled: 'Cancelled',
            };
            return labels[status] || status;
        },
        statusBadgeClass(status) {
            const classes = {
                pending: 'bg-secondary',
                running: 'bg-primary',
                completed: 'bg-success',
                failed: 'bg-danger',
                cancelled: 'bg-warning text-dark',
            };
            return classes[status] || 'bg-secondary';
        },
        progressBarClass(status) {
            const classes = {
                pending: 'bg-secondary',
                running: 'bg-primary',
                completed: 'bg-success',
                failed: 'bg-danger',
                cancelled: 'bg-warning',
            };
            return classes[status] || 'bg-secondary';
        },
        progressPercent(chain) {
            if (chain.total_steps === 0) return 0;
            if (chain.status === 'completed') return 100;
            return Math.round((chain.current_step / chain.total_steps) * 100);
        },
        canCancel(status) {
            return ['pending', 'running'].includes(status);
        },
        async loadChains(page = 1, silent = false) {
            if (!silent) this.loading = true;
            try {
                const params = { page };
                if (this.searchQuery) params.search = this.searchQuery;
                if (this.statusFilter) params.status = this.statusFilter;

                const response = await axios.get('/api/v1/chains', { params });
                this.chains = response.data.data;
                this.meta = response.data.meta || {};
            } catch (err) {
                console.error('Failed to load chains:', err);
            } finally {
                this.loading = false;
            }
        },
        startAutoRefresh() {
            this.refreshInterval = setInterval(() => {
                if (!document.hidden) {
                    this.loadChains(this.meta.current_page || 1, true);
                }
            }, 30000);
        },
        stopAutoRefresh() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        },
        confirmCancelChain(chain) {
            this.confirmModal = {
                show: true,
                title: 'Cancel Chain',
                message: `Cancel "${chain.name}"? All pending steps will be cancelled.`,
                confirmText: 'Yes, Cancel',
                variant: 'danger',
                action: 'doCancelChain',
                data: chain,
            };
        },
        handleConfirm() {
            const { action, data } = this.confirmModal;
            this.confirmModal.show = false;
            if (action && typeof this[action] === 'function') {
                this[action](data);
            }
        },
        async doCancelChain(chain) {
            try {
                await axios.delete(`/api/v1/chains/${chain.id}`);
                this.toast.success('Chain cancelled');
                this.loadChains();
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to cancel chain');
            }
        },
        clearFilters() {
            this.searchQuery = '';
            this.statusFilter = '';
            this.loadChains();
        }
    }
};
</script>

<style scoped>
.table-responsive {
    overflow: visible;
}

@media (max-width: 768px) {
    .table-responsive {
        overflow-x: auto;
    }
}
</style>
