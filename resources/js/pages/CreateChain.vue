<template>
    <div class="container py-4">
        <div class="mb-4">
            <router-link to="/chains" class="text-muted text-decoration-none small">
                &larr; Back to Workflows
            </router-link>
        </div>

        <h2 class="mb-4">Create Workflow</h2>

        <form @submit.prevent="submitChain">
            <!-- Workflow metadata -->
            <div class="card card-cml mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Workflow Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Workflow Name</label>
                            <input
                                type="text"
                                class="form-control"
                                v-model="chain.name"
                                placeholder="e.g., User Onboarding Flow"
                                required
                            >
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">On Step Failure</label>
                            <select class="form-select" v-model="chain.error_handling">
                                <option value="fail_chain">Fail entire workflow</option>
                                <option value="skip_step">Skip failed step and continue</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Steps -->
            <div class="card card-cml mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Steps ({{ chain.steps.length }})</h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="addStep('http_call')">
                            + Webhook
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="addStep('gated')">
                            + Approval
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="addStep('delay')">
                            + Wait
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="chain.steps.length === 0" class="text-center py-4 text-muted">
                        Add at least 2 steps to create a workflow.
                    </div>

                    <div
                        v-for="(step, index) in chain.steps"
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
                                    @click="moveStep(index, -1)"
                                >
                                    &uarr;
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    :disabled="index === chain.steps.length - 1"
                                    @click="moveStep(index, 1)"
                                >
                                    &darr;
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    @click="removeStep(index)"
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
                                        placeholder="https://api.example.com/endpoint or {{input.url}}"
                                        required
                                    >
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">
                                    Request Body (JSON)
                                    <small class="text-muted" v-pre>- Use {{input.field}} or {{steps.0.response.field}} for variables</small>
                                </label>
                                <textarea
                                    class="form-control form-control-sm font-monospace"
                                    v-model="step.bodyJson"
                                    rows="3"
                                    placeholder='{"user_id": "{{input.user_id}}"}'
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
                                    placeholder="Approve this action? Use {{input.field}} for variables."
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
                                    placeholder="{{input.approver_email}}"
                                >
                                <small class="form-text text-muted">Use template variables for dynamic recipients.</small>
                            </div>
                        </div>

                        <!-- Delay step config -->
                        <div v-else-if="step.type === 'delay'" class="step-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label small">Wait Duration</label>
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
                </div>
            </div>

            <!-- Input variables -->
            <div class="card card-cml mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Input Variables</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="addInputVar">
                        + Add Variable
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Define variables that will be passed when the chain is triggered. Access them with <code v-pre>{{input.variable_name}}</code>.
                    </p>
                    <div v-if="inputVars.length === 0" class="text-muted small">
                        No input variables defined.
                    </div>
                    <div v-for="(variable, index) in inputVars" :key="index" class="row mb-2">
                        <div class="col-md-4">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                v-model="variable.key"
                                placeholder="Variable name"
                            >
                        </div>
                        <div class="col-md-7">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                v-model="variable.value"
                                placeholder="Default value (optional)"
                            >
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeInputVar(index)">
                                &times;
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error message -->
            <div v-if="error" class="alert alert-danger">
                {{ error }}
            </div>

            <!-- Submit -->
            <div class="d-flex justify-content-between">
                <router-link to="/chains" class="btn btn-outline-secondary">
                    Cancel
                </router-link>
                <button type="submit" class="btn btn-cml-primary" :disabled="submitting || chain.steps.length < 2">
                    {{ submitting ? 'Creating...' : 'Create Chain' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script>
import axios from 'axios';
import { useActionStatus } from '../composables/useActionStatus';
import UnifiedRecipientSelector from '../components/UnifiedRecipientSelector.vue';

const { formatStepType, stepTypeBadgeClass } = useActionStatus();

export default {
    name: 'CreateChain',
    components: {
        UnifiedRecipientSelector,
    },
    inject: ['toast'],
    data() {
        return {
            chain: {
                name: '',
                error_handling: 'fail_chain',
                steps: [],
            },
            inputVars: [],
            submitting: false,
            error: null,
        };
    },
    methods: {
        formatStepType,
        stepTypeBadge: stepTypeBadgeClass,
        addStep(type) {
            const step = {
                name: `Step ${this.chain.steps.length + 1}`,
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

            this.chain.steps.push(step);
        },
        removeStep(index) {
            this.chain.steps.splice(index, 1);
        },
        moveStep(index, direction) {
            const newIndex = index + direction;
            if (newIndex < 0 || newIndex >= this.chain.steps.length) return;

            const temp = this.chain.steps[index];
            this.chain.steps[index] = this.chain.steps[newIndex];
            this.chain.steps[newIndex] = temp;
        },
        addInputVar() {
            this.inputVars.push({ key: '', value: '' });
        },
        removeInputVar(index) {
            this.inputVars.splice(index, 1);
        },
        buildPayload() {
            const steps = this.chain.steps.map(step => {
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
                        try {
                            result.body = JSON.parse(step.bodyJson);
                        } catch {
                            result.body = null;
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

            const input = {};
            this.inputVars.forEach(v => {
                if (v.key) {
                    input[v.key] = v.value;
                }
            });

            return {
                name: this.chain.name,
                error_handling: this.chain.error_handling,
                steps,
                input: Object.keys(input).length > 0 ? input : null,
            };
        },
        async submitChain() {
            this.error = null;
            this.submitting = true;

            try {
                const payload = this.buildPayload();
                const response = await axios.post('/api/v1/chains', payload);
                this.toast.success('Chain created successfully');
                this.$router.push(`/chains/${response.data.data.id}`);
            } catch (err) {
                if (err.response?.data?.errors) {
                    const errors = err.response.data.errors;
                    const firstError = Object.values(errors)[0];
                    this.error = Array.isArray(firstError) ? firstError[0] : firstError;
                } else {
                    this.error = err.response?.data?.message || 'Failed to create chain';
                }
            } finally {
                this.submitting = false;
            }
        }
    }
};
</script>

<style scoped>
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
