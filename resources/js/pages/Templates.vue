<template>
    <div class="container py-4">
        <!-- Confirm Modal -->
        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirm-text="confirmModal.confirmText"
            :variant="confirmModal.variant"
            @confirm="handleConfirm"
            @cancel="confirmModal.show = false"
        />

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Action Templates</h2>
                <p class="text-muted mb-0">Reusable action configurations with callable URLs</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <!-- Plan limit badge -->
                <span v-if="limits" class="badge bg-light text-dark border">
                    {{ limits.current }} / {{ limits.max }} templates
                </span>
                <router-link
                    to="/templates/create"
                    class="btn btn-cml-primary"
                    :class="{ disabled: limits && limits.current >= limits.max }"
                >
                    Create Template
                </router-link>
            </div>
        </div>

        <!-- Limit warning -->
        <div v-if="limits && limits.current >= limits.max" class="alert alert-warning mb-4">
            <strong>Template limit reached.</strong>
            You've used all {{ limits.max }} templates available on your plan.
            <a href="/pricing" class="alert-link">Upgrade</a> to create more.
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-muted" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="templates.length === 0" class="text-center py-5">
            <div class="mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="text-muted">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
            </div>
            <h5 class="text-muted mb-2">No templates yet</h5>
            <p class="text-muted mb-4">
                Templates let you create reusable action configurations with callable URLs.
                <br>Trigger actions via a simple POST request to your template's unique URL.
            </p>
            <router-link to="/templates/create" class="btn btn-cml-primary">
                Create your first template
            </router-link>
        </div>

        <!-- Templates table -->
        <div v-else class="card card-cml">
            <div class="table-responsive">
                <table class="table table-cml mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Trigger URL</th>
                            <th>Mode</th>
                            <th>Placeholders</th>
                            <th>Trigger Count</th>
                            <th>Last Triggered</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="template in templates" :key="template.id">
                            <td>
                                <div>
                                    <router-link :to="`/templates/${template.id}/edit`" class="text-decoration-none fw-medium">
                                        {{ template.name }}
                                    </router-link>
                                    <div v-if="template.description" class="text-muted small text-truncate" style="max-width: 250px;">
                                        {{ template.description }}
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <code class="small text-truncate" style="max-width: 200px;" :title="template.trigger_url">
                                        {{ template.trigger_url }}
                                    </code>
                                    <button
                                        class="btn btn-sm btn-outline-secondary py-0 px-1"
                                        @click="copyTriggerUrl(template)"
                                        title="Copy to clipboard"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <span class="mode-label">
                                    <span v-if="template.mode === 'immediate'">&#9889;</span>
                                    <span v-else>&#128275;</span>
                                    {{ template.mode === 'immediate' ? 'Immediate' : 'Gated' }}
                                </span>
                            </td>
                            <td>
                                <span v-if="template.placeholders && template.placeholders.length" class="badge bg-light text-dark border">
                                    {{ template.placeholders.length }} placeholder{{ template.placeholders.length !== 1 ? 's' : '' }}
                                </span>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td>
                                <span class="fw-medium">{{ template.trigger_count.toLocaleString() }}</span>
                            </td>
                            <td>
                                <span v-if="template.last_triggered_at">{{ formatDate(template.last_triggered_at) }}</span>
                                <span v-else class="text-muted">Never</span>
                            </td>
                            <td>
                                <div class="form-check form-switch mb-0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        :checked="template.is_active"
                                        @change="toggleActive(template)"
                                        :id="`active-${template.id}`"
                                    >
                                    <label class="form-check-label small" :for="`active-${template.id}`">
                                        {{ template.is_active ? 'Active' : 'Inactive' }}
                                    </label>
                                </div>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                                        &#8943;
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button class="dropdown-item" @click="copyTriggerUrl(template)">
                                                Copy Trigger URL
                                            </button>
                                        </li>
                                        <li>
                                            <router-link :to="`/templates/${template.id}/edit`" class="dropdown-item">
                                                Edit
                                            </router-link>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item" @click="confirmRegenerateToken(template)">
                                                Regenerate Token
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-danger" @click="confirmDelete(template)">
                                                Delete
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination">
                    <li class="page-item" :class="{ disabled: meta.current_page === 1 }">
                        <button class="page-link" @click="loadTemplates(meta.current_page - 1)">&laquo;</button>
                    </li>
                    <li
                        v-for="page in paginationPages"
                        :key="page"
                        class="page-item"
                        :class="{ active: page === meta.current_page, disabled: page === '...' }"
                    >
                        <button
                            class="page-link"
                            @click="page !== '...' && loadTemplates(page)"
                            :disabled="page === '...'"
                        >
                            {{ page }}
                        </button>
                    </li>
                    <li class="page-item" :class="{ disabled: meta.current_page === meta.last_page }">
                        <button class="page-link" @click="loadTemplates(meta.current_page + 1)">&raquo;</button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { formatDate } from '../utils/dateFormatting';
import ConfirmModal from '../components/ConfirmModal.vue';

export default {
    name: 'Templates',
    components: {
        ConfirmModal,
    },
    inject: ['toast'],
    data() {
        return {
            templates: [],
            loading: true,
            limits: null,
            meta: {
                current_page: 1,
                last_page: 1,
            },
            confirmModal: {
                show: false,
                title: '',
                message: '',
                confirmText: 'Confirm',
                variant: 'danger',
                action: null,
            },
        };
    },
    computed: {
        paginationPages() {
            const pages = [];
            const total = this.meta.last_page;
            const current = this.meta.current_page;

            if (total <= 7) {
                for (let i = 1; i <= total; i++) pages.push(i);
            } else {
                pages.push(1);
                if (current > 3) pages.push('...');
                for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
                    pages.push(i);
                }
                if (current < total - 2) pages.push('...');
                pages.push(total);
            }
            return pages;
        },
    },
    mounted() {
        this.loadTemplates();
        this.loadLimits();
    },
    methods: {
        formatDate,
        async loadTemplates(page = 1) {
            this.loading = true;
            try {
                const response = await axios.get('/api/v1/templates', {
                    params: { page },
                });
                this.templates = response.data.data;
                this.meta = response.data.meta;
            } catch (err) {
                console.error('Failed to load templates:', err);
                this.toast?.error('Failed to load templates');
            } finally {
                this.loading = false;
            }
        },
        async loadLimits() {
            try {
                const response = await axios.get('/api/v1/templates/limits');
                this.limits = response.data;
            } catch (err) {
                console.error('Failed to load limits:', err);
            }
        },
        copyTriggerUrl(template) {
            navigator.clipboard.writeText(template.trigger_url);
            this.toast?.success('Trigger URL copied to clipboard');
        },
        async toggleActive(template) {
            try {
                const response = await axios.post(`/api/v1/templates/${template.id}/toggle-active`);
                template.is_active = response.data.data.is_active;
                this.toast?.success(template.is_active ? 'Template activated' : 'Template deactivated');
            } catch (err) {
                console.error('Failed to toggle template:', err);
                this.toast?.error('Failed to update template');
            }
        },
        confirmRegenerateToken(template) {
            this.confirmModal = {
                show: true,
                title: 'Regenerate Token',
                message: `This will generate a new trigger URL for "${template.name}". The old URL will stop working immediately. Are you sure?`,
                confirmText: 'Regenerate',
                variant: 'warning',
                action: () => this.regenerateToken(template),
            };
        },
        async regenerateToken(template) {
            try {
                const response = await axios.post(`/api/v1/templates/${template.id}/regenerate-token`);
                template.trigger_token = response.data.data.trigger_token;
                template.trigger_url = response.data.data.trigger_url;
                this.toast?.success('Token regenerated. Copy the new trigger URL.');
                this.copyTriggerUrl(template);
            } catch (err) {
                console.error('Failed to regenerate token:', err);
                this.toast?.error('Failed to regenerate token');
            }
        },
        confirmDelete(template) {
            this.confirmModal = {
                show: true,
                title: 'Delete Template',
                message: `Are you sure you want to delete "${template.name}"? This action cannot be undone.`,
                confirmText: 'Delete',
                variant: 'danger',
                action: () => this.deleteTemplate(template),
            };
        },
        async deleteTemplate(template) {
            try {
                await axios.delete(`/api/v1/templates/${template.id}`);
                this.templates = this.templates.filter(t => t.id !== template.id);
                this.toast?.success('Template deleted');
                // Reload limits
                this.loadLimits();
            } catch (err) {
                console.error('Failed to delete template:', err);
                this.toast?.error('Failed to delete template');
            }
        },
        handleConfirm() {
            this.confirmModal.show = false;
            if (this.confirmModal.action) {
                this.confirmModal.action();
            }
        },
    },
};
</script>

<style scoped>
.mode-label {
    white-space: nowrap;
}

/* Fix dropdown overflow in table */
.table-responsive {
    overflow: visible;
}

.card-cml {
    overflow: visible;
}

.dropdown-menu {
    z-index: 1050;
}
</style>
