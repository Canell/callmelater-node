<template>
    <div class="container py-4">
        <!-- Loading -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-muted" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <!-- Not found -->
        <div v-else-if="!action" class="text-center py-5">
            <h4>Action not found</h4>
            <router-link to="/" class="btn btn-cml-primary mt-3">Back to Dashboard</router-link>
        </div>

        <!-- Action detail -->
        <div v-else>
            <!-- Header -->
            <div class="d-flex align-items-center mb-4">
                <router-link to="/" class="text-decoration-none me-3">&larr; Back</router-link>
                <div class="flex-grow-1">
                    <h2 class="mb-0">{{ action.name }}</h2>
                    <small class="text-muted">{{ action.description }}</small>
                </div>
                <span :class="['badge', statusBadgeClass(action.status)]" style="font-size: 1rem;">
                    {{ formatStatus(action.status) }}
                </span>
            </div>

            <div class="row">
                <!-- Left column: Details -->
                <div class="col-lg-6">
                    <!-- Type & Schedule -->
                    <div class="card card-cml mb-4">
                        <div class="card-header bg-transparent d-flex justify-content-between">
                            <h5 class="mb-0">Details</h5>
                            <span :class="['badge', action.type === 'http' ? 'bg-primary' : 'bg-info']">
                                {{ action.type.toUpperCase() }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted d-block">Scheduled For</small>
                                <strong>{{ formatDate(action.execute_at) }}</strong>
                            </div>
                            <div v-if="action.executed_at" class="mb-3">
                                <small class="text-muted d-block">Executed At</small>
                                <strong>{{ formatDate(action.executed_at) }}</strong>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block">Timezone</small>
                                <strong>{{ action.timezone || 'UTC' }}</strong>
                            </div>
                            <div v-if="action.failure_reason" class="alert alert-danger mb-0">
                                <small class="d-block">Failure Reason</small>
                                {{ action.failure_reason }}
                            </div>
                        </div>
                    </div>

                    <!-- HTTP Config -->
                    <div v-if="action.type === 'http' && action.http_request" class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">HTTP Request</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <code class="d-block p-2 bg-light rounded">
                                    {{ action.http_request.method || 'POST' }} {{ action.http_request.url }}
                                </code>
                            </div>
                            <div v-if="action.http_request.headers" class="mb-3">
                                <small class="text-muted d-block">Headers</small>
                                <pre class="bg-light p-2 rounded mb-0"><code>{{ JSON.stringify(action.http_request.headers, null, 2) }}</code></pre>
                            </div>
                            <div v-if="action.http_request.body" class="mb-3">
                                <small class="text-muted d-block">Body</small>
                                <pre class="bg-light p-2 rounded mb-0"><code>{{ JSON.stringify(action.http_request.body, null, 2) }}</code></pre>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted d-block">Attempts</small>
                                    <strong>{{ action.attempt_count }} / {{ action.max_attempts }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reminder Config -->
                    <div v-if="action.type === 'reminder'" class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Reminder</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted d-block">Message</small>
                                <div class="bg-light p-3 rounded">{{ action.message }}</div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted d-block">Confirmation Mode</small>
                                    <strong>{{ action.confirmation_mode === 'first_response' ? 'First Response' : 'All Required' }}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Snoozes</small>
                                    <strong>{{ action.snooze_count }} / {{ action.max_snoozes }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recipients (for reminders) -->
                    <div v-if="action.type === 'reminder' && action.recipients?.length" class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Recipients</h5>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-cml mb-0">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Responded</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in action.recipients" :key="r.id">
                                        <td>{{ r.email }}</td>
                                        <td>
                                            <span :class="['badge', recipientBadgeClass(r.status)]">
                                                {{ r.status }}
                                            </span>
                                        </td>
                                        <td>{{ r.responded_at ? formatDate(r.responded_at) : '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div v-if="canCancel(action.status)" class="card card-cml border-danger">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0 text-danger">Danger Zone</h5>
                        </div>
                        <div class="card-body">
                            <button class="btn btn-outline-danger" @click="cancelAction" :disabled="cancelling">
                                {{ cancelling ? 'Cancelling...' : 'Cancel Action' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right column: Events -->
                <div class="col-lg-6">
                    <div class="card card-cml">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Events</h5>
                        </div>
                        <div class="card-body p-0">
                            <div v-if="!events.length" class="p-4 text-center text-muted">
                                No events yet
                            </div>
                            <div v-else class="list-group list-group-flush">
                                <div v-for="(event, index) in events" :key="event.id" class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="badge bg-secondary me-2">#{{ events.length - index }}</span>
                                            <span :class="['badge', eventBadgeClass(event)]">
                                                {{ event.event_type || event.status }}
                                            </span>
                                            <span v-if="event.actor_email" class="text-muted ms-2">
                                                {{ event.actor_email }}
                                            </span>
                                            <span v-if="event.response_code" class="text-muted ms-2">
                                                HTTP {{ event.response_code }}
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            {{ formatDate(event.created_at) }}
                                            <span v-if="event.duration_ms">({{ event.duration_ms }}ms)</span>
                                        </small>
                                    </div>
                                    <div v-if="event.notes || event.error_message" class="mt-2 small text-muted">
                                        {{ event.notes || event.error_message }}
                                    </div>
                                    <!-- Connection error hint -->
                                    <div v-if="isConnectionError(event)" class="mt-2 small">
                                        <div class="alert alert-warning py-2 px-3 mb-0">
                                            <strong>Possible causes:</strong> This failure may be caused by firewall rules, network restrictions, or the endpoint being unreachable.
                                            <a href="/docs/webhook-security" class="alert-link">Check IP allowlisting</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'ActionDetail',
    data() {
        return {
            action: null,
            loading: true,
            cancelling: false,
        };
    },
    computed: {
        events() {
            // Combine delivery attempts and reminder events, sorted by date
            const attempts = (this.action?.delivery_attempts || []).map(a => ({
                ...a,
                _type: 'attempt'
            }));
            const reminders = (this.action?.reminder_events || []).map(e => ({
                ...e,
                _type: 'reminder'
            }));
            return [...attempts, ...reminders].sort((a, b) =>
                new Date(b.created_at) - new Date(a.created_at)
            );
        }
    },
    mounted() {
        this.loadAction();
    },
    methods: {
        async loadAction() {
            this.loading = true;
            try {
                const response = await axios.get(`/api/v1/actions/${this.$route.params.id}`);
                this.action = response.data.data;
            } catch (err) {
                console.error('Failed to load action:', err);
                this.action = null;
            } finally {
                this.loading = false;
            }
        },
        formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleString();
        },
        formatStatus(status) {
            const labels = {
                pending_resolution: 'Pending',
                resolved: 'Scheduled',
                awaiting_response: 'Awaiting Response',
                executed: 'Executed',
                failed: 'Failed',
                cancelled: 'Cancelled',
                expired: 'Expired',
            };
            return labels[status] || status;
        },
        statusBadgeClass(status) {
            const classes = {
                pending_resolution: 'bg-secondary',
                resolved: 'bg-primary',
                awaiting_response: 'bg-warning text-dark',
                executed: 'bg-success',
                failed: 'bg-danger',
                cancelled: 'bg-secondary',
                expired: 'bg-secondary',
            };
            return classes[status] || 'bg-secondary';
        },
        recipientBadgeClass(status) {
            const classes = {
                pending: 'bg-secondary',
                confirmed: 'bg-success',
                declined: 'bg-danger',
                snoozed: 'bg-warning text-dark',
            };
            return classes[status] || 'bg-secondary';
        },
        eventBadgeClass(event) {
            if (event._type === 'attempt') {
                return event.status === 'success' ? 'bg-success' : 'bg-danger';
            }
            const classes = {
                sent: 'bg-primary',
                confirmed: 'bg-success',
                declined: 'bg-danger',
                snoozed: 'bg-warning text-dark',
                escalated: 'bg-warning text-dark',
                expired: 'bg-secondary',
            };
            return classes[event.event_type] || 'bg-secondary';
        },
        canCancel(status) {
            return ['pending_resolution', 'resolved', 'awaiting_response'].includes(status);
        },
        isConnectionError(event) {
            if (event._type !== 'attempt' || event.status !== 'failed') return false;
            // No response code means connection failed before getting a response
            if (!event.response_code || event.response_code === 0) return true;
            // Check error message for connection-related keywords
            const errorMsg = (event.error_message || '').toLowerCase();
            const connectionKeywords = ['connection', 'timeout', 'refused', 'unreachable', 'network', 'dns', 'resolve'];
            return connectionKeywords.some(keyword => errorMsg.includes(keyword));
        },
        async cancelAction() {
            if (!confirm('Are you sure you want to cancel this action?')) return;

            this.cancelling = true;
            try {
                await axios.delete(`/api/v1/actions/${this.action.id}`);
                this.$router.push('/');
            } catch (err) {
                alert(err.response?.data?.message || 'Failed to cancel action');
            } finally {
                this.cancelling = false;
            }
        }
    }
};
</script>
