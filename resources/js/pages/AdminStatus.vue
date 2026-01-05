<template>
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <router-link to="/admin">Admin</router-link>
                        </li>
                        <li class="breadcrumb-item active">Status Page</li>
                    </ol>
                </nav>
                <h2 class="mb-0">Status Page Management</h2>
            </div>
            <div class="d-flex gap-2">
                <a href="/status" target="_blank" class="btn btn-outline-secondary">
                    View Public Status
                </a>
                <button class="btn btn-primary" @click="showCreateIncident = true">
                    Report Incident
                </button>
            </div>
        </div>

        <!-- Components Section -->
        <div class="card card-cml mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">System Components</h5>
            </div>
            <div class="card-body p-0">
                <div v-if="loadingComponents" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-muted"></div>
                </div>
                <div v-else class="table-responsive">
                    <table class="table table-cml mb-0">
                        <thead>
                            <tr>
                                <th>Component</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Visible</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="component in components" :key="component.id">
                                <td class="fw-medium">{{ component.name }}</td>
                                <td class="text-muted small">{{ component.description || '-' }}</td>
                                <td>
                                    <span :class="statusBadgeClass(component.current_status)">
                                        {{ component.status_label }}
                                    </span>
                                </td>
                                <td>
                                    <span v-if="component.is_visible" class="text-success">Yes</span>
                                    <span v-else class="text-muted">No</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button
                                            class="btn btn-outline-success"
                                            :class="{ active: component.current_status === 'operational' }"
                                            @click="updateComponentStatus(component, 'operational')"
                                            :disabled="updatingComponent === component.id"
                                            title="Operational"
                                        >
                                            <span v-if="updatingComponent === component.id" class="spinner-border spinner-border-sm"></span>
                                            <span v-else>OK</span>
                                        </button>
                                        <button
                                            class="btn btn-outline-warning"
                                            :class="{ active: component.current_status === 'degraded' }"
                                            @click="updateComponentStatus(component, 'degraded')"
                                            :disabled="updatingComponent === component.id"
                                            title="Degraded"
                                        >
                                            Deg
                                        </button>
                                        <button
                                            class="btn btn-outline-danger"
                                            :class="{ active: component.current_status === 'outage' }"
                                            @click="updateComponentStatus(component, 'outage')"
                                            :disabled="updatingComponent === component.id"
                                            title="Outage"
                                        >
                                            Out
                                        </button>
                                    </div>
                                    <button
                                        class="btn btn-sm btn-link text-muted ms-2"
                                        @click="showComponentHistory(component)"
                                        title="View History"
                                    >
                                        History
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Active Incidents Section -->
        <div class="card card-cml mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Active Incidents</h5>
                <span v-if="activeIncidents.length" class="badge bg-danger">{{ activeIncidents.length }}</span>
            </div>
            <div class="card-body">
                <div v-if="loadingIncidents" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-muted"></div>
                </div>
                <div v-else-if="activeIncidents.length === 0" class="text-center py-4 text-muted">
                    No active incidents
                </div>
                <div v-else class="list-group list-group-flush">
                    <div
                        v-for="incident in activeIncidents"
                        :key="incident.id"
                        class="list-group-item px-0"
                    >
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">
                                    <span :class="impactBadgeClass(incident.impact)" class="me-2">
                                        {{ incident.impact_label }}
                                    </span>
                                    {{ incident.title }}
                                </h6>
                                <p class="mb-1 text-muted small">{{ incident.summary || 'No summary' }}</p>
                                <div class="small text-muted">
                                    <span class="me-3">Started: {{ formatTime(incident.started_at) }}</span>
                                    <span>Affecting: {{ incident.affected_components.join(', ') }}</span>
                                </div>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button
                                    v-for="status in incidentStatuses"
                                    :key="status.value"
                                    class="btn"
                                    :class="[
                                        incident.status === status.value ? 'btn-secondary' : 'btn-outline-secondary',
                                    ]"
                                    @click="updateIncidentStatus(incident, status.value)"
                                    :disabled="updatingIncident === incident.id"
                                >
                                    {{ status.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Resolved Incidents -->
        <div class="card card-cml">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Resolved Incidents</h5>
            </div>
            <div class="card-body p-0">
                <div v-if="loadingIncidents" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-muted"></div>
                </div>
                <div v-else-if="resolvedIncidents.length === 0" class="text-center py-4 text-muted">
                    No recent incidents
                </div>
                <div v-else class="table-responsive">
                    <table class="table table-cml mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Impact</th>
                                <th>Components</th>
                                <th>Duration</th>
                                <th>Resolved</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="incident in resolvedIncidents" :key="incident.id">
                                <td>{{ incident.title }}</td>
                                <td>
                                    <span :class="impactBadgeClass(incident.impact)">
                                        {{ incident.impact_label }}
                                    </span>
                                </td>
                                <td class="small text-muted">{{ incident.affected_components.join(', ') }}</td>
                                <td class="small">{{ incident.duration }}</td>
                                <td class="small text-muted">{{ formatTime(incident.resolved_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create Incident Modal -->
        <div v-if="showCreateIncident" class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Report New Incident</h5>
                        <button type="button" class="btn-close" @click="showCreateIncident = false"></button>
                    </div>
                    <form @submit.prevent="createIncident">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    v-model="newIncident.title"
                                    required
                                    placeholder="Brief description of the incident"
                                >
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Impact Level</label>
                                <select class="form-select" v-model="newIncident.impact" required>
                                    <option value="minor">Minor - Some users may experience issues</option>
                                    <option value="major">Major - Significant impact on service</option>
                                    <option value="critical">Critical - Service is down</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Affected Components</label>
                                <div class="row">
                                    <div v-for="component in components" :key="component.id" class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                :id="'component-' + component.id"
                                                :value="component.id"
                                                v-model="newIncident.component_ids"
                                            >
                                            <label class="form-check-label" :for="'component-' + component.id">
                                                {{ component.name }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Summary (optional)</label>
                                <textarea
                                    class="form-control"
                                    v-model="newIncident.summary"
                                    rows="3"
                                    placeholder="Detailed description of the incident and its impact"
                                ></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showCreateIncident = false">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="btn btn-danger"
                                :disabled="creatingIncident || newIncident.component_ids.length === 0"
                            >
                                <span v-if="creatingIncident" class="spinner-border spinner-border-sm me-1"></span>
                                Report Incident
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Component History Modal -->
        <div v-if="selectedComponent" class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ selectedComponent.name }} - Status History</h5>
                        <button type="button" class="btn-close" @click="selectedComponent = null"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="loadingHistory" class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-muted"></div>
                        </div>
                        <div v-else-if="componentHistory.length === 0" class="text-center py-4 text-muted">
                            No history available
                        </div>
                        <div v-else class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Message</th>
                                        <th>By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="event in componentHistory" :key="event.id">
                                        <td class="small text-muted">{{ formatTime(event.created_at) }}</td>
                                        <td>
                                            <span :class="statusBadgeClass(event.status)">
                                                {{ event.status_label }}
                                            </span>
                                        </td>
                                        <td class="small">{{ event.message || '-' }}</td>
                                        <td class="small text-muted">{{ event.created_by || 'System' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="selectedComponent = null">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'AdminStatus',
    data() {
        return {
            components: [],
            incidents: [],
            loadingComponents: true,
            loadingIncidents: true,
            updatingComponent: null,
            updatingIncident: null,
            showCreateIncident: false,
            creatingIncident: false,
            selectedComponent: null,
            componentHistory: [],
            loadingHistory: false,
            newIncident: {
                title: '',
                impact: 'minor',
                component_ids: [],
                summary: '',
            },
            incidentStatuses: [
                { value: 'investigating', label: 'Inv' },
                { value: 'identified', label: 'Id' },
                { value: 'monitoring', label: 'Mon' },
                { value: 'resolved', label: 'Res' },
            ],
        };
    },
    computed: {
        activeIncidents() {
            return this.incidents.filter(i => i.status !== 'resolved');
        },
        resolvedIncidents() {
            return this.incidents.filter(i => i.status === 'resolved').slice(0, 10);
        },
    },
    mounted() {
        this.loadComponents();
        this.loadIncidents();
    },
    methods: {
        async loadComponents() {
            try {
                const response = await axios.get('/api/admin/status/components');
                this.components = response.data.data;
            } catch (err) {
                console.error('Failed to load components:', err);
            } finally {
                this.loadingComponents = false;
            }
        },
        async loadIncidents() {
            try {
                const response = await axios.get('/api/admin/status/incidents');
                this.incidents = response.data.data;
            } catch (err) {
                console.error('Failed to load incidents:', err);
            } finally {
                this.loadingIncidents = false;
            }
        },
        async updateComponentStatus(component, status) {
            if (component.current_status === status) return;

            this.updatingComponent = component.id;
            try {
                await axios.patch(`/api/admin/status/components/${component.id}`, { status });
                component.current_status = status;
                component.status_label = this.getStatusLabel(status);
            } catch (err) {
                console.error('Failed to update component:', err);
                alert('Failed to update component status');
            } finally {
                this.updatingComponent = null;
            }
        },
        async updateIncidentStatus(incident, status) {
            if (incident.status === status) return;

            this.updatingIncident = incident.id;
            try {
                await axios.patch(`/api/admin/status/incidents/${incident.id}`, { status });
                incident.status = status;
                if (status === 'resolved') {
                    // Refresh to get updated data
                    await Promise.all([this.loadComponents(), this.loadIncidents()]);
                }
            } catch (err) {
                console.error('Failed to update incident:', err);
                alert('Failed to update incident status');
            } finally {
                this.updatingIncident = null;
            }
        },
        async createIncident() {
            this.creatingIncident = true;
            try {
                await axios.post('/api/admin/status/incidents', this.newIncident);
                this.showCreateIncident = false;
                this.newIncident = {
                    title: '',
                    impact: 'minor',
                    component_ids: [],
                    summary: '',
                };
                await Promise.all([this.loadComponents(), this.loadIncidents()]);
            } catch (err) {
                console.error('Failed to create incident:', err);
                alert('Failed to create incident: ' + (err.response?.data?.message || err.message));
            } finally {
                this.creatingIncident = false;
            }
        },
        async showComponentHistory(component) {
            this.selectedComponent = component;
            this.loadingHistory = true;
            try {
                const response = await axios.get(`/api/admin/status/components/${component.id}/history`);
                this.componentHistory = response.data.data;
            } catch (err) {
                console.error('Failed to load history:', err);
            } finally {
                this.loadingHistory = false;
            }
        },
        statusBadgeClass(status) {
            const classes = {
                operational: 'badge bg-success',
                degraded: 'badge bg-warning text-dark',
                outage: 'badge bg-danger',
            };
            return classes[status] || 'badge bg-secondary';
        },
        impactBadgeClass(impact) {
            const classes = {
                minor: 'badge bg-warning text-dark',
                major: 'badge bg-orange',
                critical: 'badge bg-danger',
            };
            return classes[impact] || 'badge bg-secondary';
        },
        getStatusLabel(status) {
            const labels = {
                operational: 'Operational',
                degraded: 'Degraded Performance',
                outage: 'Outage',
            };
            return labels[status] || status;
        },
        formatTime(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);

            if (diffMins < 1) return 'just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;

            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
    },
};
</script>

<style scoped>
.bg-orange {
    background-color: #f97316;
    color: white;
}

.btn-group .btn.active {
    font-weight: bold;
}
</style>
