<template>
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Admin Dashboard</h2>
            <div class="d-flex gap-2">
                <router-link to="/admin/status" class="btn btn-outline-primary">
                    Status Page
                </router-link>
                <button class="btn btn-outline-secondary" @click="refreshAll" :disabled="loading">
                    <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                    Refresh
                </button>
            </div>
        </div>

        <!-- Health Status Banner -->
        <div v-if="health" class="alert mb-4" :class="healthAlertClass">
            <div class="d-flex align-items-center">
                <strong class="me-2">System Status:</strong>
                <span class="text-capitalize">{{ health.status }}</span>
                <span class="ms-auto text-muted small">Last checked: {{ formatTime(health.checked_at) }}</span>
            </div>
            <div v-if="health.errors.length > 0" class="mt-2">
                <div v-for="error in health.errors" :key="error.type" class="small">
                    {{ error.message }}
                </div>
            </div>
            <div v-if="health.warnings.length > 0" class="mt-2">
                <div v-for="warning in health.warnings" :key="warning.type" class="small">
                    {{ warning.message }}
                </div>
            </div>
        </div>

        <!-- Stats Cards Row -->
        <div class="row g-3 mb-4">
            <!-- Users Card -->
            <div class="col-md-6 col-lg-3">
                <div class="card card-cml h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">Users</h6>
                        <div class="d-flex align-items-baseline">
                            <h2 class="mb-0 me-2">{{ overview?.users?.total || 0 }}</h2>
                            <small class="text-muted">total</small>
                        </div>
                        <div class="mt-2 small text-muted">
                            <span class="text-success">+{{ overview?.users?.last_7_days || 0 }}</span> last 7 days
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscriptions Card -->
            <div class="col-md-6 col-lg-3">
                <div class="card card-cml h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">Subscriptions</h6>
                        <div class="d-flex align-items-baseline">
                            <h2 class="mb-0 me-2">{{ overview?.subscriptions?.total_paying || 0 }}</h2>
                            <small class="text-muted">paying</small>
                        </div>
                        <div class="mt-2 small">
                            <span class="badge bg-secondary me-1">{{ overview?.subscriptions?.free || 0 }} free</span>
                            <span class="badge bg-primary me-1">{{ overview?.subscriptions?.pro || 0 }} pro</span>
                            <span class="badge bg-success">{{ overview?.subscriptions?.business || 0 }} biz</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="col-md-6 col-lg-3">
                <div class="card card-cml h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">Actions</h6>
                        <div class="d-flex align-items-baseline">
                            <h2 class="mb-0 me-2">{{ overview?.actions?.total || 0 }}</h2>
                            <small class="text-muted">total</small>
                        </div>
                        <div class="mt-2 small">
                            <span class="text-success">{{ overview?.actions?.executed || 0 }} executed</span>
                            <span class="mx-1">|</span>
                            <span class="text-danger">{{ overview?.actions?.failed || 0 }} failed</span>
                            <span v-if="overview?.actions?.failure_rate > 0" class="ms-1 text-muted">
                                ({{ overview?.actions?.failure_rate }}%)
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reminders Card -->
            <div class="col-md-6 col-lg-3">
                <div class="card card-cml h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">Reminders</h6>
                        <div class="d-flex align-items-baseline">
                            <h2 class="mb-0 me-2">{{ overview?.reminders?.sent || 0 }}</h2>
                            <small class="text-muted">sent</small>
                        </div>
                        <div class="mt-2 small">
                            <span class="text-success">{{ overview?.reminders?.confirmed || 0 }} confirmed</span>
                            <span class="mx-1">|</span>
                            <span class="text-danger">{{ overview?.reminders?.declined || 0 }} declined</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row g-4">
            <!-- Trends Section -->
            <div class="col-lg-8">
                <div class="card card-cml h-100">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">30-Day Trends</h5>
                    </div>
                    <div class="card-body">
                        <div v-if="!trends" class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-muted"></div>
                        </div>
                        <div v-else class="table-responsive">
                            <table class="table table-sm table-cml mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-end">Actions</th>
                                        <th class="text-end">Failures</th>
                                        <th class="text-end">Users</th>
                                        <th class="text-end">Reminders</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="day in recentTrends" :key="day.date">
                                        <td>{{ formatShortDate(day.date) }}</td>
                                        <td class="text-end">{{ day.actions }}</td>
                                        <td class="text-end">
                                            <span :class="day.failures > 0 ? 'text-danger' : ''">
                                                {{ day.failures }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <span :class="day.users > 0 ? 'text-success' : ''">
                                                {{ day.users }}
                                            </span>
                                        </td>
                                        <td class="text-end">{{ day.reminders_sent }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td>Total (30d)</td>
                                        <td class="text-end">{{ trendsTotals.actions }}</td>
                                        <td class="text-end text-danger">{{ trendsTotals.failures }}</td>
                                        <td class="text-end text-success">{{ trendsTotals.users }}</td>
                                        <td class="text-end">{{ trendsTotals.reminders }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="col-lg-4">
                <!-- Queue Health -->
                <div class="card card-cml mb-4">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Queue Health</h5>
                    </div>
                    <div class="card-body">
                        <div v-if="!queue" class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-muted"></div>
                        </div>
                        <div v-else>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Connection</span>
                                <span :class="queue.stats.connection === 'connected' ? 'badge bg-success' : 'badge bg-danger'">
                                    {{ queue.stats.connection }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Pending Jobs</span>
                                <span :class="queue.stats.pending > 100 ? 'text-warning fw-bold' : ''">
                                    {{ queue.stats.pending }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Failed (1h)</span>
                                <span :class="queue.stats.failed_last_hour > 10 ? 'text-danger fw-bold' : ''">
                                    {{ queue.stats.failed_last_hour }}
                                </span>
                            </div>
                            <div v-if="queue.stats.error" class="alert alert-danger mt-3 mb-0 small">
                                {{ queue.stats.error }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Health Metrics -->
                <div class="card card-cml">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Health Metrics</h5>
                    </div>
                    <div class="card-body">
                        <div v-if="!health" class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-muted"></div>
                        </div>
                        <div v-else>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Stuck (resolved)</span>
                                <span :class="health.metrics.stuck_resolved > 0 ? 'text-warning fw-bold' : ''">
                                    {{ health.metrics.stuck_resolved }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Stuck (executing)</span>
                                <span :class="health.metrics.stuck_executing > 0 ? 'text-danger fw-bold' : ''">
                                    {{ health.metrics.stuck_executing }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Stuck (awaiting)</span>
                                <span :class="health.metrics.stuck_awaiting > 0 ? 'text-warning fw-bold' : ''">
                                    {{ health.metrics.stuck_awaiting }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>High Retry Count</span>
                                <span :class="health.metrics.high_retry_count > 0 ? 'text-warning fw-bold' : ''">
                                    {{ health.metrics.high_retry_count }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Failure Rate (1h)</span>
                                <span :class="failureRateClass">
                                    {{ health.metrics.last_hour_failure_rate }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Stats Detail -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card card-cml">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Action Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col">
                                <div class="h4 mb-0">{{ overview?.actions?.http || 0 }}</div>
                                <small class="text-muted">HTTP Actions</small>
                            </div>
                            <div class="col">
                                <div class="h4 mb-0">{{ overview?.actions?.reminder || 0 }}</div>
                                <small class="text-muted">Reminders</small>
                            </div>
                            <div class="col">
                                <div class="h4 mb-0 text-success">{{ overview?.actions?.executed || 0 }}</div>
                                <small class="text-muted">Executed</small>
                            </div>
                            <div class="col">
                                <div class="h4 mb-0 text-danger">{{ overview?.actions?.failed || 0 }}</div>
                                <small class="text-muted">Failed</small>
                            </div>
                            <div class="col">
                                <div class="h4 mb-0 text-secondary">{{ overview?.actions?.cancelled || 0 }}</div>
                                <small class="text-muted">Cancelled</small>
                            </div>
                            <div class="col">
                                <div class="h4 mb-0 text-primary">{{ overview?.actions?.pending || 0 }}</div>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users List -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card card-cml">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Users</h5>
                        <button class="btn btn-sm btn-outline-secondary" @click="loadUsers" :disabled="loadingUsers">
                            <span v-if="loadingUsers" class="spinner-border spinner-border-sm me-1"></span>
                            {{ users ? 'Refresh' : 'Load Users' }}
                        </button>
                    </div>
                    <div class="card-body">
                        <div v-if="loadingUsers" class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-muted"></div>
                        </div>
                        <div v-else-if="users" class="table-responsive">
                            <table class="table table-sm table-cml table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Account</th>
                                        <th>Plan</th>
                                        <th class="text-end">Actions</th>
                                        <th>Joined</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="user in users.users" :key="user.id">
                                        <td>
                                            {{ user.name }}
                                            <span v-if="user.is_admin" class="badge bg-danger ms-1">Admin</span>
                                        </td>
                                        <td>
                                            <a :href="'mailto:' + user.email" class="text-decoration-none">
                                                {{ user.email }}
                                            </a>
                                        </td>
                                        <td>
                                            <span v-if="user.account">
                                                {{ user.account.name }}
                                                <span v-if="user.account.is_owner" class="badge bg-secondary ms-1">Owner</span>
                                            </span>
                                            <span v-else class="text-muted">-</span>
                                        </td>
                                        <td>
                                            <span :class="planBadgeClass(user.plan)">{{ user.plan }}</span>
                                            <span v-if="user.is_manually_managed" class="badge bg-info ms-1" :title="user.account?.manual_plan_reason || 'Manual override'">Manual</span>
                                        </td>
                                        <td class="text-end">{{ user.actions_count }}</td>
                                        <td>{{ formatShortDate(user.created_at) }}</td>
                                        <td>
                                            <span v-if="user.email_verified_at" class="badge bg-success">Verified</span>
                                            <span v-else class="badge bg-warning text-dark">Unverified</span>
                                        </td>
                                        <td>
                                            <button
                                                v-if="user.account"
                                                class="btn btn-sm btn-outline-secondary"
                                                @click="openPlanModal(user)"
                                                title="Set manual plan"
                                            >
                                                Set Plan
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="text-muted small mt-2">
                                Total: {{ users.total }} users
                            </div>
                        </div>
                        <div v-else class="text-center py-4 text-muted">
                            Click "Load Users" to view the user list
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Plan Modal -->
        <div v-if="planModal.show" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Set Manual Plan</h5>
                        <button type="button" class="btn-close" @click="closePlanModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <strong>Account:</strong> {{ planModal.user?.account?.name }}
                            <br>
                            <small class="text-muted">Owner: {{ planModal.user?.name }} ({{ planModal.user?.email }})</small>
                        </div>

                        <div v-if="planModal.user?.is_manually_managed" class="alert alert-info mb-3">
                            <strong>Current manual plan:</strong> {{ planModal.user?.account?.manual_plan }}
                            <span v-if="planModal.user?.account?.manual_plan_expires_at">
                                (expires {{ formatShortDate(planModal.user?.account?.manual_plan_expires_at) }})
                            </span>
                            <br>
                            <small v-if="planModal.user?.account?.manual_plan_reason">
                                Reason: {{ planModal.user?.account?.manual_plan_reason }}
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Plan</label>
                            <select class="form-select" v-model="planModal.plan">
                                <option value="">Revoke (use Stripe subscription)</option>
                                <option value="pro">Pro</option>
                                <option value="business">Business</option>
                            </select>
                        </div>

                        <div class="mb-3" v-if="planModal.plan">
                            <label class="form-label">Expires At (optional)</label>
                            <input type="date" class="form-control" v-model="planModal.expiresAt" :min="minDate">
                            <small class="text-muted">Leave empty for no expiration</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <input type="text" class="form-control" v-model="planModal.reason" placeholder="e.g. Beta tester, Support comp, Enterprise customer">
                        </div>

                        <div v-if="planModal.error" class="alert alert-danger">
                            {{ planModal.error }}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" @click="closePlanModal">
                            Cancel
                        </button>
                        <button
                            type="button"
                            :class="planModal.plan ? 'btn btn-primary' : 'btn btn-warning'"
                            @click="savePlan"
                            :disabled="planModal.saving"
                        >
                            <span v-if="planModal.saving" class="spinner-border spinner-border-sm me-1"></span>
                            {{ planModal.plan ? 'Set Plan' : 'Revoke Manual Plan' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { formatTime, formatShortDate } from '../utils/dateFormatting';

export default {
    name: 'Admin',
    data() {
        return {
            loading: false,
            loadingUsers: false,
            overview: null,
            trends: null,
            health: null,
            queue: null,
            users: null,
            planModal: {
                show: false,
                user: null,
                plan: '',
                expiresAt: '',
                reason: '',
                saving: false,
                error: null,
            },
        };
    },
    computed: {
        healthAlertClass() {
            if (!this.health) return 'alert-secondary';
            const classes = {
                healthy: 'alert-success',
                warning: 'alert-warning',
                critical: 'alert-danger',
            };
            return classes[this.health.status] || 'alert-secondary';
        },
        failureRateClass() {
            if (!this.health) return '';
            const rate = this.health.metrics.last_hour_failure_rate;
            if (rate > 10) return 'text-danger fw-bold';
            if (rate > 5) return 'text-warning fw-bold';
            return 'text-success';
        },
        recentTrends() {
            if (!this.trends?.data) return [];
            // Show last 7 days in reverse order (most recent first)
            return [...this.trends.data].reverse().slice(0, 7);
        },
        trendsTotals() {
            if (!this.trends?.data) return { actions: 0, failures: 0, users: 0, reminders: 0 };
            return this.trends.data.reduce((acc, day) => ({
                actions: acc.actions + day.actions,
                failures: acc.failures + day.failures,
                users: acc.users + day.users,
                reminders: acc.reminders + day.reminders_sent,
            }), { actions: 0, failures: 0, users: 0, reminders: 0 });
        },
        minDate() {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            return tomorrow.toISOString().split('T')[0];
        }
    },
    mounted() {
        this.refreshAll();
    },
    methods: {
        async refreshAll() {
            this.loading = true;
            try {
                await Promise.all([
                    this.loadOverview(),
                    this.loadTrends(),
                    this.loadHealth(),
                    this.loadQueue(),
                ]);
            } finally {
                this.loading = false;
            }
        },
        async loadOverview() {
            try {
                const response = await axios.get('/api/admin/stats/overview');
                this.overview = response.data;
            } catch (err) {
                console.error('Failed to load overview:', err);
            }
        },
        async loadTrends() {
            try {
                const response = await axios.get('/api/admin/stats/trends');
                this.trends = response.data;
            } catch (err) {
                console.error('Failed to load trends:', err);
            }
        },
        async loadHealth() {
            try {
                const response = await axios.get('/api/admin/health');
                this.health = response.data;
            } catch (err) {
                console.error('Failed to load health:', err);
            }
        },
        async loadQueue() {
            try {
                const response = await axios.get('/api/admin/queue');
                this.queue = response.data;
            } catch (err) {
                console.error('Failed to load queue:', err);
            }
        },
        async loadUsers() {
            this.loadingUsers = true;
            try {
                const response = await axios.get('/api/admin/users');
                this.users = response.data;
            } catch (err) {
                console.error('Failed to load users:', err);
            } finally {
                this.loadingUsers = false;
            }
        },
        planBadgeClass(plan) {
            const classes = {
                free: 'badge bg-secondary',
                pro: 'badge bg-primary',
                business: 'badge bg-success',
            };
            return classes[plan] || 'badge bg-secondary';
        },
        openPlanModal(user) {
            this.planModal = {
                show: true,
                user: user,
                plan: user.is_manually_managed ? user.account?.manual_plan : '',
                expiresAt: '',
                reason: user.account?.manual_plan_reason || '',
                saving: false,
                error: null,
            };
        },
        closePlanModal() {
            this.planModal.show = false;
            this.planModal.user = null;
        },
        async savePlan() {
            this.planModal.saving = true;
            this.planModal.error = null;

            try {
                const payload = {
                    plan: this.planModal.plan || null,
                    reason: this.planModal.reason || null,
                };

                if (this.planModal.plan && this.planModal.expiresAt) {
                    payload.expires_at = this.planModal.expiresAt;
                }

                const accountId = this.planModal.user.account.id;
                await axios.post(`/api/admin/accounts/${accountId}/manual-plan`, payload);

                // Refresh users list to show updated info
                await this.loadUsers();
                this.closePlanModal();
            } catch (err) {
                console.error('Failed to set plan:', err);
                this.planModal.error = err.response?.data?.message || 'Failed to update plan';
            } finally {
                this.planModal.saving = false;
            }
        },
        formatTime,
        formatShortDate,
    }
};
</script>
