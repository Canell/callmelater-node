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
                                            <h5>Webhook</h5>
                                            <p class="text-muted mb-0 small">Schedule a webhook to any URL at a specific time</p>
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
                                <a href="https://docs.callmelater.io/reference/security#ip-allowlisting" target="_blank" class="btn btn-sm btn-link">Learn more</a>
                            </div>
                        </div>
                        <button type="button" class="btn-close ms-2" @click="dismissFirewallHint" aria-label="Dismiss"></button>
                    </div>

                    <!-- Webhook Config -->
                    <div v-if="form.type === 'http'" class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Webhook Details</h5>
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
                                    <div class="position-relative">
                                        <input
                                            type="url"
                                            class="form-control"
                                            :class="{ 'is-invalid': urlError }"
                                            v-model="httpRequest.url"
                                            required
                                            placeholder="https://api.example.com/webhook"
                                            @blur="validateUrl"
                                        >
                                        <div v-if="urlValidating" class="position-absolute top-50 end-0 translate-middle-y pe-3">
                                            <span class="spinner-border spinner-border-sm text-muted"></span>
                                        </div>
                                    </div>
                                    <div v-if="urlError" class="invalid-feedback d-block">{{ urlError }}</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Headers (JSON)</label>
                                <textarea
                                    class="form-control font-monospace"
                                    :class="{ 'is-invalid': headersJsonError }"
                                    v-model="headersJson"
                                    rows="3"
                                    placeholder='{"Authorization": "Bearer YOUR_API_TOKEN"}'
                                ></textarea>
                                <div v-if="headersJsonError" class="invalid-feedback">{{ headersJsonError }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Body (JSON)</label>
                                <textarea
                                    class="form-control font-monospace"
                                    :class="{ 'is-invalid': bodyJsonError }"
                                    v-model="bodyJson"
                                    rows="4"
                                    placeholder='{"event": "trial_expired", "user_id": 123}'
                                ></textarea>
                                <div v-if="bodyJsonError" class="invalid-feedback">{{ bodyJsonError }}</div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Max Attempts</label>
                                    <input type="number" class="form-control" v-model="form.max_attempts" min="1" max="10">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        Retry Strategy
                                        <span
                                            class="text-muted ms-1"
                                            role="button"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-html="true"
                                            :title="retryTooltip"
                                            @mouseenter="initTooltip"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                            </svg>
                                        </span>
                                    </label>
                                    <select class="form-select" v-model="form.retry_strategy">
                                        <option value="exponential">Exponential Backoff</option>
                                        <option value="linear">Linear</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">Retries only occur when the request fails or times out.</small>
                                <a href="https://docs.callmelater.io/reference/retry-behavior" target="_blank" class="small ms-2">Learn more</a>
                            </div>

                            <!-- Test Webhook -->
                            <div class="mt-4 pt-3 border-top">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <strong>Test your webhook</strong>
                                        <div class="text-muted small">Send a test request to verify your configuration</div>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        @click="testRequest"
                                        :disabled="testButtonDisabled"
                                    >
                                        <span v-if="testing">
                                            <span class="spinner-border spinner-border-sm me-1"></span>
                                            Testing...
                                        </span>
                                        <span v-else-if="testCooldown > 0">
                                            Wait {{ testCooldown }}s
                                        </span>
                                        <span v-else>Test Request</span>
                                    </button>
                                </div>

                                <!-- Test Result -->
                                <div v-if="testResult" class="mt-3">
                                    <div
                                        class="alert mb-0"
                                        :class="testResult.success ? 'alert-success' : (testResult.rate_limited ? 'alert-warning' : 'alert-danger')"
                                    >
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong v-if="testResult.success">Success!</strong>
                                                <strong v-else-if="testResult.rate_limited">Test limit reached</strong>
                                                <strong v-else>Failed</strong>
                                                <span v-if="testResult.status_code" class="ms-2">
                                                    HTTP {{ testResult.status_code }}
                                                </span>
                                                <span v-if="testResult.duration_ms" class="text-muted ms-2">({{ testResult.duration_ms }}ms)</span>
                                            </div>
                                            <button type="button" class="btn-close" @click="testResult = null"></button>
                                        </div>
                                        <div v-if="testResult.error" class="mt-2 small">
                                            {{ testResult.error }}
                                        </div>
                                        <div v-if="testCooldown > 0" class="mt-2 small">
                                            You can test again in <strong>{{ testCooldown }}</strong> seconds.
                                        </div>
                                        <div v-if="testResult.body" class="mt-2">
                                            <small class="text-muted">Response preview:</small>
                                            <pre class="mb-0 mt-1 p-2 bg-white rounded small" style="max-height: 100px; overflow: auto;">{{ testResult.body }}</pre>
                                        </div>
                                    </div>
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
                        <button type="submit" class="btn btn-cml-primary" :disabled="submitting || (form.type === 'http' && (hasValidationErrors || !isUrlValid))">
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
            // Test request
            testing: false,
            testResult: null,
            testCooldown: 0,
            testCooldownInterval: null,
            // URL validation
            urlError: null,
            urlValidating: false,
            // Tooltip content
            retryTooltip: `<strong>Exponential backoff</strong><br>After a failure, retries are delayed with increasing intervals (e.g. 1min, 5min, 15min, 1hr).<br><br><strong>Linear</strong><br>Retries use fixed intervals.<br><br><em>Client errors (4xx) are not retried.</em>`,
        };
    },
    computed: {
        headersJsonError() {
            return this.validateJson(this.headersJson, 'headers');
        },
        bodyJsonError() {
            return this.validateJson(this.bodyJson, 'body');
        },
        hasJsonErrors() {
            return !!(this.headersJsonError || this.bodyJsonError);
        },
        hasValidationErrors() {
            return this.hasJsonErrors || !!this.urlError || this.urlValidating;
        },
        testButtonDisabled() {
            return this.testing || !this.isUrlValid || this.hasValidationErrors || this.testCooldown > 0;
        },
        isUrlValid() {
            if (!this.httpRequest.url) return false;
            try {
                const url = new URL(this.httpRequest.url);
                // Must have http/https protocol AND a valid hostname (not empty)
                const validProtocol = url.protocol === 'http:' || url.protocol === 'https:';
                const hasHost = url.hostname && url.hostname.length > 0 && url.hostname.includes('.');
                return validProtocol && hasHost;
            } catch {
                return false;
            }
        }
    },
    mounted() {
        this.loadServerInfo();
    },
    beforeUnmount() {
        // Clean up cooldown interval
        if (this.testCooldownInterval) {
            clearInterval(this.testCooldownInterval);
        }
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
        initTooltip(event) {
            // Initialize Bootstrap tooltip on first hover
            const el = event.target.closest('[data-bs-toggle="tooltip"]');
            if (el && !el._tooltip) {
                el._tooltip = new bootstrap.Tooltip(el);
                el._tooltip.show();
            }
        },
        async testRequest() {
            this.testing = true;
            this.testResult = null;

            try {
                const payload = {
                    url: this.httpRequest.url,
                    method: this.httpRequest.method,
                };

                // Parse headers if provided
                if (this.headersJson.trim()) {
                    payload.headers = JSON.parse(this.headersJson);
                }

                // Parse body if provided
                if (this.bodyJson.trim()) {
                    payload.body = JSON.parse(this.bodyJson);
                }

                const response = await axios.post('/api/v1/actions/test', payload);
                this.testResult = response.data;
            } catch (err) {
                // Handle rate limiting (429)
                if (err.response?.status === 429) {
                    const data = err.response.data;
                    this.testResult = {
                        success: false,
                        error: data.message || 'Rate limit reached. Please wait before testing again.',
                        duration_ms: 0,
                        rate_limited: true,
                    };
                    // Start cooldown timer
                    const retryAfter = data.retry_after || 60;
                    this.startCooldown(retryAfter);
                } else {
                    this.testResult = {
                        success: false,
                        error: err.response?.data?.message || err.message || 'Test failed',
                        duration_ms: 0,
                    };
                }
            } finally {
                this.testing = false;
            }
        },
        startCooldown(seconds) {
            this.testCooldown = seconds;
            // Clear any existing interval
            if (this.testCooldownInterval) {
                clearInterval(this.testCooldownInterval);
            }
            // Start countdown
            this.testCooldownInterval = setInterval(() => {
                this.testCooldown--;
                if (this.testCooldown <= 0) {
                    clearInterval(this.testCooldownInterval);
                    this.testCooldownInterval = null;
                }
            }, 1000);
        },
        validateJson(jsonString, fieldName) {
            if (!jsonString || !jsonString.trim()) {
                return null; // Empty is valid (optional field)
            }
            try {
                const parsed = JSON.parse(jsonString);
                // Ensure it's an object, not an array or primitive
                if (typeof parsed !== 'object' || Array.isArray(parsed)) {
                    return `${fieldName === 'headers' ? 'Headers' : 'Body'} must be a JSON object`;
                }
                return null;
            } catch (e) {
                // Try to give a helpful error message
                const message = e.message || 'Invalid JSON';
                // Clean up common JSON parse error messages
                if (message.includes('Unexpected token')) {
                    const match = message.match(/position (\d+)/);
                    if (match) {
                        return `Invalid JSON — syntax error near position ${match[1]}`;
                    }
                    return 'Invalid JSON — unexpected token';
                }
                if (message.includes('Unexpected end')) {
                    return 'Invalid JSON — incomplete or missing closing bracket';
                }
                return `Invalid JSON — ${message.toLowerCase()}`;
            }
        },
        async validateUrl() {
            const url = this.httpRequest.url?.trim();
            this.urlError = null;

            // Skip if empty - will be caught by required validation on submit
            if (!url) {
                return;
            }

            // Basic format validation first
            if (!this.isUrlValid) {
                this.urlError = 'Please enter a valid URL starting with http:// or https://';
                return;
            }

            // Validate against backend for SSRF checks
            this.urlValidating = true;
            try {
                const response = await axios.post('/api/v1/actions/test', {
                    url: url,
                    method: 'GET',
                });

                // Check if the URL was blocked by SSRF protection
                if (!response.data.success && response.data.error) {
                    const error = response.data.error.toLowerCase();
                    if (error.includes('private ip') || error.includes('not allowed') || error.includes('blocked')) {
                        this.urlError = 'This URL resolves to a private IP address and cannot be called for security reasons.';
                    }
                    // Don't show error for other failures (like 404) - those are fine, the URL is reachable
                }
            } catch (err) {
                // API error - might not be authenticated yet, skip validation
                console.warn('URL validation failed:', err);
            } finally {
                this.urlValidating = false;
            }
        },
        async submit() {
            this.submitting = true;
            this.error = null;
            this.errors = [];

            // Check for validation errors first
            if (this.form.type === 'http' && this.hasValidationErrors) {
                this.error = 'Please fix the validation errors before submitting.';
                this.submitting = false;
                return;
            }

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
