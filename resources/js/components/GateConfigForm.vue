<template>
    <div class="card card-cml mb-4">
        <div class="card-header bg-transparent">
            <h5 class="mb-0">Gate Configuration</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Message *</label>
                <textarea
                    class="form-control"
                    :value="gate.message"
                    @input="updateGate('message', $event.target.value)"
                    rows="4"
                    required
                    :placeholder="messagePlaceholder"
                ></textarea>
                <div class="form-text">
                    <span v-if="showPlaceholderHint">You can use placeholders: <code v-pre>{{variable}}</code></span>
                    <span v-else>This is what recipients will see.</span>
                </div>
            </div>

            <!-- Recipients section -->
            <div class="mb-3">
                <label class="form-label">
                    {{ recipientsLabel }}
                </label>

                <!-- Team member selector (optional) -->
                <slot name="team-selector"></slot>

                <!-- Recipients textarea -->
                <div class="mb-2">
                    <label v-if="showRecipientsSubLabel" class="form-label small text-muted">
                        {{ recipientsSubLabel }}
                    </label>

                    <!-- Quick add buttons slot -->
                    <slot name="quick-add"></slot>

                    <textarea
                        class="form-control"
                        :value="recipientsText"
                        @input="$emit('update:recipientsText', $event.target.value)"
                        :rows="recipientsRows"
                        :placeholder="recipientsPlaceholder"
                    ></textarea>
                </div>
                <div v-if="recipientsHint" class="form-text" v-html="recipientsHint"></div>
            </div>

            <!-- Timeout, On Timeout, Confirmation Mode -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Timeout</label>
                    <div class="input-group">
                        <input
                            type="number"
                            class="form-control"
                            :value="gate.timeoutValue"
                            @input="updateGate('timeoutValue', parseInt($event.target.value))"
                            min="1"
                            placeholder="e.g. 4"
                        >
                        <select
                            class="form-select"
                            :value="gate.timeoutUnit"
                            @change="updateGate('timeoutUnit', $event.target.value)"
                            style="max-width: 100px;"
                        >
                            <option value="h">hours</option>
                            <option value="d">days</option>
                            <option value="w">weeks</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">On Timeout</label>
                    <select class="form-select" :value="gate.on_timeout" @change="updateGate('on_timeout', $event.target.value)">
                        <option value="expire">Mark as expired</option>
                        <option value="cancel">Cancel action</option>
                        <option value="approve">Auto-approve</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Confirmation Mode</label>
                    <select class="form-select" :value="gate.confirmation_mode" @change="updateGate('confirmation_mode', $event.target.value)">
                        <option value="first_response">First Response</option>
                        <option value="all_required">All Must Confirm</option>
                    </select>
                </div>
            </div>

            <!-- Max Snoozes -->
            <div class="mb-3">
                <label class="form-label">Max Snoozes</label>
                <input
                    type="number"
                    class="form-control"
                    :value="gate.max_snoozes"
                    @input="updateGate('max_snoozes', parseInt($event.target.value))"
                    min="0"
                    max="10"
                    style="max-width: 100px;"
                >
            </div>

            <!-- Execute on approval checkbox -->
            <div class="mt-4 p-3 bg-light rounded">
                <div class="form-check mb-0">
                    <input
                        type="checkbox"
                        class="form-check-input"
                        id="executeOnApproval"
                        :checked="executeOnApproval"
                        @change="$emit('update:executeOnApproval', $event.target.checked)"
                    >
                    <label class="form-check-label" for="executeOnApproval">
                        <strong>Execute HTTP request on approval</strong>
                        <div class="text-muted small">When approved, automatically fire a webhook to your specified URL</div>
                    </label>
                </div>
            </div>

            <!-- Escalation slot (for CreateAction with team members) -->
            <slot name="escalation"></slot>
        </div>
    </div>
</template>

<script>
export default {
    name: 'GateConfigForm',
    props: {
        gate: {
            type: Object,
            required: true,
            // Expected shape: { message: '', timeoutValue: 4, timeoutUnit: 'h', on_timeout: 'expire', confirmation_mode: 'first_response', max_snoozes: 5 }
        },
        recipientsText: {
            type: String,
            default: '',
        },
        executeOnApproval: {
            type: Boolean,
            default: false,
        },
        messagePlaceholder: {
            type: String,
            default: 'e.g. Ready to deploy v2.1 to production?',
        },
        recipientsLabel: {
            type: String,
            default: 'Recipients *',
        },
        recipientsSubLabel: {
            type: String,
            default: '',
        },
        showRecipientsSubLabel: {
            type: Boolean,
            default: false,
        },
        recipientsPlaceholder: {
            type: String,
            default: 'ops@example.com\n+32499123456',
        },
        recipientsRows: {
            type: Number,
            default: 3,
        },
        recipientsHint: {
            type: String,
            default: '',
        },
        showPlaceholderHint: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:gate', 'update:recipientsText', 'update:executeOnApproval'],
    methods: {
        updateGate(field, value) {
            this.$emit('update:gate', {
                ...this.gate,
                [field]: value,
            });
        },
    },
};
</script>
