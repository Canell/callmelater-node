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
                    <!-- Template Type Selection -->
                    <div class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Template Type</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-2 mb-md-0">
                                    <div
                                        class="type-option p-3 rounded border"
                                        :class="{ 'border-success bg-success-subtle': form.type === 'action' }"
                                        @click="setType('action')"
                                        style="cursor: pointer;"
                                    >
                                        <div class="d-flex align-items-start">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" :checked="form.type === 'action'" @change="setType('action')">
                                            </div>
                                            <div class="ms-2">
                                                <strong>Single Action</strong>
                                                <p class="text-muted small mb-0 mt-1">
                                                    Create a template for single HTTP calls or approval requests.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div
                                        class="type-option p-3 rounded border"
                                        :class="{ 'border-success bg-success-subtle': form.type === 'chain' }"
                                        @click="setType('chain')"
                                        style="cursor: pointer;"
                                    >
                                        <div class="d-flex align-items-start">
                                            <div class="form-check">
                                                <input type="radio" class="form-check-input" :checked="form.type === 'chain'" @change="setType('chain')">
                                            </div>
                                            <div class="ms-2">
                                                <strong>Workflow (Multi-step)</strong>
                                                <p class="text-muted small mb-0 mt-1">
                                                    Create a template for workflows with multiple webhooks, approvals, and wait steps.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mode Selection (only for action type) -->
                    <ModeSelector
                        v-if="form.type === 'action'"
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

                    <!-- Workflow Steps (for chain type) -->
                    <div v-if="form.type === 'chain'" class="card card-cml mb-4">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Workflow Steps ({{ chainSteps.length }})</h5>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary" @click="addChainStep('http_call')">
                                    + Webhook
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" @click="addChainStep('gated')">
                                    + Approval
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" @click="addChainStep('delay')">
                                    + Wait
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div v-if="chainSteps.length === 0" class="text-center py-4 text-muted border rounded bg-light">
                                Add at least 2 steps to create a workflow template.
                            </div>

                            <div
                                v-for="(step, index) in chainSteps"
                                :key="index"
                                class="step-card mb-3"
                            >
                                <div class="step-header d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="step-number">{{ index + 1 }}</span>
                                        <span class="badge" :class="stepTypeBadge(step.type)">
                                            {{ formatStepType(step.type) }}
                                        </span>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm step-name-input"
                                            v-model="step.name"
                                            placeholder="Step name"
                                            style="width: 200px;"
                                        >
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            :disabled="index === 0"
                                            @click="moveChainStep(index, -1)"
                                        >
                                            &uarr;
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            :disabled="index === chainSteps.length - 1"
                                            @click="moveChainStep(index, 1)"
                                        >
                                            &darr;
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            @click="removeChainStep(index)"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                </div>

                                <!-- HTTP Call step config -->
                                <div v-if="step.type === 'http_call'" class="step-body">
                                    <div class="row">
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label small">Method</label>
                                            <select class="form-select form-select-sm" v-model="step.method">
                                                <option value="GET">GET</option>
                                                <option value="POST">POST</option>
                                                <option value="PUT">PUT</option>
                                                <option value="PATCH">PATCH</option>
                                                <option value="DELETE">DELETE</option>
                                            </select>
                                        </div>
                                        <div class="col-md-10 mb-2">
                                            <label class="form-label small">URL</label>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm"
                                                v-model="step.url"
                                                placeholder="https://api.example.com/{{endpoint}} or {{input.url}}"
                                                required
                                            >
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">
                                            Request Body (JSON)
                                            <small class="text-muted" v-pre>- Use {{placeholder}}, {{input.field}} or {{steps.0.response.field}}</small>
                                        </label>
                                        <textarea
                                            class="form-control form-control-sm font-monospace"
                                            v-model="step.bodyJson"
                                            rows="3"
                                            placeholder='{"user_id": "{{user_id}}", "data": "{{steps.0.response.id}}"}'
                                        ></textarea>
                                    </div>
                                </div>

                                <!-- Gated step config -->
                                <div v-else-if="step.type === 'gated'" class="step-body">
                                    <div class="mb-3">
                                        <label class="form-label small">Message</label>
                                        <textarea
                                            class="form-control form-control-sm"
                                            v-model="step.gate.message"
                                            rows="2"
                                            placeholder="Approve this action? Use {{placeholder}} for variables."
                                            required
                                        ></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <UnifiedRecipientSelector
                                            v-model="step.selectedRecipients"
                                            label="Recipients"
                                            placeholder="Search contacts, channels, or enter email/phone..."
                                            helper-text="Select team members, chat channels, or enter email/phone manually."
                                        />
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Variable recipient (optional)</label>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            v-model="step.recipientVariable"
                                            placeholder="{{approver_email}}"
                                        >
                                        <small class="form-text text-muted">Use template placeholders for dynamic recipients.</small>
                                    </div>
                                </div>

                                <!-- Delay step config -->
                                <div v-else-if="step.type === 'delay'" class="step-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label small">Delay Duration</label>
                                            <div class="input-group input-group-sm">
                                                <input
                                                    type="number"
                                                    class="form-control"
                                                    v-model="step.delayValue"
                                                    min="1"
                                                    required
                                                >
                                                <select class="form-select" v-model="step.delayUnit">
                                                    <option value="m">Minutes</option>
                                                    <option value="h">Hours</option>
                                                    <option value="d">Days</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Condition (for all step types) -->
                                <div class="step-body border-top pt-2 mt-2">
                                    <div class="form-check">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            :id="'condition-' + index"
                                            v-model="step.hasCondition"
                                        >
                                        <label class="form-check-label small" :for="'condition-' + index">
                                            Add condition (only run if...)
                                        </label>
                                    </div>
                                    <div v-if="step.hasCondition" class="mt-2">
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            v-model="step.condition"
                                            placeholder="{{steps.0.status}} == 'executed' or {{steps.1.response.approved}} == true"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Workflow error handling -->
                            <div v-if="chainSteps.length > 0" class="mt-3 pt-3 border-top">
                                <label class="form-label">On Step Failure</label>
                                <select class="form-select" v-model="chainErrorHandling" style="max-width: 300px;">
                                    <option value="fail_chain">Fail entire workflow</option>
                                    <option value="skip_step">Skip failed step and continue</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Request Configuration (for immediate mode or gated with execute on approval) -->
                    <RequestConfigForm
                        v-if="form.type === 'action' && (form.mode === 'immediate' || showRequestForGated)"
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

                    <!-- Gate Configuration (for gated mode on action type) -->
                    <GateConfigForm
                        v-if="form.type === 'action' && form.mode === 'gated'"
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

                    <!-- Deduplication (only for action templates) -->
                    <CoordinationForm
                        v-if="form.type === 'action'"
                        v-model:keysText="coordinationKeysText"
                        :coordination="coordination"
                        @update:coordination="coordination = $event"
                        :initial-expanded="showCoordination"
                        description="Default deduplication settings for actions created from this template."
                        keys-label="Default Dedup Keys"
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
import { useActionStatus } from '../composables/useActionStatus';
import ModeSelector from '../components/ModeSelector.vue';
import CoordinationForm from '../components/CoordinationForm.vue';
import RequestConfigForm from '../components/RequestConfigForm.vue';
import GateConfigForm from '../components/GateConfigForm.vue';
import UnifiedRecipientSelector from '../components/UnifiedRecipientSelector.vue';

const { formatStepType: _formatStepType, stepTypeBadgeClass } = useActionStatus();

export default {
    name: 'CreateTemplate',
    components: {
        ModeSelector,
        CoordinationForm,
        RequestConfigForm,
        GateConfigForm,
        UnifiedRecipientSelector,
    },
    inject: ['toast'],
    data() {
        return {
            form: {
                name: '',
                description: '',
                type: 'action',
                mode: 'immediate',
                timezone: localStorage.getItem('userTimezone') || Intl.DateTimeFormat().resolvedOptions().timeZone,
                max_attempts: 5,
                retry_strategy: 'exponential',
                placeholders: [],
            },
            chainSteps: [],
            chainErrorHandling: 'fail_chain',
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
            if (this.form.type === 'chain') {
                return this.chainSteps.length < 2;
            }
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
                this.form.type = template.type || 'action';
                this.form.mode = template.mode || 'immediate';
                this.form.timezone = template.timezone || 'UTC';
                this.form.max_attempts = template.max_attempts || 5;
                this.form.retry_strategy = template.retry_strategy || 'exponential';
                this.form.placeholders = template.placeholders || [];

                // Chain-specific fields
                if (template.type === 'chain' && template.chain_steps) {
                    this.chainErrorHandling = template.chain_error_handling || 'fail_chain';
                    this.chainSteps = template.chain_steps.map(step => {
                        const loadedStep = {
                            name: step.name || 'Step',
                            type: step.type,
                            hasCondition: !!step.condition,
                            condition: step.condition || '',
                        };

                        if (step.type === 'http_call') {
                            loadedStep.method = step.method || 'POST';
                            loadedStep.url = step.url || '';
                            loadedStep.bodyJson = step.body
                                ? (typeof step.body === 'string' ? step.body : JSON.stringify(step.body, null, 2))
                                : '';
                        } else if (step.type === 'gated') {
                            loadedStep.gate = {
                                message: step.gate?.message || '',
                            };
                            // Parse recipients - some may be URIs, some may be template variables
                            loadedStep.selectedRecipients = [];
                            loadedStep.recipientVariable = '';
                            const recipients = step.gate?.recipients || [];
                            recipients.forEach(r => {
                                if (r.startsWith('{{') || r.includes('@') || r.startsWith('+')) {
                                    // It's a template variable or raw email/phone - put in variable field
                                    if (!loadedStep.recipientVariable) {
                                        loadedStep.recipientVariable = r;
                                    }
                                }
                                // Note: URI-based recipients would need to be looked up via API
                                // For now, we just initialize empty and let user re-select
                            });
                        } else if (step.type === 'delay') {
                            const delayMatch = (step.delay || '5m').match(/^(\d+)([mhd])$/);
                            loadedStep.delayValue = delayMatch ? parseInt(delayMatch[1]) : 5;
                            loadedStep.delayUnit = delayMatch ? delayMatch[2] : 'm';
                        }

                        return loadedStep;
                    });
                }

                // Request config (action templates only)
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

                // Gate config (action templates only)
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

                // Coordination (action templates only)
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
        setType(type) {
            this.form.type = type;
        },
        formatStepType: _formatStepType,
        stepTypeBadge: stepTypeBadgeClass,
        addChainStep(type) {
            const step = {
                name: `Step ${this.chainSteps.length + 1}`,
                type,
                hasCondition: false,
                condition: '',
            };

            if (type === 'http_call') {
                step.method = 'POST';
                step.url = '';
                step.bodyJson = '';
            } else if (type === 'gated') {
                step.gate = {
                    message: '',
                };
                step.selectedRecipients = [];
                step.recipientVariable = '';
            } else if (type === 'delay') {
                step.delayValue = 5;
                step.delayUnit = 'm';
            }

            this.chainSteps.push(step);
        },
        removeChainStep(index) {
            this.chainSteps.splice(index, 1);
        },
        moveChainStep(index, direction) {
            const newIndex = index + direction;
            if (newIndex < 0 || newIndex >= this.chainSteps.length) return;

            const temp = this.chainSteps[index];
            this.chainSteps[index] = this.chainSteps[newIndex];
            this.chainSteps[newIndex] = temp;
        },
        buildChainStepsPayload() {
            return this.chainSteps.map(step => {
                const result = {
                    name: step.name,
                    type: step.type,
                };

                if (step.hasCondition && step.condition) {
                    result.condition = step.condition;
                }

                if (step.type === 'http_call') {
                    result.method = step.method;
                    result.url = step.url;
                    if (step.bodyJson && step.bodyJson.trim()) {
                        // Check if body contains unquoted placeholders
                        if (this.hasUnquotedPlaceholders(step.bodyJson)) {
                            result.body = step.bodyJson;
                        } else {
                            try {
                                result.body = JSON.parse(step.bodyJson);
                            } catch {
                                result.body = step.bodyJson;
                            }
                        }
                    }
                } else if (step.type === 'gated') {
                    // Build recipients from selected + variable
                    const recipients = [];
                    const channels = new Set();

                    // Add selected recipients (from UnifiedRecipientSelector)
                    if (step.selectedRecipients && step.selectedRecipients.length > 0) {
                        step.selectedRecipients.forEach(r => {
                            recipients.push(r.uri);
                            // Determine channel from recipient type
                            if (r.type === 'channel') {
                                channels.add(r.provider || 'teams');
                            } else if (r.contact_type === 'phone') {
                                channels.add('sms');
                            } else {
                                channels.add('email');
                            }
                        });
                    }

                    // Add variable recipient if provided
                    if (step.recipientVariable && step.recipientVariable.trim()) {
                        recipients.push(step.recipientVariable.trim());
                        // Assume email for variable recipients by default
                        channels.add('email');
                    }

                    result.gate = {
                        message: step.gate.message,
                        channels: Array.from(channels),
                        recipients: recipients,
                    };
                } else if (step.type === 'delay') {
                    result.delay = `${step.delayValue}${step.delayUnit}`;
                }

                return result;
            });
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
                    type: this.form.type,
                    timezone: this.form.timezone,
                };

                // Placeholders
                if (this.form.placeholders.length > 0) {
                    payload.placeholders = this.form.placeholders.filter(ph => ph.name);
                }

                // Chain-specific fields
                if (this.form.type === 'chain') {
                    payload.chain_steps = this.buildChainStepsPayload();
                    payload.chain_error_handling = this.chainErrorHandling;
                } else {
                    // Action-specific fields
                    payload.mode = this.form.mode;
                    payload.max_attempts = parseInt(this.form.max_attempts);
                    payload.retry_strategy = this.form.retry_strategy;

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

                    // Coordination (only for action templates)
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

.type-option {
    transition: all 0.15s ease;
}

.type-option:hover {
    border-color: #22c55e !important;
}

.bg-success-subtle {
    background-color: rgba(34, 197, 94, 0.1);
}

.step-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.step-header {
    background: #f9fafb;
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
}

.step-body {
    padding: 16px;
}

.step-number {
    width: 24px;
    height: 24px;
    background: #e5e7eb;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
}

.step-name-input {
    border: none;
    background: transparent;
    font-weight: 500;
}

.step-name-input:focus {
    background: white;
    border: 1px solid #22c55e;
}
</style>
