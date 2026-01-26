<template>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center mb-4">
                    <router-link to="/templates" class="text-decoration-none me-3">&larr; Back</router-link>
                    <div>
                        <h2 class="mb-0">{{ isEdit ? 'Edit Template' : 'Create Template' }}</h2>
                        <small v-if="isEdit" class="text-muted">{{ form.name }}</small>
                    </div>
                </div>

                <!-- Loading state -->
                <div v-if="loadingTemplate" class="text-center py-5">
                    <div class="spinner-border text-muted" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2">Loading template...</p>
                </div>

                <form v-else @submit.prevent="submit">
                    <!-- Mode Selection -->
                    <ModeSelector
                        v-model="form.mode"
                        title="How should actions from this template execute?"
                        immediate-description="Execute HTTP request at scheduled time"
                    />

                    <!-- Basic Info -->
                    <div class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" class="form-control" v-model="form.name" required placeholder="e.g. Deploy Service">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" v-model="form.description" rows="2" placeholder="Brief description of what this template does"></textarea>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Timezone</label>
                                <select class="form-select" v-model="form.timezone" style="max-width: 300px;">
                                    <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                </select>
                                <div class="form-text">Used for wall-clock scheduling (e.g., "next Monday")</div>
                            </div>
                        </div>
                    </div>

                    <!-- Placeholders -->
                    <div class="card card-cml mb-4">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Placeholders</h5>
                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="addPlaceholder">
                                + Add Placeholder
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Define variables that can be passed when triggering this template.
                                Use <code v-pre>{{name}}</code> syntax in URL, headers, body, or gate message.
                            </p>

                            <div v-if="form.placeholders.length === 0" class="text-center text-muted py-4 border rounded bg-light">
                                No placeholders defined. Click "Add Placeholder" to create one.
                            </div>

                            <div v-else class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th style="width: 80px;">Required</th>
                                            <th>Default Value</th>
                                            <th>Description</th>
                                            <th style="width: 40px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(ph, index) in form.placeholders" :key="index">
                                            <td>
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm font-monospace"
                                                    v-model="ph.name"
                                                    placeholder="variable_name"
                                                    pattern="[a-zA-Z_][a-zA-Z0-9_]*"
                                                    required
                                                >
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input" v-model="ph.required">
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm"
                                                    v-model="ph.default"
                                                    placeholder="Default value"
                                                    :disabled="ph.required"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm"
                                                    v-model="ph.description"
                                                    placeholder="Brief description"
                                                >
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-link text-danger" @click="removePlaceholder(index)">
                                                    &times;
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Request Configuration (for immediate mode or gated with execute on approval) -->
                    <RequestConfigForm
                        v-if="form.mode === 'immediate' || showRequestForGated"
                        title="HTTP Request Configuration"
                        :request="request"
                        @update:request="request = $event"
                        v-model:headersJson="headersJson"
                        v-model:bodyJson="bodyJson"
                        v-model:maxAttempts="form.max_attempts"
                        v-model:retryStrategy="form.retry_strategy"
                        :headers-error="headersJsonError"
                        :body-error="bodyJsonError"
                        :url-required="form.mode === 'immediate'"
                        url-placeholder="https://api.example.com/{{service}}/deploy"
                        headers-placeholder='{"Authorization": "Bearer {{api_token}}"}'
                        body-placeholder='{"version": "{{version}}", "environment": "{{env}}"}'
                        :show-placeholder-hint="true"
                        :show-docs-link="false"
                    />

                    <!-- Gate Configuration (for gated mode) -->
                    <GateConfigForm
                        v-if="form.mode === 'gated'"
                        :gate="gate"
                        @update:gate="gate = $event"
                        v-model:recipientsText="recipientsText"
                        v-model:executeOnApproval="showRequestForGated"
                        message-placeholder="Deploy {{service}} v{{version}} to {{environment}}?"
                        recipients-label="Default Recipients (one per line)"
                        recipients-placeholder="ops@example.com&#10;+15551234567"
                        recipients-hint="Can be overridden when triggering. Use placeholder for dynamic recipients."
                        :recipients-rows="2"
                        :show-placeholder-hint="true"
                    />

                    <!-- Coordination -->
                    <CoordinationForm
                        v-model:keysText="coordinationKeysText"
                        :coordination="coordination"
                        @update:coordination="coordination = $event"
                        :initial-expanded="showCoordination"
                        description="Default coordination settings for actions created from this template."
                        keys-label="Default Coordination Keys"
                        keys-placeholder="e.g. deployment:{{service}}, env:{{environment}}"
                        :show-placeholder-hint="true"
                    />

                    <!-- Trigger URL Preview (for edit mode) -->
                    <div v-if="isEdit && existingTemplate" class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Trigger URL</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <code class="bg-light px-3 py-2 rounded flex-grow-1 text-break">
                                    {{ existingTemplate.trigger_url }}
                                </code>
                                <button type="button" class="btn btn-outline-secondary" @click="copyTriggerUrl">
                                    {{ urlCopied ? 'Copied!' : 'Copy' }}
                                </button>
                            </div>
                            <div class="text-muted small">
                                <strong>Example cURL:</strong>
                                <pre class="bg-light p-2 rounded mt-2 mb-0 small">curl -X POST {{ existingTemplate.trigger_url }} \
  -H "Content-Type: application/json" \
  -d '{{ examplePayload }}'</pre>
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
                        <router-link to="/templates" class="btn btn-outline-secondary">Cancel</router-link>
                        <button type="submit" class="btn btn-cml-primary" :disabled="submitting || hasValidationErrors">
                            {{ submitting ? 'Saving...' : (isEdit ? 'Save Changes' : 'Create Template') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import ModeSelector from '../components/ModeSelector.vue';
import CoordinationForm from '../components/CoordinationForm.vue';
import RequestConfigForm from '../components/RequestConfigForm.vue';
import GateConfigForm from '../components/GateConfigForm.vue';

export default {
    name: 'CreateTemplate',
    components: {
        ModeSelector,
        CoordinationForm,
        RequestConfigForm,
        GateConfigForm,
    },
    inject: ['toast'],
    data() {
        return {
            form: {
                name: '',
                description: '',
                mode: 'immediate',
                timezone: localStorage.getItem('userTimezone') || Intl.DateTimeFormat().resolvedOptions().timeZone,
                max_attempts: 5,
                retry_strategy: 'exponential',
                placeholders: [],
            },
            request: {
                method: 'POST',
                url: '',
            },
            gate: {
                message: '',
                timeoutValue: 4,
                timeoutUnit: 'h',
                on_timeout: 'expire',
                confirmation_mode: 'first_response',
                max_snoozes: 5,
            },
            showRequestForGated: false,
            showCoordination: false,
            coordinationKeysText: '',
            coordination: {
                on_create: '',
                on_execute_condition: '',
                on_condition_not_met: 'cancel',
            },
            headersJson: '',
            bodyJson: '',
            recipientsText: '',
            timezones: [
                'UTC',
                'Europe/London',
                'Europe/Paris',
                'Europe/Berlin',
                'Europe/Brussels',
                'Europe/Amsterdam',
                'America/New_York',
                'America/Chicago',
                'America/Denver',
                'America/Los_Angeles',
                'Asia/Tokyo',
                'Asia/Shanghai',
                'Asia/Singapore',
                'Australia/Sydney',
                'Pacific/Auckland',
            ],
            submitting: false,
            loadingTemplate: false,
            error: null,
            errors: [],
            existingTemplate: null,
            urlCopied: false,
        };
    },
    computed: {
        isEdit() {
            return !!this.$route.params.id;
        },
        headersJsonError() {
            return this.validateJson(this.headersJson, 'headers');
        },
        bodyJsonError() {
            return this.validateJson(this.bodyJson, 'body');
        },
        hasValidationErrors() {
            return !!(this.headersJsonError || this.bodyJsonError);
        },
        examplePayload() {
            if (!this.form.placeholders.length) {
                return '{"intent": {"delay": "1h"}}';
            }
            const example = { intent: { delay: '1h' } };
            this.form.placeholders.forEach(ph => {
                example[ph.name] = ph.default || `<${ph.name}>`;
            });
            return JSON.stringify(example);
        },
    },
    mounted() {
        if (this.isEdit) {
            this.loadTemplate();
        }
    },
    methods: {
        async loadTemplate() {
            this.loadingTemplate = true;
            try {
                const response = await axios.get(`/api/v1/templates/${this.$route.params.id}`);
                const template = response.data.data;
                this.existingTemplate = template;

                // Populate form
                this.form.name = template.name;
                this.form.description = template.description || '';
                this.form.mode = template.mode;
                this.form.timezone = template.timezone || 'UTC';
                this.form.max_attempts = template.max_attempts || 5;
                this.form.retry_strategy = template.retry_strategy || 'exponential';
                this.form.placeholders = template.placeholders || [];

                // Request config
                if (template.request_config) {
                    this.request.method = template.request_config.method || 'POST';
                    this.request.url = template.request_config.url || '';
                    this.headersJson = template.request_config.headers
                        ? JSON.stringify(template.request_config.headers, null, 2)
                        : '';
                    // Handle body stored as string (for numeric placeholders)
                    if (template.request_config.body) {
                        this.bodyJson = typeof template.request_config.body === 'string'
                            ? template.request_config.body
                            : JSON.stringify(template.request_config.body, null, 2);
                    }
                    if (template.mode === 'gated') {
                        this.showRequestForGated = true;
                    }
                }

                // Gate config
                if (template.gate_config) {
                    this.gate.message = template.gate_config.message || '';
                    if (template.gate_config.timeout) {
                        const match = template.gate_config.timeout.match(/^(\d+)([hdw])$/);
                        if (match) {
                            this.gate.timeoutValue = parseInt(match[1]);
                            this.gate.timeoutUnit = match[2];
                        }
                    }
                    this.gate.on_timeout = template.gate_config.on_timeout || 'expire';
                    this.gate.confirmation_mode = template.gate_config.confirmation_mode || 'first_response';
                    this.gate.max_snoozes = template.gate_config.max_snoozes ?? 5;
                    this.recipientsText = (template.gate_config.recipients || []).join('\n');
                }

                // Coordination
                if (template.default_coordination_keys?.length) {
                    this.coordinationKeysText = template.default_coordination_keys.join(', ');
                    this.showCoordination = true;
                }
                if (template.coordination_config?.on_create) {
                    this.coordination.on_create = template.coordination_config.on_create;
                    this.showCoordination = true;
                }
                if (template.coordination_config?.on_execute?.condition) {
                    this.coordination.on_execute_condition = template.coordination_config.on_execute.condition;
                    this.coordination.on_condition_not_met = template.coordination_config.on_execute.on_condition_not_met || 'cancel';
                    this.showCoordination = true;
                }
            } catch (err) {
                console.error('Failed to load template:', err);
                this.error = 'Failed to load template';
            } finally {
                this.loadingTemplate = false;
            }
        },
        addPlaceholder() {
            this.form.placeholders.push({
                name: '',
                required: false,
                default: '',
                description: '',
            });
        },
        removePlaceholder(index) {
            this.form.placeholders.splice(index, 1);
        },
        validateJson(jsonString, fieldName) {
            if (!jsonString || !jsonString.trim()) {
                return null;
            }
            try {
                // Replace placeholders with valid JSON values before parsing
                // This allows {{placeholder}} to be used as raw values (not just strings)
                const withPlaceholdersReplaced = jsonString.replace(/\{\{[^}]+\}\}/g, '"__placeholder__"');
                const parsed = JSON.parse(withPlaceholdersReplaced);
                if (typeof parsed !== 'object' || Array.isArray(parsed)) {
                    return `${fieldName === 'headers' ? 'Headers' : 'Body'} must be a JSON object`;
                }
                return null;
            } catch (e) {
                return 'Invalid JSON';
            }
        },
        copyTriggerUrl() {
            if (this.existingTemplate?.trigger_url) {
                navigator.clipboard.writeText(this.existingTemplate.trigger_url);
                this.urlCopied = true;
                setTimeout(() => { this.urlCopied = false; }, 2000);
            }
        },
        hasUnquotedPlaceholders(jsonString) {
            // Check if the JSON string contains placeholders that are NOT inside quotes
            // e.g., {"id": {{id}}} has unquoted placeholder, {"id": "{{id}}"} does not
            try {
                JSON.parse(jsonString);
                return false; // Valid JSON as-is, no unquoted placeholders
            } catch (e) {
                // Original failed, check if it's due to placeholders
                const withPlaceholdersReplaced = jsonString.replace(/\{\{[^}]+\}\}/g, '0');
                try {
                    JSON.parse(withPlaceholdersReplaced);
                    return true; // Becomes valid when placeholders replaced with numbers
                } catch (e2) {
                    return false; // Still invalid, some other JSON error
                }
            }
        },
        async submit() {
            this.submitting = true;
            this.error = null;
            this.errors = [];

            try {
                const payload = {
                    name: this.form.name,
                    description: this.form.description || null,
                    mode: this.form.mode,
                    timezone: this.form.timezone,
                    max_attempts: parseInt(this.form.max_attempts),
                    retry_strategy: this.form.retry_strategy,
                };

                // Placeholders
                if (this.form.placeholders.length > 0) {
                    payload.placeholders = this.form.placeholders.filter(ph => ph.name);
                }

                // Request config
                if (this.form.mode === 'immediate' || this.showRequestForGated) {
                    payload.request_config = {
                        method: this.request.method,
                        url: this.request.url,
                    };
                    if (this.headersJson.trim()) {
                        payload.request_config.headers = JSON.parse(this.headersJson);
                    }
                    if (this.bodyJson.trim()) {
                        // Check if body contains unquoted placeholders (e.g., {{id}} not inside quotes)
                        // If so, send as string to preserve numeric placeholder substitution
                        const hasUnquotedPlaceholders = this.hasUnquotedPlaceholders(this.bodyJson);
                        if (hasUnquotedPlaceholders) {
                            // Send as raw string - backend will parse after substitution
                            payload.request_config.body = this.bodyJson;
                        } else {
                            payload.request_config.body = JSON.parse(this.bodyJson);
                        }
                    }
                }

                // Gate config
                if (this.form.mode === 'gated') {
                    payload.gate_config = {
                        message: this.gate.message,
                        timeout: `${this.gate.timeoutValue}${this.gate.timeoutUnit}`,
                        on_timeout: this.gate.on_timeout,
                        confirmation_mode: this.gate.confirmation_mode,
                        max_snoozes: parseInt(this.gate.max_snoozes),
                    };
                    if (this.recipientsText.trim()) {
                        payload.gate_config.recipients = this.recipientsText
                            .split('\n')
                            .map(r => r.trim())
                            .filter(r => r);
                    }
                }

                // Coordination
                if (this.coordinationKeysText.trim()) {
                    payload.default_coordination_keys = this.coordinationKeysText
                        .split(',')
                        .map(k => k.trim())
                        .filter(k => k);
                }

                // Build coordination config
                const coordConfig = {};
                if (this.coordination.on_create) {
                    coordConfig.on_create = this.coordination.on_create;
                }
                if (this.coordination.on_execute_condition) {
                    coordConfig.on_execute = {
                        condition: this.coordination.on_execute_condition,
                        on_condition_not_met: this.coordination.on_condition_not_met || 'cancel',
                    };
                }
                if (Object.keys(coordConfig).length > 0) {
                    payload.coordination_config = coordConfig;
                }

                if (this.isEdit) {
                    await axios.put(`/api/v1/templates/${this.$route.params.id}`, payload);
                    this.toast?.success('Template updated');
                } else {
                    await axios.post('/api/v1/templates', payload);
                    this.toast?.success('Template created');
                }

                this.$router.push('/templates');
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
        },
    },
};
</script>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}
</style>
