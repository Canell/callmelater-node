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

        <!-- Loading -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-muted" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <!-- Not found -->
        <div v-else-if="!action" class="text-center py-5">
            <h4>Action not found</h4>
            <router-link to="/dashboard" class="btn btn-cml-primary mt-3">Back to Dashboard</router-link>
        </div>

        <!-- Action detail -->
        <div v-else>
            <!-- Header -->
            <div class="d-flex align-items-center mb-4">
                <router-link to="/dashboard" class="text-decoration-none me-3">&larr; Back</router-link>
                <div class="flex-grow-1">
                    <h2 class="mb-0">{{ action.name }}</h2>
                    <small class="text-muted">{{ action.description }}</small>
                </div>
                <span :class="['badge', statusBadgeClass(action.status)]" style="font-size: 1rem;">
                    {{ formatStatus(action.status) }}
                </span>
            </div>

            <!-- Replaced notice -->
            <div v-if="action.replaced_by" class="alert alert-warning mb-4">
                <strong>This action was replaced.</strong>
                <router-link :to="`/actions/${action.replaced_by.id}`" class="alert-link ms-2">
                    View {{ action.replaced_by.name }} &rarr;
                </router-link>
            </div>

            <div class="row">
                <!-- Left column: Details -->
                <div class="col-lg-6">
                    <!-- Mode & Schedule -->
                    <div class="card card-cml mb-4">
                        <div class="card-header bg-transparent d-flex justify-content-between">
                            <h5 class="mb-0">Details</h5>
                            <span :class="['badge', action.mode === 'immediate' ? 'bg-primary' : 'bg-info']">
                                {{ formatType(action.mode) }}
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
                                <small class="text-muted d-block">Displayed In</small>
                                <strong>{{ displayTimezone }}</strong>
                            </div>
                            <div v-if="action.dedup_keys?.length" class="mb-3">
                                <small class="text-muted d-block">Dedup Keys</small>
                                <span v-for="key in action.dedup_keys" :key="key" class="badge bg-secondary me-1">
                                    {{ key }}
                                </span>
                            </div>
                            <div v-if="action.coordination_config" class="mb-3">
                                <small class="text-muted d-block">Coordination</small>
                                <div class="bg-light p-2 rounded small">
                                    <div v-if="action.coordination_config.on_create">
                                        On Create: <code>{{ action.coordination_config.on_create }}</code>
                                    </div>
                                    <div v-if="action.coordination_config.on_execute">
                                        On Execute: <code>{{ action.coordination_config.on_execute.condition }}</code>
                                        <span v-if="action.coordination_config.on_execute.on_condition_not_met && action.coordination_config.on_execute.on_condition_not_met !== 'cancel'" class="text-muted">
                                            ({{ action.coordination_config.on_execute.on_condition_not_met }})
                                        </span>
                                    </div>
                                    <div v-if="action.coordination_reschedule_count" class="text-muted">
                                        Rescheduled {{ action.coordination_reschedule_count }} time(s)
                                    </div>
                                </div>
                            </div>
                            <div v-if="action.is_recurring" class="mb-3">
                                <small class="text-muted d-block">Recurrence</small>
                                <span class="badge bg-info me-2">&#8635; Every {{ action.recurrence.frequency }} {{ recurrenceUnitLabel(action.recurrence.unit, action.recurrence.frequency) }}</span>
                                <div class="mt-1">
                                    <strong>{{ action.recurrence_count }}</strong> execution(s) completed
                                </div>
                                <div v-if="action.last_executed_at" class="small text-muted">
                                    Last executed: {{ formatDate(action.last_executed_at) }}
                                </div>
                                <div class="small text-muted mt-1">
                                    <template v-if="action.recurrence.end_type === 'count'">
                                        {{ Math.max(0, action.recurrence.max_occurrences - action.recurrence_count) }} remaining
                                    </template>
                                    <template v-else-if="action.recurrence.end_type === 'date'">
                                        Ends on {{ formatDate(action.recurrence.end_date) }}
                                    </template>
                                    <template v-else>
                                        Repeats indefinitely
                                    </template>
                                </div>
                            </div>
                            <div v-if="action.failure_reason" class="alert alert-danger mb-0">
                                <small class="d-block">Failure Reason</small>
                                {{ action.failure_reason }}
                            </div>
                        </div>
                    </div>

                    <!-- Related Actions -->
                    <div v-if="action.related_actions?.length" class="card card-cml mb-4">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Related Actions</h5>
                            <span class="badge bg-secondary">{{ action.related_actions.length }}</span>
                        </div>
                        <div class="list-group list-group-flush">
                            <router-link
                                v-for="related in action.related_actions"
                                :key="related.id"
                                :to="`/actions/${related.id}`"
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                            >
                                <div>
                                    <span class="fw-medium">{{ related.name }}</span>
                                    <small class="text-muted d-block">{{ formatDate(related.created_at) }}</small>
                                </div>
                                <span :class="['badge', statusBadgeClass(related.status)]">
                                    {{ formatStatus(related.status) }}
                                </span>
                            </router-link>
                        </div>
                    </div>

                    <!-- HTTP Request Config (for actions with request) -->
                    <div v-if="action.request" class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">HTTP Request</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <code class="d-block p-2 bg-light rounded">
                                    {{ action.request.method || 'POST' }} {{ action.request.url }}
                                </code>
                            </div>
                            <div v-if="action.request.headers && Object.keys(action.request.headers).length" class="mb-3">
                                <small class="text-muted d-block">Headers</small>
                                <pre class="bg-light p-2 rounded mb-0"><code>{{ JSON.stringify(action.request.headers, null, 2) }}</code></pre>
                            </div>
                            <div v-if="action.request.body" class="mb-3">
                                <small class="text-muted d-block">Body</small>
                                <pre class="bg-light p-2 rounded mb-0"><code>{{ JSON.stringify(action.request.body, null, 2) }}</code></pre>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted d-block">Attempts</small>
                                    <strong>{{ action.attempt_count }} / {{ action.max_attempts }}</strong>
                                </div>
                            </div>
                            <div v-if="action.gate_passed_at" class="mt-3">
                                <small class="text-muted d-block">Gate Passed At</small>
                                <strong>{{ formatDate(action.gate_passed_at) }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Config (gated mode) -->
                    <div v-if="action.mode === 'gated' && action.gate" class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Approval Configuration</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted d-block">Message</small>
                                <div class="bg-light p-3 rounded">{{ action.gate.message }}</div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted d-block">Confirmation Mode</small>
                                    <strong>{{ action.gate.confirmation_mode === 'first_response' ? 'First Response' : 'All Required' }}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Snoozes</small>
                                    <strong>{{ action.snooze_count || 0 }} / {{ action.gate.max_snoozes || 5 }}</strong>
                                </div>
                            </div>
                            <div v-if="action.gate.timeout" class="mt-3">
                                <small class="text-muted d-block">Timeout</small>
                                <strong>{{ action.gate.timeout }}</strong>
                                <span class="text-muted small ms-2">({{ action.gate.on_timeout || 'cancel' }} on timeout)</span>
                            </div>
                            <div v-if="action.callback_url" class="mt-3">
                                <small class="text-muted d-block">Callback URL</small>
                                <code class="small">{{ action.callback_url }}</code>
                            </div>
                        </div>
                    </div>

                    <!-- Recipients (for gated actions) -->
                    <div v-if="action.mode === 'gated' && action.recipients?.length" class="card card-cml mb-4">
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
                                    <template v-for="r in action.recipients" :key="r.id">
                                        <tr>
                                            <td>{{ r.email }}</td>
                                            <td>
                                                <span :class="['badge', recipientBadgeClass(r.status)]">
                                                    {{ r.status }}
                                                </span>
                                            </td>
                                            <td>{{ r.responded_at ? formatDate(r.responded_at) : '-' }}</td>
                                        </tr>
                                        <tr v-if="r.response_comment" class="recipient-comment-row">
                                            <td colspan="3" class="pt-0 pb-2 ps-4">
                                                <small class="text-muted fst-italic">"{{ r.response_comment }}"</small>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div v-if="canCancel(action.status)" class="card card-cml border-danger mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0 text-danger">Danger Zone</h5>
                        </div>
                        <div class="card-body">
                            <button class="btn btn-outline-danger" @click="confirmCancelAction" :disabled="cancelling">
                                {{ cancelling ? 'Cancelling...' : 'Cancel Action' }}
                            </button>
                        </div>
                    </div>

                    <!-- Retry Zone (for failed actions) -->
                    <div v-if="canRetry(action.status)" class="card card-cml border-warning">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0 text-warning">Actions</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                <strong>Warning:</strong> Retrying will re-execute this action.
                                If your endpoint is not idempotent, this may cause duplicate effects.
                            </p>
                            <button
                                class="btn btn-outline-warning"
                                @click="confirmRetryAction"
                                :disabled="retrying"
                            >
                                {{ retrying ? 'Retrying...' : 'Retry Action' }}
                            </button>
                            <div v-if="action.manual_retry_count > 0" class="mt-2 small text-muted">
                                Previously retried {{ action.manual_retry_count }} time(s)
                                <span v-if="action.last_manual_retry_at">
                                    - Last retry: {{ formatDate(action.last_manual_retry_at) }}
                                </span>
                            </div>
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
                                <div
                                    v-for="(event, index) in events"
                                    :key="event.id"
                                    class="list-group-item event-item"
                                    :class="{ 'expandable': hasDetails(event), 'expanded': expandedEvents[event.id] }"
                                    @click="toggleEvent(event)"
                                >
                                    <!-- Event header (always visible) -->
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span v-if="hasDetails(event)" class="expand-icon me-1">
                                                {{ expandedEvents[event.id] ? '&#9660;' : '&#9654;' }}
                                            </span>
                                            <span class="badge bg-secondary me-2">Attempt #{{ event.attempt_number || (events.length - index) }}</span>
                                            <span :class="['badge', eventBadgeClass(event)]">
                                                {{ formatEventStatus(event) }}
                                            </span>
                                            <span v-if="event.actor_email" class="text-muted ms-2">
                                                {{ event.actor_email }}
                                            </span>
                                            <span v-if="event.response_code" class="text-muted ms-2">
                                                HTTP {{ event.response_code }}
                                            </span>
                                        </div>
                                        <small class="text-muted text-nowrap">
                                            <span v-if="event.duration_ms" class="me-2">{{ event.duration_ms }}ms</span>
                                            {{ formatTime(event.created_at) }}
                                        </small>
                                    </div>

                                    <!-- Manual retry cycle marker -->
                                    <div v-if="event._type === 'cycle'" class="mt-2 small">
                                        <span class="badge bg-info me-1">Manual Retry</span>
                                        Cycle #{{ event.cycle_number }} triggered by {{ event.triggered_by_user?.name || 'Unknown' }}
                                        <span v-if="event.result === 'success'" class="badge bg-success ms-2">Success</span>
                                        <span v-else-if="event.result === 'failed'" class="badge bg-danger ms-2">Failed</span>
                                        <span v-else class="badge bg-secondary ms-2">In Progress</span>
                                    </div>

                                    <!-- Brief summary (always visible) -->
                                    <div v-if="event.notes || event.error_message" class="mt-2 small text-muted">
                                        {{ event.notes || event.error_message }}
                                    </div>

                                    <!-- Expanded details (for HTTP attempts) -->
                                    <div v-if="expandedEvents[event.id] && event._type === 'attempt'" class="mt-3 event-details">
                                        <div class="row mb-2">
                                            <div class="col-4">
                                                <small class="text-muted d-block">Status Code</small>
                                                <strong>{{ event.response_code || 'N/A' }}</strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted d-block">Duration</small>
                                                <strong>{{ event.duration_ms }}ms</strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted d-block">Timestamp</small>
                                                <strong>{{ formatDate(event.created_at) }}</strong>
                                            </div>
                                        </div>

                                        <!-- Response preview -->
                                        <div v-if="event.response_preview" class="mt-3">
                                            <small class="text-muted d-block mb-1">
                                                Response preview
                                                <span v-if="event.response_truncated" class="text-warning">(truncated to 10 KB)</span>
                                            </small>
                                            <pre class="response-preview bg-dark text-light p-2 rounded mb-0"><code>{{ event.response_preview }}</code></pre>
                                        </div>
                                        <div v-else-if="event.status === 'success'" class="mt-3">
                                            <small class="text-muted">No response body returned.</small>
                                        </div>

                                        <!-- Error details -->
                                        <div v-if="event.error_message" class="mt-3">
                                            <small class="text-muted d-block mb-1">Error</small>
                                            <div class="alert alert-danger py-2 px-3 mb-0">
                                                {{ event.error_message }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Connection error hint -->
                                    <div v-if="isConnectionError(event)" class="mt-2 small">
                                        <div class="alert alert-warning py-2 px-3 mb-0">
                                            <strong>Possible causes:</strong> Firewall rules, network restrictions, or endpoint unreachable.
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
import { useActionStatus } from '../composables/useActionStatus';
import { formatDate, formatTime } from '../utils/dateFormatting';
import ConfirmModal from '../components/ConfirmModal.vue';

const { formatStatus, statusBadgeClass, recipientBadgeClass, canCancel, canRetry, formatType } = useActionStatus();

export default {
    name: 'ActionDetail',
    components: {
        ConfirmModal,
    },
    inject: ['toast'],
    data() {
        return {
            action: null,
            loading: true,
            cancelling: false,
            retrying: false,
            expandedEvents: {},
            confirmModal: {
                show: false,
                title: '',
                message: '',
                confirmText: 'Confirm',
                variant: 'warning',
            },
            pendingAction: null,
        };
    },
    computed: {
        events() {
            // Combine delivery attempts, reminder events, and execution cycles
            const attempts = (this.action?.delivery_attempts || []).map(a => ({
                ...a,
                _type: 'attempt'
            }));
            const reminders = (this.action?.reminder_events || []).map(e => ({
                ...e,
                _type: 'reminder'
            }));
            const cycles = (this.action?.execution_cycles || [])
                .filter(c => c.triggered_by === 'manual')
                .map(c => ({
                    ...c,
                    _type: 'cycle',
                    id: `cycle-${c.id}`,
                    created_at: c.started_at,
                }));
            return [...attempts, ...reminders, ...cycles].sort((a, b) =>
                new Date(b.created_at) - new Date(a.created_at)
            );
        },
        displayTimezone() {
            return localStorage.getItem('userTimezone') || Intl.DateTimeFormat().resolvedOptions().timeZone;
        }
    },
    mounted() {
        this.loadAction();
    },
    methods: {
        formatDate,
        formatTime,
        formatStatus,
        formatType,
        statusBadgeClass,
        recipientBadgeClass,
        canCancel,
        canRetry,
        hasDetails(event) {
            // HTTP attempts have expandable details
            return event._type === 'attempt';
        },
        toggleEvent(event) {
            if (!this.hasDetails(event)) return;
            this.expandedEvents = {
                ...this.expandedEvents,
                [event.id]: !this.expandedEvents[event.id]
            };
        },
        formatEventStatus(event) {
            if (event._type === 'attempt') {
                if (event.status === 'success') {
                    return `Success (HTTP ${event.response_code})`;
                }
                return event.response_code ? `Failed (HTTP ${event.response_code})` : 'Failed';
            }
            return event.event_type || event.status;
        },
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
        isConnectionError(event) {
            if (event._type !== 'attempt' || event.status !== 'failed') return false;
            // No response code means connection failed before getting a response
            if (!event.response_code || event.response_code === 0) return true;
            // Check error message for connection-related keywords
            const errorMsg = (event.error_message || '').toLowerCase();
            const connectionKeywords = ['connection', 'timeout', 'refused', 'unreachable', 'network', 'dns', 'resolve'];
            return connectionKeywords.some(keyword => errorMsg.includes(keyword));
        },
        recurrenceUnitLabel(unit, freq) {
            const labels = { m: 'minute', h: 'hour', d: 'day', w: 'week', M: 'month' };
            const label = labels[unit] || unit;
            return freq > 1 ? label + 's' : label;
        },
        confirmCancelAction() {
            const recurringNote = this.action.is_recurring ? ' All future occurrences will also be stopped.' : '';
            this.confirmModal = {
                show: true,
                title: 'Cancel Action',
                message: `Cancel "${this.action.name}"? This action will not be executed.${recurringNote}`,
                confirmText: 'Yes, Cancel',
                variant: 'danger',
            };
            this.pendingAction = 'doCancelAction';
        },
        confirmRetryAction() {
            this.confirmModal = {
                show: true,
                title: 'Retry Action',
                message: `Retry "${this.action.name}"?\n\nWarning: If your endpoint is not idempotent, this may cause duplicate side effects. The action will be re-executed with a new execution cycle.`,
                confirmText: 'Yes, Retry',
                variant: 'warning',
            };
            this.pendingAction = 'doRetryAction';
        },
        handleConfirm() {
            this.confirmModal.show = false;
            if (this.pendingAction === 'doRetryAction') {
                this.doRetryAction();
            } else {
                this.doCancelAction();
            }
            this.pendingAction = null;
        },
        async doCancelAction() {
            this.cancelling = true;
            try {
                await axios.delete(`/api/v1/actions/${this.action.id}`);
                this.toast.success('Action cancelled');
                this.$router.push('/dashboard');
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to cancel action');
            } finally {
                this.cancelling = false;
            }
        },
        async doRetryAction() {
            this.retrying = true;
            try {
                await axios.post(`/api/v1/actions/${this.action.id}/retry`);
                this.toast.success('Action retry initiated');
                await this.loadAction();
            } catch (err) {
                const reasons = err.response?.data?.reasons;
                if (reasons && reasons.length > 0) {
                    this.toast.error(reasons.join('. '));
                } else {
                    this.toast.error(err.response?.data?.message || 'Failed to retry action');
                }
            } finally {
                this.retrying = false;
            }
        }
    }
};
</script>

<style scoped>
/* Expandable event items */
.event-item.expandable {
    cursor: pointer;
    transition: background-color 0.15s;
}

.event-item.expandable:hover {
    background-color: #f8f9fa;
}

.event-item.expanded {
    background-color: #f8f9fa;
}

.expand-icon {
    font-size: 0.75em;
    color: #6b7280;
}

/* Event details section */
.event-details {
    padding-top: 12px;
    border-top: 1px solid #e5e7eb;
}

/* Response preview */
.response-preview {
    max-height: 300px;
    overflow: auto;
    font-size: 0.85em;
    white-space: pre-wrap;
    word-break: break-all;
}

/* Recipient comment row */
.recipient-comment-row td {
    border-top: none !important;
    background-color: #f9fafb;
}
</style>
