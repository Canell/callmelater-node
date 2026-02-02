<template>
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Inbox</h2>
        </div>

        <!-- Filters -->
        <div class="card card-cml mb-4">
            <div class="card-body d-flex flex-wrap gap-3">
                <input
                    type="text"
                    class="form-control"
                    placeholder="Search by email or name..."
                    v-model="searchQuery"
                    style="max-width: 250px;"
                >
                <select class="form-select" v-model="responseTypeFilter" style="max-width: 160px;">
                    <option value="">All responses</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="declined">Declined</option>
                    <option value="snoozed">Snoozed</option>
                </select>
                <input
                    type="date"
                    class="form-control"
                    v-model="dateFrom"
                    placeholder="From"
                    style="max-width: 160px;"
                >
                <input
                    type="date"
                    class="form-control"
                    v-model="dateTo"
                    placeholder="To"
                    style="max-width: 160px;"
                >
            </div>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-muted" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="responses.length === 0" class="text-center py-5">
            <template v-if="searchQuery || responseTypeFilter || dateFrom || dateTo">
                <h5 class="text-muted mb-2">No responses found</h5>
                <p class="text-muted mb-4">Try adjusting your search or filters.</p>
                <button class="btn btn-outline-secondary" @click="clearFilters">
                    Clear filters
                </button>
            </template>
            <template v-else>
                <h5 class="text-muted mb-2">No responses yet</h5>
                <p class="text-muted mb-4">Responses will appear here when recipients respond to your reminders.</p>
            </template>
        </div>

        <!-- Responses table -->
        <div v-else class="card card-cml">
            <div class="table-responsive">
                <table class="table table-cml mb-0">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Responder</th>
                            <th>Response</th>
                            <th>Comment</th>
                            <th>Responded At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="response in responses" :key="response.id">
                            <td>
                                <router-link :to="`/actions/${response.action_id}`" class="text-decoration-none">
                                    {{ response.action_name }}
                                </router-link>
                            </td>
                            <td>
                                <span :title="response.responder_email">{{ response.responder }}</span>
                            </td>
                            <td>
                                <span :class="['badge', responseBadgeClass(response.response_type)]">
                                    {{ formatResponseType(response.response_type) }}
                                </span>
                            </td>
                            <td class="comment-cell">
                                <span v-if="response.comment" :title="response.comment" class="text-muted">
                                    {{ truncateComment(response.comment) }}
                                </span>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td>{{ formatDate(response.responded_at) }}</td>
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
                        <button class="page-link" @click="loadResponses(meta.current_page - 1)">&laquo;</button>
                    </li>
                    <li
                        v-for="page in paginationPages"
                        :key="page"
                        class="page-item"
                        :class="{ active: page === meta.current_page, disabled: page === '...' }"
                    >
                        <button
                            class="page-link"
                            @click="page !== '...' && loadResponses(page)"
                            :disabled="page === '...'"
                        >
                            {{ page }}
                        </button>
                    </li>
                    <li class="page-item" :class="{ disabled: meta.current_page === meta.last_page }">
                        <button class="page-link" @click="loadResponses(meta.current_page + 1)">&raquo;</button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { formatDate } from '../utils/dateFormatting';

export default {
    name: 'Responses',
    inject: ['toast'],
    data() {
        return {
            responses: [],
            meta: {},
            loading: true,
            searchQuery: '',
            responseTypeFilter: '',
            dateFrom: '',
            dateTo: '',
            // Auto-refresh
            refreshInterval: null,
            refreshSeconds: 30,
            // Search debounce
            searchDebounce: null,
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
        this.loadResponses();
        this.startAutoRefresh();
    },
    beforeUnmount() {
        this.stopAutoRefresh();
    },
    watch: {
        searchQuery() {
            clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => {
                this.loadResponses();
            }, 300);
        },
        responseTypeFilter() {
            this.loadResponses();
        },
        dateFrom() {
            this.loadResponses();
        },
        dateTo() {
            this.loadResponses();
        }
    },
    methods: {
        formatDate,
        responseBadgeClass(type) {
            const classes = {
                confirmed: 'bg-success',
                declined: 'bg-danger',
                snoozed: 'bg-warning text-dark',
            };
            return classes[type] || 'bg-secondary';
        },
        formatResponseType(type) {
            const labels = {
                confirmed: 'Confirmed',
                declined: 'Declined',
                snoozed: 'Snoozed',
            };
            return labels[type] || type;
        },
        truncateComment(comment, maxLength = 50) {
            if (!comment) return '';
            if (comment.length <= maxLength) return comment;
            return comment.substring(0, maxLength) + '...';
        },
        async loadResponses(page = 1, silent = false) {
            if (!silent) this.loading = true;
            try {
                const params = { page };
                if (this.searchQuery) params.search = this.searchQuery;
                if (this.responseTypeFilter) params.response_type = this.responseTypeFilter;
                if (this.dateFrom) params.date_from = this.dateFrom;
                if (this.dateTo) params.date_to = this.dateTo;

                const response = await axios.get('/api/v1/responses', { params });
                this.responses = response.data.data;
                this.meta = response.data.meta;
            } catch (err) {
                console.error('Failed to load responses:', err);
                if (!silent) {
                    this.toast.error('Failed to load responses');
                }
            } finally {
                this.loading = false;
            }
        },
        startAutoRefresh() {
            if (this.refreshInterval) return;
            this.refreshInterval = setInterval(() => {
                if (!document.hidden) {
                    this.loadResponses(this.meta.current_page || 1, true);
                }
            }, this.refreshSeconds * 1000);
        },
        stopAutoRefresh() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        },
        clearFilters() {
            this.searchQuery = '';
            this.responseTypeFilter = '';
            this.dateFrom = '';
            this.dateTo = '';
            this.loadResponses();
        }
    }
};
</script>

<style scoped>
/* Comment cell truncation */
.comment-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Fix dropdown being clipped by table-responsive overflow */
.table-responsive {
    overflow: visible;
}

@media (max-width: 768px) {
    .table-responsive {
        overflow-x: auto;
    }
}
</style>
