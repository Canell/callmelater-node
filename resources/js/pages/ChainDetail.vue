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

        <!-- Loading state -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-muted" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <!-- Chain details -->
        <div v-else-if="chain">
            <!-- Back link and header -->
            <div class="mb-4">
                <router-link to="/chains" class="text-muted text-decoration-none small">
                    &larr; Back to Chains
                </router-link>
            </div>

            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h2 class="mb-1">{{ chain.name }}</h2>
                    <span :class="['badge', statusBadgeClass(chain.status)]">
                        {{ formatStatus(chain.status) }}
                    </span>
                </div>
                <button
                    v-if="canCancel"
                    class="btn btn-outline-danger"
                    @click="confirmCancelChain"
                >
                    Cancel Chain
                </button>
            </div>

            <!-- Chain info cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card card-cml">
                        <div class="card-body text-center">
                            <div class="text-muted small">Progress</div>
                            <div class="h4 mb-0">{{ chain.current_step }} / {{ chain.total_steps }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-cml">
                        <div class="card-body text-center">
                            <div class="text-muted small">Started</div>
                            <div class="h6 mb-0">{{ chain.started_at ? formatDate(chain.started_at) : '-' }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-cml">
                        <div class="card-body text-center">
                            <div class="text-muted small">Completed</div>
                            <div class="h6 mb-0">{{ chain.completed_at ? formatDate(chain.completed_at) : '-' }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-cml">
                        <div class="card-body text-center">
                            <div class="text-muted small">Error Handling</div>
                            <div class="h6 mb-0">{{ chain.error_handling === 'fail_chain' ? 'Fail Chain' : 'Skip Step' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Failure reason -->
            <div v-if="chain.failure_reason" class="alert alert-danger mb-4">
                <strong>Failure:</strong> {{ chain.failure_reason }}
            </div>

            <!-- Timeline -->
            <div class="card card-cml">
                <div class="card-header">
                    <h5 class="mb-0">Steps Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div
                            v-for="(step, index) in chain.steps"
                            :key="index"
                            class="timeline-item"
                            :class="{ 'timeline-item-current': index === chain.current_step && chain.status === 'running' }"
                        >
                            <!-- Timeline dot -->
                            <div class="timeline-dot" :class="stepDotClass(step.status)">
                                <span v-if="step.status === 'completed' || step.status === 'confirmed'">&#10003;</span>
                                <span v-else-if="step.status === 'failed'">&#10007;</span>
                                <span v-else-if="step.status === 'skipped'">&#8211;</span>
                                <span v-else-if="step.status === 'in_progress'" class="spinner-border spinner-border-sm"></span>
                                <span v-else>{{ index + 1 }}</span>
                            </div>

                            <!-- Timeline content -->
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ step.name }}</h6>
                                        <div class="d-flex gap-2 align-items-center">
                                            <span class="badge bg-light text-dark">{{ formatStepType(step.type) }}</span>
                                            <span :class="['badge', stepStatusBadgeClass(step.status)]">
                                                {{ formatStepStatus(step.status) }}
                                            </span>
                                        </div>
                                    </div>
                                    <small v-if="step.completed_at" class="text-muted">
                                        {{ formatDate(step.completed_at) }}
                                    </small>
                                </div>

                                <!-- Step details -->
                                <div v-if="step.type === 'http_call'" class="mt-2 small text-muted">
                                    <code>{{ step.method || 'POST' }} {{ step.url }}</code>
                                </div>
                                <div v-else-if="step.type === 'gated'" class="mt-2 small text-muted">
                                    {{ step.gate?.recipients_count || 0 }} recipient(s) via {{ (step.gate?.channels || ['email']).join(', ') }}
                                </div>
                                <div v-else-if="step.type === 'delay'" class="mt-2 small text-muted">
                                    Wait {{ step.delay }}
                                </div>

                                <!-- Step response (collapsible) -->
                                <div v-if="step.response" class="mt-2">
                                    <button
                                        class="btn btn-sm btn-link p-0"
                                        @click="toggleStepResponse(index)"
                                    >
                                        {{ expandedSteps[index] ? 'Hide' : 'Show' }} response
                                    </button>
                                    <pre v-if="expandedSteps[index]" class="mt-2 p-2 bg-light rounded small">{{ JSON.stringify(step.response, null, 2) }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input variables -->
            <div v-if="chain.input && Object.keys(chain.input).length > 0" class="card card-cml mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Input Variables</h5>
                </div>
                <div class="card-body">
                    <pre class="mb-0">{{ JSON.stringify(chain.input, null, 2) }}</pre>
                </div>
            </div>
        </div>

        <!-- Not found state -->
        <div v-else class="text-center py-5">
            <h5 class="text-muted">Chain not found</h5>
            <router-link to="/chains" class="btn btn-outline-secondary mt-3">
                Back to Chains
            </router-link>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { formatDate } from '../utils/dateFormatting';
import ConfirmModal from '../components/ConfirmModal.vue';

export default {
    name: 'ChainDetail',
    components: {
        ConfirmModal,
    },
    inject: ['toast'],
    data() {
        return {
            chain: null,
            loading: true,
            expandedSteps: {},
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
        canCancel() {
            return this.chain && ['pending', 'running'].includes(this.chain.status);
        }
    },
    mounted() {
        this.loadChain();
        this.startAutoRefresh();
    },
    beforeUnmount() {
        this.stopAutoRefresh();
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
        formatStepType(type) {
            const labels = {
                http_call: 'HTTP',
                gated: 'Approval',
                delay: 'Delay',
            };
            return labels[type] || type;
        },
        formatStepStatus(status) {
            const labels = {
                pending: 'Pending',
                in_progress: 'In Progress',
                completed: 'Completed',
                confirmed: 'Confirmed',
                declined: 'Declined',
                failed: 'Failed',
                skipped: 'Skipped',
            };
            return labels[status] || status;
        },
        stepStatusBadgeClass(status) {
            const classes = {
                pending: 'bg-secondary',
                in_progress: 'bg-primary',
                completed: 'bg-success',
                confirmed: 'bg-success',
                declined: 'bg-warning text-dark',
                failed: 'bg-danger',
                skipped: 'bg-light text-dark',
            };
            return classes[status] || 'bg-secondary';
        },
        stepDotClass(status) {
            const classes = {
                pending: 'timeline-dot-pending',
                in_progress: 'timeline-dot-progress',
                completed: 'timeline-dot-success',
                confirmed: 'timeline-dot-success',
                declined: 'timeline-dot-warning',
                failed: 'timeline-dot-danger',
                skipped: 'timeline-dot-muted',
            };
            return classes[status] || 'timeline-dot-pending';
        },
        toggleStepResponse(index) {
            this.expandedSteps[index] = !this.expandedSteps[index];
        },
        async loadChain(silent = false) {
            if (!silent) this.loading = true;
            try {
                const response = await axios.get(`/api/v1/chains/${this.$route.params.id}`);
                this.chain = response.data.data;
            } catch (err) {
                if (!silent) {
                    console.error('Failed to load chain:', err);
                    this.chain = null;
                }
            } finally {
                this.loading = false;
            }
        },
        startAutoRefresh() {
            this.refreshInterval = setInterval(() => {
                if (!document.hidden && this.chain && ['pending', 'running'].includes(this.chain.status)) {
                    this.loadChain(true);
                }
            }, 5000);
        },
        stopAutoRefresh() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        },
        confirmCancelChain() {
            this.confirmModal = {
                show: true,
                title: 'Cancel Chain',
                message: `Cancel "${this.chain.name}"? All pending steps will be cancelled.`,
                confirmText: 'Yes, Cancel',
                variant: 'danger',
                action: 'doCancelChain',
                data: this.chain,
            };
        },
        handleConfirm() {
            const { action, data } = this.confirmModal;
            this.confirmModal.show = false;
            if (action && typeof this[action] === 'function') {
                this[action](data);
            }
        },
        async doCancelChain() {
            try {
                await axios.delete(`/api/v1/chains/${this.chain.id}`);
                this.toast.success('Chain cancelled');
                this.loadChain();
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to cancel chain');
            }
        }
    }
};
</script>

<style scoped>
/* Timeline */
.timeline {
    position: relative;
    padding-left: 40px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}

.timeline-item {
    position: relative;
    padding-bottom: 24px;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item-current .timeline-dot {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.timeline-dot {
    position: absolute;
    left: -40px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    z-index: 1;
}

.timeline-dot-pending {
    background: #e5e7eb;
    color: #6b7280;
}

.timeline-dot-progress {
    background: #3b82f6;
    color: white;
}

.timeline-dot-success {
    background: #22c55e;
    color: white;
}

.timeline-dot-warning {
    background: #f59e0b;
    color: white;
}

.timeline-dot-danger {
    background: #ef4444;
    color: white;
}

.timeline-dot-muted {
    background: #f3f4f6;
    color: #9ca3af;
}

.timeline-content {
    background: #f9fafb;
    border-radius: 8px;
    padding: 16px;
}

pre {
    white-space: pre-wrap;
    word-wrap: break-word;
    font-size: 0.8rem;
}
</style>
