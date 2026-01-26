<template>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center mb-4">
                    <router-link to="/dashboard" class="text-decoration-none me-3">&larr; Back</router-link>
                    <div>
                        <h2 class="mb-0">{{ cloneSourceName ? 'Clone Action' : 'Create Action' }}</h2>
                        <small v-if="cloneSourceName" class="text-muted">Cloning from "{{ cloneSourceName }}"</small>
                    </div>
                </div>

                <!-- Loading state when cloning -->
                <div v-if="cloning" class="text-center py-5">
                    <div class="spinner-border text-muted" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2">Loading action data...</p>
                </div>

                <form v-if="!cloning" @submit.prevent="submit">
                    <!-- Mode Selection -->
                    <ModeSelector
                        :model-value="form.mode"
                        @update:model-value="onModeChange"
                    />

                    <!-- Basic Info -->
                    <div class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" class="form-control" v-model="form.name" required :placeholder="form.mode === 'immediate' ? 'e.g. Delete trial account' : 'e.g. Deploy to production'">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" v-model="form.description" rows="2"></textarea>
                            </div>
                            <div v-if="teams.length > 0" class="mb-0">
                                <label class="form-label">Scope</label>
                                <select class="form-select" v-model="form.team_id" style="max-width: 300px;">
                                    <option :value="null">Personal (visible only to you)</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.id">
                                        {{ team.name }} (visible to team members)
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Coordination (optional, collapsible) -->
                    <CoordinationForm
                        v-model:keysText="coordinationKeysText"
                        :coordination="coordination"
                        @update:coordination="coordination = $event"
                        :initial-expanded="showCoordination"
                    />

                    <!-- Schedule -->
                    <div class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Schedule</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">{{ form.mode === 'gated' ? 'When to send approval request *' : 'When to execute *' }}</label>
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

                    <!-- Gate Configuration (Gated mode only) -->
                    <div v-if="form.mode === 'gated'" class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Gate Configuration</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Message *</label>
                                <textarea class="form-control" v-model="gate.message" rows="4" required placeholder="e.g. Ready to deploy v2.1 to production?"></textarea>
                                <div class="form-text">This is what recipients will see.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">
                                    Recipients *
                                </label>

                                <!-- Team member selector -->
                                <div v-if="teamMembers.length > 0" class="mb-3">
                                    <label class="form-label small text-muted">Select from contacts:</label>
                                    <select class="form-select" @change="addTeamMemberRecipient($event)" style="max-width: 300px;">
                                        <option value="">Choose a contact...</option>
                                        <option v-for="member in availableTeamMembers" :key="member.id" :value="member.id">
                                            {{ member.full_name }} ({{ member.email || member.phone }})
                                        </option>
                                    </select>
                                </div>

                                <!-- Selected team members as badges -->
                                <div v-if="selectedTeamMembers.length > 0" class="mb-2">
                                    <span
                                        v-for="member in selectedTeamMembers"
                                        :key="member.id"
                                        class="badge bg-primary me-1 mb-1 d-inline-flex align-items-center"
                                    >
                                        {{ member.full_name }}
                                        <button type="button" class="btn-close btn-close-white ms-1" style="font-size: 0.6rem;" @click="removeTeamMemberRecipient(member)"></button>
                                    </span>
                                </div>

                                <!-- Additional recipients (manual entry) -->
                                <div class="mb-2">
                                    <label class="form-label small text-muted">
                                        {{ teamMembers.length > 0 ? 'Or enter additional email/phone:' : 'Enter recipients (one per line):' }}
                                    </label>
                                    <!-- Team member quick-add buttons -->
                                    <div v-if="otherAccountMembers.length > 0" class="mb-2">
                                        <small class="text-muted d-block mb-1">Quick add account members:</small>
                                        <div class="d-flex flex-wrap gap-1">
                                            <div v-for="member in availableMembers" :key="member.id" class="btn-group btn-group-sm">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    @click="addRecipient(member.email || member.phone)"
                                                >
                                                    <span class="me-1">+</span>{{ member.name || member.email || member.phone }}
                                                </button>
                                                <button
                                                    v-if="member.email && member.phone"
                                                    type="button"
                                                    class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                                    data-bs-toggle="dropdown"
                                                >
                                                    <span class="visually-hidden">Toggle Dropdown</span>
                                                </button>
                                                <ul v-if="member.email && member.phone" class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item small" href="#" @click.prevent="addRecipient(member.email)">Email: {{ member.email }}</a></li>
                                                    <li><a class="dropdown-item small" href="#" @click.prevent="addRecipient(member.phone)">Phone: {{ member.phone }}</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <textarea class="form-control" v-model="recipientsText" rows="3" placeholder="ops@example.com&#10;+32499123456"></textarea>
                                </div>
                                <div class="form-text">
                                    <span v-if="hasPhoneRecipients">SMS recipients will receive a link to respond.</span>
                                    <span v-if="userPlan === 'free' && hasPhoneRecipients" class="text-warning">
                                        SMS requires <a href="/pricing">Pro plan</a>.
                                    </span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Timeout</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" v-model="gate.timeoutValue" min="1" placeholder="e.g. 4">
                                        <select class="form-select" v-model="gate.timeoutUnit" style="max-width: 100px;">
                                            <option value="h">hours</option>
                                            <option value="d">days</option>
                                            <option value="w">weeks</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">On Timeout</label>
                                    <select class="form-select" v-model="gate.on_timeout">
                                        <option value="expire">Mark as expired</option>
                                        <option value="cancel">Cancel action</option>
                                        <option value="approve">Auto-approve</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Confirmation Mode</label>
                                    <select class="form-select" v-model="gate.confirmation_mode">
                                        <option value="first_response">First Response</option>
                                        <option value="all_required">All Must Confirm</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Max Snoozes</label>
                                <input type="number" class="form-control" v-model="gate.max_snoozes" min="0" max="10" style="max-width: 100px;">
                            </div>

                            <!-- Execute HTTP on approval checkbox -->
                            <div class="mt-4 p-3 bg-light rounded">
                                <div class="form-check mb-0">
                                    <input type="checkbox" class="form-check-input" id="executeOnApproval" v-model="executeOnApproval">
                                    <label class="form-check-label" for="executeOnApproval">
                                        <strong>Execute HTTP request on approval</strong>
                                        <div class="text-muted small">When approved, automatically fire a webhook to your specified URL</div>
                                    </label>
                                </div>
                            </div>

                            <!-- Escalation Settings -->
                            <div class="border-top pt-3 mt-4">
                                <h6 class="mb-3">Escalation <span class="text-muted fw-normal">(optional)</span></h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Escalate after</label>
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                class="form-control"
                                                v-model="escalation.hours"
                                                min="0"
                                                step="0.5"
                                                placeholder="e.g. 2"
                                            >
                                            <span class="input-group-text">hours</span>
                                        </div>
                                        <div class="form-text">
                                            If no response received, escalate to contacts below.
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">
                                        Escalation Contacts
                                        <span class="text-muted fw-normal">(one per line)</span>
                                    </label>
                                    <!-- Team member quick-add for escalation -->
                                    <div v-if="otherAccountMembers.length > 0 && escalation.hours" class="mb-2">
                                        <small class="text-muted d-block mb-1">Quick add:</small>
                                        <div class="d-flex flex-wrap gap-1">
                                            <div v-for="member in availableEscalationMembers" :key="member.id" class="btn-group btn-group-sm">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    @click="addEscalationContact(member.email || member.phone)"
                                                >
                                                    <span class="me-1">+</span>{{ member.name || member.email || member.phone }}
                                                </button>
                                                <button
                                                    v-if="member.email && member.phone"
                                                    type="button"
                                                    class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                                    data-bs-toggle="dropdown"
                                                >
                                                    <span class="visually-hidden">Toggle Dropdown</span>
                                                </button>
                                                <ul v-if="member.email && member.phone" class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item small" href="#" @click.prevent="addEscalationContact(member.email)">Email: {{ member.email }}</a></li>
                                                    <li><a class="dropdown-item small" href="#" @click.prevent="addEscalationContact(member.phone)">Phone: {{ member.phone }}</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <textarea
                                        class="form-control"
                                        v-model="escalation.contacts"
                                        rows="2"
                                        placeholder="manager@example.com&#10;+15551234567"
                                        :disabled="!escalation.hours"
                                    ></textarea>
                                    <div class="form-text">
                                        These contacts are notified only if nobody responds in time.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Firewall Hint (for HTTP requests) -->
                    <div v-if="showRequestConfig && showFirewallHint && outboundIp" class="alert alert-light border mb-4 d-flex align-items-start">
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

                    <!-- HTTP Request Config (Immediate mode OR Gated + Execute on approval) -->
                    <RequestConfigForm
                        v-if="showRequestConfig"
                        :request="request"
                        @update:request="request = $event"
                        v-model:headersJson="headersJson"
                        v-model:bodyJson="bodyJson"
                        v-model:maxAttempts="form.max_attempts"
                        v-model:retryStrategy="form.retry_strategy"
                        :url-error="urlError"
                        :headers-error="headersJsonError"
                        :body-error="bodyJsonError"
                        :url-validating="urlValidating"
                        :show-retry-tooltip="true"
                        @validate-url="validateUrl"
                    >
                        <template #test-request>
                            <!-- Test Request -->
                            <div class="mt-4 pt-3 border-top">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <strong>Test your request</strong>
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
                        </template>
                    </RequestConfigForm>

                    <!-- Callback URL (for gated without request) -->
                    <div v-if="form.mode === 'gated' && !executeOnApproval" class="card card-cml mb-4">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Callback <span class="text-muted fw-normal">(optional)</span></h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-0">
                                <label class="form-label">Callback URL</label>
                                <input
                                    type="url"
                                    class="form-control"
                                    v-model="form.callback_url"
                                    placeholder="https://api.example.com/webhook/response"
                                >
                                <div class="form-text">
                                    We'll POST to this URL when someone responds (confirms, declines, or snoozes).
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
                        <button type="submit" class="btn btn-cml-primary" :disabled="submitting || (showRequestConfig && (hasValidationErrors || !isUrlValid))">
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
import ModeSelector from '../components/ModeSelector.vue';
import CoordinationForm from '../components/CoordinationForm.vue';
import RequestConfigForm from '../components/RequestConfigForm.vue';

export default {
    name: 'CreateAction',
    components: {
        ModeSelector,
        CoordinationForm,
        RequestConfigForm,
    },
    data() {
        return {
            form: {
                mode: 'immediate',
                name: '',
                description: '',
                execute_at: '',
                timezone: localStorage.getItem('userTimezone') || Intl.DateTimeFormat().resolvedOptions().timeZone,
                max_attempts: 3,
                retry_strategy: 'exponential',
                callback_url: '',
                team_id: null,
            },
            // Gate configuration (for gated mode)
            gate: {
                message: '',
                timeoutValue: 7,
                timeoutUnit: 'd',
                on_timeout: 'expire',
                confirmation_mode: 'first_response',
                max_snoozes: 5,
            },
            // Request configuration (for immediate or gated+execute)
            request: {
                method: 'POST',
                url: '',
            },
            executeOnApproval: false,
            // Teams (Business plan)
            teams: [],
            accountMembers: [],
            currentUserEmail: null,
            userPlan: 'free',
            // Team members (contacts)
            teamMembers: [],
            selectedTeamMembers: [],
            scheduleType: 'datetime',
            intentPreset: 'tomorrow',
            delayAmount: 1,
            delayUnit: 'h',
            headersJson: '',
            bodyJson: '',
            recipientsText: '',
            escalation: {
                hours: '',
                contacts: '',
            },
            // Coordination
            showCoordination: false,
            coordinationKeysText: '',
            coordination: {
                on_create: '',
                on_execute_condition: '',
                on_condition_not_met: 'cancel',
            },
            timezones: [
                'UTC',
                'Europe/London',
                'Europe/Paris',
                'Europe/Berlin',
                'Europe/Brussels',
                'Europe/Amsterdam',
                'Europe/Rome',
                'Europe/Madrid',
                'Europe/Zurich',
                'America/New_York',
                'America/Chicago',
                'America/Denver',
                'America/Los_Angeles',
                'America/Toronto',
                'America/Vancouver',
                'America/Sao_Paulo',
                'Asia/Tokyo',
                'Asia/Shanghai',
                'Asia/Hong_Kong',
                'Asia/Singapore',
                'Asia/Dubai',
                'Asia/Kolkata',
                'Australia/Sydney',
                'Australia/Melbourne',
                'Pacific/Auckland',
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
            // Cloning
            cloning: false,
            cloneSourceName: null,
            // Tooltip content
            retryTooltip: `<strong>Exponential backoff</strong><br>After a failure, retries are delayed with increasing intervals (e.g. 1min, 5min, 15min, 1hr).<br><br><strong>Linear</strong><br>Retries use fixed intervals.<br><br><em>Client errors (4xx) are not retried.</em>`,
        };
    },
    computed: {
        showRequestConfig() {
            return this.form.mode === 'immediate' || this.executeOnApproval;
        },
        headersJsonError() {
            return this.validateJson(this.headersJson, 'headers');
        },
        bodyJsonError() {
            return this.validateJson(this.bodyJson, 'body');
        },
        hasJsonErrors() {
            return !!(this.headersJsonError || this.bodyJsonError);
        },
        hasPhoneRecipients() {
            if (!this.recipientsText) return false;
            const lines = this.recipientsText.split('\n').map(l => l.trim()).filter(l => l);
            return lines.some(line => /^\+?[\d\s\-()]+$/.test(line) && line.replace(/\D/g, '').length >= 10);
        },
        currentRecipientEmails() {
            if (!this.recipientsText) return [];
            return this.recipientsText
                .split('\n')
                .map(l => l.trim().toLowerCase())
                .filter(l => l);
        },
        otherAccountMembers() {
            // Filter out the current user from account members
            if (!this.currentUserEmail) return this.accountMembers;
            return this.accountMembers.filter(
                member => member.email.toLowerCase() !== this.currentUserEmail.toLowerCase()
            );
        },
        availableMembers() {
            // Filter out members already added as recipients (and current user)
            return this.otherAccountMembers.filter(
                member => !this.currentRecipientEmails.includes(member.email.toLowerCase())
            );
        },
        currentEscalationEmails() {
            if (!this.escalation.contacts) return [];
            return this.escalation.contacts
                .split('\n')
                .map(l => l.trim().toLowerCase())
                .filter(l => l);
        },
        availableEscalationMembers() {
            // Filter out members already added as escalation contacts (and current user)
            return this.otherAccountMembers.filter(
                member => !this.currentEscalationEmails.includes(member.email.toLowerCase())
            );
        },
        availableTeamMembers() {
            // Filter out already selected team members
            const selectedIds = this.selectedTeamMembers.map(m => m.id);
            return this.teamMembers.filter(member => !selectedIds.includes(member.id));
        },
        hasValidationErrors() {
            return this.hasJsonErrors || !!this.urlError || this.urlValidating;
        },
        testButtonDisabled() {
            return this.testing || !this.isUrlValid || this.hasValidationErrors || this.testCooldown > 0;
        },
        isUrlValid() {
            if (!this.request.url) return false;
            try {
                const url = new URL(this.request.url);
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
        this.loadUserPlanAndTeams();

        // Check if we're cloning an existing action
        const cloneId = this.$route.query.clone;
        if (cloneId) {
            this.loadClonedAction(cloneId);
        }
    },
    beforeUnmount() {
        // Clean up cooldown interval
        if (this.testCooldownInterval) {
            clearInterval(this.testCooldownInterval);
        }
    },
    methods: {
        onModeChange(mode) {
            this.form.mode = mode;
            if (mode === 'immediate') {
                this.executeOnApproval = false;
            }
        },
        async loadServerInfo() {
            try {
                const response = await axios.get('/api/public/server-info');
                this.outboundIp = response.data.outbound_ip;
            } catch (err) {
                console.error('Failed to load server info:', err);
            }
        },
        async loadUserPlanAndTeams() {
            try {
                // Check user's plan and get current user email
                const [subResponse, accountResponse, teamsResponse, teamMembersResponse] = await Promise.all([
                    axios.get('/api/subscription/status'),
                    axios.get('/api/account'),
                    axios.get('/api/teams'),
                    axios.get('/api/v1/team-members'),
                ]);

                this.userPlan = subResponse.data.plan || 'free';
                this.currentUserEmail = subResponse.data.user?.email || null;
                this.teams = teamsResponse.data.data || [];
                this.teamMembers = teamMembersResponse.data.data || [];

                // Combine members from account and all teams (deduplicated by email)
                const accountMembers = accountResponse.data.data?.members || [];
                const teamMembers = this.teams.flatMap(team => team.members || []);
                const allMembers = [...accountMembers, ...teamMembers];

                // Deduplicate by email
                const seen = new Set();
                this.accountMembers = allMembers.filter(member => {
                    const email = member.email.toLowerCase();
                    if (seen.has(email)) return false;
                    seen.add(email);
                    return true;
                });
            } catch (err) {
                console.error('Failed to load user plan/teams:', err);
            }
        },
        async loadClonedAction(actionId) {
            this.cloning = true;
            try {
                const response = await axios.get(`/api/v1/actions/${actionId}`);
                const action = response.data.data;

                this.cloneSourceName = action.name;

                // Populate basic info
                this.form.mode = action.mode || 'immediate';
                this.form.name = `${action.name} (copy)`;
                this.form.description = action.description || '';
                this.form.timezone = action.timezone || localStorage.getItem('userTimezone') || Intl.DateTimeFormat().resolvedOptions().timeZone;

                // Default to delay scheduling for clones (1 hour from now)
                this.scheduleType = 'delay';
                this.delayAmount = 1;
                this.delayUnit = 'h';

                if (action.mode === 'immediate' || action.request) {
                    // Request config
                    const req = action.request || {};
                    this.request.method = req.method || 'POST';
                    this.request.url = req.url || '';
                    this.headersJson = req.headers ? JSON.stringify(req.headers, null, 2) : '';
                    this.bodyJson = req.body ? JSON.stringify(req.body, null, 2) : '';
                    this.form.max_attempts = action.max_attempts || 3;
                    this.form.retry_strategy = action.retry_strategy || 'exponential';

                    if (action.mode === 'gated' && action.request) {
                        this.executeOnApproval = true;
                    }
                }

                if (action.mode === 'gated' && action.gate) {
                    // Gate config
                    const g = action.gate;
                    this.gate.message = g.message || '';
                    this.gate.confirmation_mode = g.confirmation_mode || 'first_response';
                    this.gate.max_snoozes = g.max_snoozes || 5;
                    this.gate.on_timeout = g.on_timeout || 'expire';

                    // Parse timeout
                    if (g.timeout) {
                        const match = g.timeout.match(/^(\d+)([hdw])$/);
                        if (match) {
                            this.gate.timeoutValue = parseInt(match[1]);
                            this.gate.timeoutUnit = match[2];
                        }
                    }

                    // Recipients
                    this.recipientsText = (g.recipients || []).join('\n');

                    // Escalation
                    if (g.escalation) {
                        this.escalation.hours = g.escalation.after_hours || '';
                        this.escalation.contacts = (g.escalation.contacts || []).join('\n');
                    }
                }

                this.form.callback_url = action.callback_url || '';

                // Coordination keys
                if (action.coordination_keys && action.coordination_keys.length > 0) {
                    this.coordinationKeysText = action.coordination_keys.join(', ');
                    this.showCoordination = true;
                    if (action.coordination_config?.on_create) {
                        this.coordination.on_create = action.coordination_config.on_create;
                    }
                    if (action.coordination_config?.on_execute?.condition) {
                        this.coordination.on_execute_condition = action.coordination_config.on_execute.condition;
                        this.coordination.on_condition_not_met = action.coordination_config.on_execute.on_condition_not_met || 'cancel';
                    }
                }
            } catch (err) {
                console.error('Failed to load action for cloning:', err);
                this.error = 'Failed to load the action to clone. Please try again.';
            } finally {
                this.cloning = false;
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
                    url: this.request.url,
                    method: this.request.method,
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
            const url = this.request.url?.trim();
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
        addRecipient(email) {
            if (!email) return;
            // Add email to recipients if not already present
            const currentEmails = this.currentRecipientEmails;
            if (!currentEmails.includes(email.toLowerCase())) {
                if (this.recipientsText.trim()) {
                    this.recipientsText += '\n' + email;
                } else {
                    this.recipientsText = email;
                }
            }
        },
        addEscalationContact(email) {
            if (!email) return;
            // Add email to escalation contacts if not already present
            const currentEmails = this.currentEscalationEmails;
            if (!currentEmails.includes(email.toLowerCase())) {
                if (this.escalation.contacts?.trim()) {
                    this.escalation.contacts += '\n' + email;
                } else {
                    this.escalation.contacts = email;
                }
            }
        },
        addTeamMemberRecipient(event) {
            const memberId = event.target.value;
            if (!memberId) return;
            const member = this.teamMembers.find(m => m.id === memberId);
            if (member && !this.selectedTeamMembers.some(m => m.id === memberId)) {
                this.selectedTeamMembers.push(member);
            }
            // Reset the select
            event.target.value = '';
        },
        removeTeamMemberRecipient(member) {
            this.selectedTeamMembers = this.selectedTeamMembers.filter(m => m.id !== member.id);
        },
        async submit() {
            this.submitting = true;
            this.error = null;
            this.errors = [];

            // Check for validation errors first
            if (this.showRequestConfig && this.hasValidationErrors) {
                this.error = 'Please fix the validation errors before submitting.';
                this.submitting = false;
                return;
            }

            try {
                // Build payload
                const payload = {
                    mode: this.form.mode,
                    name: this.form.name,
                    description: this.form.description || undefined,
                    timezone: this.form.timezone,
                    team_id: this.form.team_id || undefined,
                };

                // Schedule
                if (this.scheduleType === 'datetime') {
                    payload.execute_at = new Date(this.form.execute_at).toISOString();
                } else if (this.scheduleType === 'preset') {
                    payload.intent = { preset: this.intentPreset };
                } else if (this.scheduleType === 'delay') {
                    payload.intent = { delay: `${this.delayAmount}${this.delayUnit}` };
                }

                // Request config (for immediate or gated+execute)
                if (this.showRequestConfig) {
                    payload.request = {
                        method: this.request.method,
                        url: this.request.url,
                    };
                    if (this.headersJson.trim()) {
                        payload.request.headers = JSON.parse(this.headersJson);
                    }
                    if (this.bodyJson.trim()) {
                        payload.request.body = JSON.parse(this.bodyJson);
                    }
                    payload.max_attempts = parseInt(this.form.max_attempts);
                    payload.retry_strategy = this.form.retry_strategy;
                }

                // Gate config (for gated mode)
                if (this.form.mode === 'gated') {
                    // Combine selected team member IDs with manually entered recipients
                    const manualRecipients = this.recipientsText.split('\n').map(e => e.trim()).filter(e => e);
                    const teamMemberIds = this.selectedTeamMembers.map(m => m.id);
                    const allRecipients = [...teamMemberIds, ...manualRecipients];

                    payload.gate = {
                        message: this.gate.message,
                        recipients: allRecipients,
                        timeout: `${this.gate.timeoutValue}${this.gate.timeoutUnit}`,
                        on_timeout: this.gate.on_timeout,
                        confirmation_mode: this.gate.confirmation_mode,
                        max_snoozes: parseInt(this.gate.max_snoozes),
                    };

                    // Add escalation settings if configured
                    if (this.escalation.hours && parseFloat(this.escalation.hours) > 0) {
                        payload.gate.escalation = {
                            after_hours: parseFloat(this.escalation.hours),
                        };
                        if (this.escalation.contacts?.trim()) {
                            payload.gate.escalation.contacts = this.escalation.contacts
                                .split('\n')
                                .map(c => c.trim())
                                .filter(c => c);
                        }
                    }
                }

                // Callback URL (for gated without request)
                if (this.form.mode === 'gated' && !this.executeOnApproval && this.form.callback_url?.trim()) {
                    payload.callback_url = this.form.callback_url.trim();
                }

                // Coordination keys
                if (this.coordinationKeysText.trim()) {
                    payload.coordination_keys = this.coordinationKeysText
                        .split(',')
                        .map(k => k.trim())
                        .filter(k => k);

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
                        payload.coordination = coordConfig;
                    }
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
