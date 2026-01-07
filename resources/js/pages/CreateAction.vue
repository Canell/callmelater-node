<template>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center mb-4">
                    <router-link to="/dashboard" class="text-decoration-none me-3">&larr; Back</router-link>
                    <h2 class="mb-0">Create Action</h2>
                </div>

                <form @submit.prevent="submit">
                    <!-- Type Selection -->
                    <div class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Action Type</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div
                                        class="card h-100 cursor-pointer"
                                        :class="{ 'border-primary': form.type === 'http' }"
                                        @click="form.type = 'http'"
                                        role="button"
                                    >
                                        <div class="card-body text-center py-4">
                                            <h5>HTTP Call</h5>
                                            <p class="text-muted mb-0 small">Schedule an HTTP request to a webhook or API endpoint</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div
                                        class="card h-100 cursor-pointer"
                                        :class="{ 'border-primary': form.type === 'reminder' }"
                                        @click="form.type = 'reminder'"
                                        role="button"
                                    >
                                        <div class="card-body text-center py-4">
                                            <h5>Reminder</h5>
                                            <p class="text-muted mb-0 small">Send an interactive reminder with Yes/No/Snooze options</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Basic Info -->
                    <div class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" class="form-control" v-model="form.name" required :placeholder="form.type === 'http' ? 'e.g. Delete trial account' : 'e.g. Remind me to review contract'">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" v-model="form.description" rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Schedule</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">When to execute *</label>
                                <div class="btn-group d-block mb-2" role="group">
                                    <input type="radio" class="btn-check" id="schedule-datetime" v-model="scheduleType" value="datetime">
                                    <label class="btn btn-outline-secondary" for="schedule-datetime">Specific Date/Time</label>

                                    <input type="radio" class="btn-check" id="schedule-preset" v-model="scheduleType" value="preset">
                                    <label class="btn btn-outline-secondary" for="schedule-preset">Preset</label>

                                    <input type="radio" class="btn-check" id="schedule-delay" v-model="scheduleType" value="delay">
                                    <label class="btn btn-outline-secondary" for="schedule-delay">Delay</label>
                                </div>

                                <div v-if="scheduleType === 'datetime'" class="row">
                                    <div class="col-md-8">
                                        <input type="datetime-local" class="form-control" v-model="form.execute_at">
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" v-model="form.timezone">
                                            <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div v-else-if="scheduleType === 'preset'">
                                    <select class="form-select" v-model="intentPreset">
                                        <option value="tomorrow">Tomorrow (same time)</option>
                                        <option value="next_week">Next Week</option>
                                        <option value="next_monday">Next Monday</option>
                                        <option value="next_friday">Next Friday</option>
                                        <option value="1h">In 1 Hour</option>
                                        <option value="4h">In 4 Hours</option>
                                        <option value="1d">In 1 Day</option>
                                        <option value="3d">In 3 Days</option>
                                    </select>
                                </div>

                                <div v-else-if="scheduleType === 'delay'" class="row">
                                    <div class="col-md-4">
                                        <input type="number" class="form-control" v-model="delayAmount" min="1" placeholder="Amount">
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" v-model="delayUnit">
                                            <option value="m">Minutes</option>
                                            <option value="h">Hours</option>
                                            <option value="d">Days</option>
                                            <option value="w">Weeks</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Firewall Hint (for HTTP actions) -->
                    <div v-if="form.type === 'http' && showFirewallHint && outboundIp" class="alert alert-light border mb-4 d-flex align-items-start">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-muted me-3 flex-shrink-0 mt-1">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        <div class="flex-grow-1">
                            <strong>Firewall configuration</strong>
                            <p class="mb-2 small text-muted">
                                If your endpoint is protected by a firewall, you may need to allow incoming requests from CallMeLater's outbound IP address.
                            </p>
                            <div class="d-flex align-items-center gap-2">
                                <code class="bg-white px-2 py-1 border rounded small">{{ outboundIp }}</code>
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="copyIp">
                                    {{ ipCopied ? 'Copied!' : 'Copy' }}
                                </button>
                                <a href="/docs/webhook-security" class="btn btn-sm btn-link">Learn more</a>
                            </div>
                        </div>
                        <button type="button" class="btn-close ms-2" @click="dismissFirewallHint" aria-label="Dismiss"></button>
                    </div>

                    <!-- HTTP Config -->
                    <div v-if="form.type === 'http'" class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">HTTP Request</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Method</label>
                                    <select class="form-select" v-model="httpRequest.method">
                                        <option>GET</option>
                                        <option>POST</option>
                                        <option>PUT</option>
                                        <option>PATCH</option>
                                        <option>DELETE</option>
                                    </select>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label">URL *</label>
                                    <input type="url" class="form-control" v-model="httpRequest.url" required placeholder="https://api.example.com/webhook">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Headers (JSON)</label>
                                <textarea class="form-control font-monospace" v-model="headersJson" rows="3" placeholder='{"Authorization": "Bearer token"}'></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Body (JSON)</label>
                                <textarea class="form-control font-monospace" v-model="bodyJson" rows="4" placeholder='{"key": "value"}'></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Max Attempts</label>
                                    <input type="number" class="form-control" v-model="form.max_attempts" min="1" max="10">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Retry Strategy</label>
                                    <select class="form-select" v-model="form.retry_strategy">
                                        <option value="exponential">Exponential Backoff</option>
                                        <option value="linear">Linear</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reminder Config -->
                    <div v-if="form.type === 'reminder'" class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Reminder Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Message *</label>
                                <textarea class="form-control" v-model="form.message" rows="4" required placeholder="Enter the reminder message..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Recipients * (one email per line)</label>
                                <textarea class="form-control" v-model="recipientsText" rows="3" placeholder="user@example.com"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Confirmation Mode</label>
                                    <select class="form-select" v-model="form.confirmation_mode">
                                        <option value="first_response">First Response Wins</option>
                                        <option value="all_required">All Must Confirm</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Max Snoozes</label>
                                    <input type="number" class="form-control" v-model="form.max_snoozes" min="0" max="10">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error display -->
                    <div v-if="error" class="alert alert-danger">
                        {{ error }}
                    </div>
                    <div v-if="errors.length" class="alert alert-danger">
                        <ul class="mb-0">
                            <li v-for="err in errors" :key="err">{{ err }}</li>
                        </ul>
                    </div>

                    <!-- Submit -->
                    <div class="d-flex justify-content-end gap-2">
                        <router-link to="/dashboard" class="btn btn-outline-secondary">Cancel</router-link>
                        <button type="submit" class="btn btn-cml-primary" :disabled="submitting">
                            {{ submitting ? 'Creating...' : 'Create Action' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'CreateAction',
    data() {
        return {
            form: {
                type: 'http',
                name: '',
                description: '',
                execute_at: '',
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                max_attempts: 5,
                retry_strategy: 'exponential',
                message: '',
                confirmation_mode: 'first_response',
                max_snoozes: 5,
            },
            scheduleType: 'datetime',
            intentPreset: 'tomorrow',
            delayAmount: 1,
            delayUnit: 'h',
            httpRequest: {
                method: 'POST',
                url: '',
            },
            headersJson: '',
            bodyJson: '',
            recipientsText: '',
            timezones: [
                'UTC',
                'America/New_York',
                'America/Chicago',
                'America/Denver',
                'America/Los_Angeles',
                'Europe/London',
                'Europe/Paris',
                'Europe/Berlin',
                'Asia/Tokyo',
                'Asia/Shanghai',
                'Australia/Sydney',
            ],
            submitting: false,
            error: null,
            errors: [],
            // Firewall hint
            showFirewallHint: !localStorage.getItem('dismissedFirewallHint'),
            outboundIp: null,
            ipCopied: false,
        };
    },
    mounted() {
        this.loadServerInfo();
    },
    methods: {
        async loadServerInfo() {
            try {
                const response = await axios.get('/api/public/server-info');
                this.outboundIp = response.data.outbound_ip;
            } catch (err) {
                console.error('Failed to load server info:', err);
            }
        },
        copyIp() {
            navigator.clipboard.writeText(this.outboundIp);
            this.ipCopied = true;
            setTimeout(() => { this.ipCopied = false; }, 2000);
        },
        dismissFirewallHint() {
            this.showFirewallHint = false;
            localStorage.setItem('dismissedFirewallHint', 'true');
        },
        async submit() {
            this.submitting = true;
            this.error = null;
            this.errors = [];

            try {
                // Build payload
                const payload = {
                    type: this.form.type,
                    name: this.form.name,
                    description: this.form.description || undefined,
                    timezone: this.form.timezone,
                };

                // Schedule
                if (this.scheduleType === 'datetime') {
                    payload.execute_at = new Date(this.form.execute_at).toISOString();
                } else if (this.scheduleType === 'preset') {
                    payload.intent = { preset: this.intentPreset };
                } else if (this.scheduleType === 'delay') {
                    payload.intent = { delay: `${this.delayAmount}${this.delayUnit}` };
                }

                // Type-specific config
                if (this.form.type === 'http') {
                    payload.http_request = {
                        method: this.httpRequest.method,
                        url: this.httpRequest.url,
                    };
                    if (this.headersJson.trim()) {
                        payload.http_request.headers = JSON.parse(this.headersJson);
                    }
                    if (this.bodyJson.trim()) {
                        payload.http_request.body = JSON.parse(this.bodyJson);
                    }
                    payload.max_attempts = parseInt(this.form.max_attempts);
                    payload.retry_strategy = this.form.retry_strategy;
                } else {
                    payload.message = this.form.message;
                    payload.confirmation_mode = this.form.confirmation_mode;
                    payload.max_snoozes = parseInt(this.form.max_snoozes);
                    payload.escalation_rules = {
                        recipients: this.recipientsText.split('\n').map(e => e.trim()).filter(e => e),
                    };
                }

                await axios.post('/api/v1/actions', payload);
                this.$router.push('/dashboard');
            } catch (err) {
                if (err.response?.status === 422) {
                    const data = err.response.data;
                    if (data.errors) {
                        this.errors = Object.values(data.errors).flat();
                    } else {
                        this.error = data.message || 'Validation failed';
                    }
                } else {
                    this.error = err.response?.data?.message || 'An error occurred';
                }
            } finally {
                this.submitting = false;
            }
        }
    }
};
</script>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}
</style>
