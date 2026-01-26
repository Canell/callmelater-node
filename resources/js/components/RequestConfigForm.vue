<template>
    <div class="card card-cml mb-4">
        <div class="card-header bg-transparent">
            <h5 class="mb-0">{{ title }}</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Method</label>
                    <select class="form-select" :value="request.method" @change="updateRequest('method', $event.target.value)">
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
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': urlError }"
                            :value="request.url"
                            @input="updateRequest('url', $event.target.value)"
                            @blur="$emit('validate-url')"
                            :required="urlRequired"
                            :placeholder="urlPlaceholder"
                        >
                        <div v-if="urlValidating" class="position-absolute top-50 end-0 translate-middle-y pe-3">
                            <span class="spinner-border spinner-border-sm text-muted"></span>
                        </div>
                    </div>
                    <div v-if="urlError" class="invalid-feedback d-block">{{ urlError }}</div>
                    <div v-if="showPlaceholderHint" class="form-text">You can use placeholders: <code v-pre>{{variable}}</code></div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Headers (JSON)</label>
                <textarea
                    class="form-control font-monospace"
                    :class="{ 'is-invalid': headersError }"
                    :value="headersJson"
                    @input="$emit('update:headersJson', $event.target.value)"
                    rows="3"
                    :placeholder="headersPlaceholder"
                ></textarea>
                <div v-if="headersError" class="invalid-feedback">{{ headersError }}</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Body (JSON)</label>
                <textarea
                    class="form-control font-monospace"
                    :class="{ 'is-invalid': bodyError }"
                    :value="bodyJson"
                    @input="$emit('update:bodyJson', $event.target.value)"
                    rows="4"
                    :placeholder="bodyPlaceholder"
                ></textarea>
                <div v-if="bodyError" class="invalid-feedback">{{ bodyError }}</div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Max Attempts</label>
                    <input
                        type="number"
                        class="form-control"
                        :value="maxAttempts"
                        @input="$emit('update:maxAttempts', parseInt($event.target.value))"
                        min="1"
                        max="10"
                    >
                </div>
                <div class="col-md-4">
                    <label class="form-label">
                        Retry Strategy
                        <span
                            v-if="showRetryTooltip"
                            class="text-muted ms-1"
                            role="button"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            data-bs-html="true"
                            :title="retryTooltipContent"
                            @mouseenter="initTooltip"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                        </span>
                    </label>
                    <select class="form-select" :value="retryStrategy" @change="$emit('update:retryStrategy', $event.target.value)">
                        <option value="exponential">Exponential Backoff</option>
                        <option value="linear">Linear</option>
                    </select>
                </div>
            </div>
            <div class="mt-2">
                <small class="text-muted">Retries only occur when the request fails or times out.</small>
                <a v-if="showDocsLink" href="https://docs.callmelater.io/reference/retry-behavior" target="_blank" class="small ms-2">Learn more</a>
            </div>

            <!-- Test Request slot for CreateAction -->
            <slot name="test-request"></slot>
        </div>
    </div>
</template>

<script>
import { Tooltip } from 'bootstrap';

export default {
    name: 'RequestConfigForm',
    props: {
        title: {
            type: String,
            default: 'HTTP Request',
        },
        request: {
            type: Object,
            required: true,
            // Expected shape: { method: 'POST', url: '' }
        },
        headersJson: {
            type: String,
            default: '',
        },
        bodyJson: {
            type: String,
            default: '',
        },
        maxAttempts: {
            type: Number,
            default: 5,
        },
        retryStrategy: {
            type: String,
            default: 'exponential',
        },
        urlError: {
            type: String,
            default: '',
        },
        headersError: {
            type: String,
            default: '',
        },
        bodyError: {
            type: String,
            default: '',
        },
        urlValidating: {
            type: Boolean,
            default: false,
        },
        urlRequired: {
            type: Boolean,
            default: true,
        },
        urlPlaceholder: {
            type: String,
            default: 'https://api.example.com/webhook',
        },
        headersPlaceholder: {
            type: String,
            default: '{"Authorization": "Bearer YOUR_API_TOKEN"}',
        },
        bodyPlaceholder: {
            type: String,
            default: '{"event": "deploy", "version": "2.1"}',
        },
        showPlaceholderHint: {
            type: Boolean,
            default: false,
        },
        showRetryTooltip: {
            type: Boolean,
            default: false,
        },
        showDocsLink: {
            type: Boolean,
            default: true,
        },
    },
    emits: ['update:request', 'update:headersJson', 'update:bodyJson', 'update:maxAttempts', 'update:retryStrategy', 'validate-url'],
    computed: {
        retryTooltipContent() {
            return `
                <strong>Exponential:</strong> 1m, 5m, 30m, 2h, 12h<br>
                <strong>Linear:</strong> 5m, 10m, 15m, 20m, 25m
            `;
        },
    },
    methods: {
        updateRequest(field, value) {
            this.$emit('update:request', {
                ...this.request,
                [field]: value,
            });
        },
        initTooltip(event) {
            const el = event.target.closest('[data-bs-toggle="tooltip"]');
            if (el && !el._tooltip) {
                el._tooltip = new Tooltip(el);
                el._tooltip.show();
            }
        },
    },
};
</script>
