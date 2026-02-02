<template>
    <div class="card card-cml mb-4">
        <div
            class="card-header bg-transparent d-flex justify-content-between align-items-center cursor-pointer"
            @click="expanded = !expanded"
            role="button"
        >
            <h5 class="mb-0">
                Deduplication
                <span class="text-muted fw-normal ms-1">(optional)</span>
            </h5>
            <span class="text-muted">{{ expanded ? '−' : '+' }}</span>
        </div>
        <div v-show="expanded" class="card-body">
            <p class="text-muted small mb-3">
                {{ description }}
            </p>
            <div class="mb-3">
                <label class="form-label">{{ keysLabel }}</label>
                <input
                    type="text"
                    class="form-control"
                    :value="keysText"
                    @input="$emit('update:keysText', $event.target.value)"
                    :placeholder="keysPlaceholder"
                >
                <div class="form-text">
                    <span v-if="showPlaceholderHint">Comma-separated. You can use placeholders.</span>
                    <span v-else>Comma-separated keys to identify related actions. Example: <code>deployment:prod</code></span>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">On Create Behavior</label>
                <select class="form-select" :value="coordination.on_create" @change="updateField('on_create', $event.target.value)" style="max-width: 400px;">
                    <option value="">None (default)</option>
                    <option value="replace_existing">Replace existing — cancel previous pending actions with same key</option>
                    <option value="skip_if_exists">Skip if exists — don't create if pending action exists</option>
                </select>
                <div class="form-text">
                    What happens when creating an action with a key that already has pending actions.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">On Execute Condition</label>
                <select class="form-select" :value="coordination.on_execute_condition" @change="updateField('on_execute_condition', $event.target.value)" style="max-width: 400px;">
                    <option value="">None (default)</option>
                    <option value="execute_if_previous_succeeded">Execute if previous succeeded — only run if last action with same key succeeded</option>
                    <option value="execute_if_previous_failed">Execute if previous failed — only run if last action with same key failed</option>
                    <option value="wait_for_previous">Wait for previous — reschedule until previous action completes</option>
                    <option value="skip_if_previous_pending">Skip if previous pending — cancel if another action is still pending</option>
                </select>
                <div class="form-text">
                    Condition evaluated at execution time. Useful for chaining actions.
                </div>
            </div>

            <div v-if="coordination.on_execute_condition" class="mb-0">
                <label class="form-label">If Condition Not Met</label>
                <select class="form-select" :value="coordination.on_condition_not_met" @change="updateField('on_condition_not_met', $event.target.value)" style="max-width: 300px;">
                    <option value="cancel">Cancel action</option>
                    <option value="fail">Mark as failed</option>
                    <option value="reschedule" v-if="coordination.on_execute_condition === 'wait_for_previous'">Reschedule (retry later)</option>
                </select>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CoordinationForm',
    props: {
        keysText: {
            type: String,
            default: '',
        },
        coordination: {
            type: Object,
            required: true,
            // Expected shape: { on_create: '', on_execute_condition: '', on_condition_not_met: 'cancel' }
        },
        initialExpanded: {
            type: Boolean,
            default: false,
        },
        description: {
            type: String,
            default: 'Dedup keys let you link related actions and control their behavior.',
        },
        keysLabel: {
            type: String,
            default: 'Dedup Keys',
        },
        keysPlaceholder: {
            type: String,
            default: 'e.g. deployment:prod, user:123',
        },
        showPlaceholderHint: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:keysText', 'update:coordination'],
    data() {
        return {
            expanded: this.initialExpanded,
        };
    },
    watch: {
        initialExpanded(val) {
            if (val) this.expanded = true;
        },
    },
    methods: {
        updateField(field, value) {
            this.$emit('update:coordination', {
                ...this.coordination,
                [field]: value,
            });
        },
    },
};
</script>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}
</style>
