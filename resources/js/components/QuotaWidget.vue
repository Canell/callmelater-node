<template>
    <div class="card card-cml">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Usage This Month</h5>
            <small class="text-muted">{{ period }}</small>
        </div>
        <div class="card-body">
            <div v-if="loading" class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-muted" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <div v-else-if="error" class="text-danger small">
                Failed to load usage data
            </div>
            <div v-else>
                <!-- Actions usage -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small">Actions</span>
                        <span class="small text-muted">
                            {{ usage.actions.used.toLocaleString() }} / {{ usage.actions.limit.toLocaleString() }}
                        </span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div
                            class="progress-bar"
                            :class="progressBarClass(usage.actions.percentage)"
                            :style="{ width: `${Math.min(usage.actions.percentage, 100)}%` }"
                        ></div>
                    </div>
                </div>

                <!-- SMS usage (only show if plan includes SMS) -->
                <div v-if="usage.sms.limit > 0">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small">SMS</span>
                        <span class="small text-muted">
                            {{ usage.sms.used.toLocaleString() }} / {{ usage.sms.limit.toLocaleString() }}
                        </span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div
                            class="progress-bar"
                            :class="progressBarClass(usage.sms.percentage)"
                            :style="{ width: `${Math.min(usage.sms.percentage, 100)}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Warning message at 80% -->
                <div v-if="showWarning" class="alert alert-warning mt-3 mb-0 py-2 px-3 small">
                    You're approaching your monthly limit.
                    <router-link to="/settings" class="alert-link">Upgrade your plan</router-link>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'QuotaWidget',
    data() {
        return {
            loading: true,
            error: false,
            usage: {
                actions: { used: 0, limit: 0, percentage: 0 },
                sms: { used: 0, limit: 0, percentage: 0 },
            },
            period: '',
            plan: 'free',
        };
    },
    computed: {
        showWarning() {
            return this.usage.actions.percentage >= 80 ||
                   (this.usage.sms.limit > 0 && this.usage.sms.percentage >= 80);
        }
    },
    mounted() {
        this.loadQuota();
    },
    methods: {
        async loadQuota() {
            this.loading = true;
            this.error = false;
            try {
                const response = await axios.get('/api/v1/quota');
                this.usage = {
                    actions: response.data.actions,
                    sms: response.data.sms,
                };
                this.period = response.data.period.month_name;
                this.plan = response.data.plan;
            } catch (err) {
                console.error('Failed to load quota:', err);
                this.error = true;
            } finally {
                this.loading = false;
            }
        },
        progressBarClass(percentage) {
            if (percentage >= 90) return 'bg-danger';
            if (percentage >= 80) return 'bg-warning';
            return 'bg-success';
        }
    }
};
</script>
