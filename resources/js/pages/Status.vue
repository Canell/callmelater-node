<template>
  <div class="status-page">
    <!-- Header -->
    <header class="status-header">
      <div class="container">
        <div class="d-flex align-items-center justify-content-between">
          <a href="/" class="status-logo">
            <img src="/images/callmelater-logo.svg" alt="CallMeLater" width="28" height="28">
            <span>CallMe<span style="color: #22C55E;">Later</span> Status</span>
          </a>
          <a href="https://callmelater.io" class="btn btn-sm btn-outline-secondary">
            Back to CallMeLater
          </a>
        </div>
      </div>
    </header>

    <!-- Overall Status Banner -->
    <section :class="['status-banner', `status-banner-${overallStatus}`]">
      <div class="container">
        <div class="d-flex align-items-center justify-content-center gap-3">
          <div :class="['status-indicator', `status-${overallStatus}`]"></div>
          <h1 class="status-headline mb-0">{{ overallStatusText }}</h1>
        </div>
        <p class="status-updated mb-0" v-if="lastUpdated">
          Last updated: {{ formatTime(lastUpdated) }}
        </p>
      </div>
    </section>

    <!-- Loading State -->
    <div v-if="loading" class="container py-5 text-center">
      <div class="spinner-border text-secondary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="container py-5">
      <div class="alert alert-warning text-center">
        <p class="mb-2">Unable to fetch current status.</p>
        <button @click="fetchStatus" class="btn btn-sm btn-outline-secondary">
          Try Again
        </button>
      </div>
    </div>

    <template v-else>
      <!-- Active Incidents -->
      <section v-if="activeIncidents.length > 0" class="container py-4">
        <h2 class="section-title">Active Incidents</h2>
        <div class="incident-list">
          <div
            v-for="incident in activeIncidents"
            :key="incident.id"
            :class="['incident-card', `incident-${incident.impact}`]"
          >
            <div class="incident-header">
              <span :class="['incident-badge', `badge-${incident.impact}`]">
                {{ incident.impact_label }}
              </span>
              <span class="incident-status">{{ incident.status_label }}</span>
            </div>
            <h3 class="incident-title">{{ incident.title }}</h3>
            <p class="incident-summary" v-if="incident.summary">{{ incident.summary }}</p>
            <div class="incident-meta">
              <span>Started {{ formatTime(incident.started_at) }}</span>
              <span v-if="incident.affected_components.length">
                Affecting: {{ incident.affected_components.map(c => c.name).join(', ') }}
              </span>
            </div>
          </div>
        </div>
      </section>

      <!-- Components Status -->
      <section class="container py-4">
        <h2 class="section-title">System Status</h2>
        <div class="components-list">
          <div
            v-for="component in components"
            :key="component.slug"
            class="component-row"
          >
            <div class="component-info">
              <span class="component-name">{{ component.name }}</span>
              <span class="component-desc" v-if="component.description">
                {{ component.description }}
              </span>
            </div>
            <div :class="['component-status', `status-${component.status}`]">
              <span class="status-dot"></span>
              <span class="status-text">{{ component.status_label }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Recent Incidents -->
      <section v-if="recentIncidents.length > 0" class="container py-4">
        <h2 class="section-title">Past Incidents</h2>
        <p class="section-subtitle">Last 90 days</p>
        <div class="incident-history">
          <div
            v-for="incident in recentIncidents"
            :key="incident.id"
            class="history-item"
          >
            <div class="history-date">
              {{ formatDate(incident.started_at) }}
            </div>
            <div class="history-content">
              <h4 class="history-title">{{ incident.title }}</h4>
              <p class="history-summary" v-if="incident.summary">{{ incident.summary }}</p>
              <div class="history-meta">
                <span :class="['history-impact', `impact-${incident.impact}`]">
                  {{ incident.impact_label }}
                </span>
                <span class="history-duration" v-if="incident.duration">
                  Duration: {{ incident.duration }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- No Incidents Message (only show if no active AND no recent incidents) -->
      <section v-if="recentIncidents.length === 0 && activeIncidents.length === 0" class="container py-4">
        <div class="no-incidents">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
          <p>No incidents reported in the last 90 days.</p>
        </div>
      </section>
    </template>

    <!-- Footer -->
    <footer class="status-footer">
      <div class="container">
        <p>&copy; {{ new Date().getFullYear() }} CallMeLater. All systems monitored 24/7.</p>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const loading = ref(true);
const error = ref(false);
const statusData = ref(null);
let refreshInterval = null;

const overallStatus = computed(() => statusData.value?.overall_status || 'operational');
const components = computed(() => statusData.value?.components || []);
const activeIncidents = computed(() => statusData.value?.active_incidents || []);
const recentIncidents = computed(() => statusData.value?.recent_incidents || []);
const lastUpdated = computed(() => statusData.value?.updated_at);

const overallStatusText = computed(() => {
  switch (overallStatus.value) {
    case 'operational':
      return 'All Systems Operational';
    case 'degraded':
      return 'Degraded Performance';
    case 'outage':
      return 'Service Disruption';
    default:
      return 'Status Unknown';
  }
});

async function fetchStatus() {
  try {
    error.value = false;
    const response = await axios.get('/api/public/status');
    statusData.value = response.data;
  } catch (err) {
    console.error('Failed to fetch status:', err);
    error.value = true;
  } finally {
    loading.value = false;
  }
}

function formatTime(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
  if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;

  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

onMounted(() => {
  fetchStatus();
  // Refresh every 60 seconds
  refreshInterval = setInterval(fetchStatus, 60000);
});

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
});
</script>

<style scoped>
.status-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: #f9fafb;
}

/* Header */
.status-header {
  background: white;
  border-bottom: 1px solid #e5e7eb;
  padding: 1rem 0;
}

.status-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
  color: #111827;
  font-weight: 600;
  font-size: 1.125rem;
}

/* Status Banner */
.status-banner {
  padding: 3rem 0;
  text-align: center;
  transition: background-color 0.3s;
}

.status-banner-operational {
  background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
}

.status-banner-degraded {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
}

.status-banner-outage {
  background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
}

.status-indicator {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

.status-indicator.status-operational {
  background-color: #22c55e;
  box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.2);
}

.status-indicator.status-degraded {
  background-color: #f59e0b;
  box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
}

.status-indicator.status-outage {
  background-color: #ef4444;
  box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

.status-headline {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
}

.status-updated {
  margin-top: 0.75rem;
  font-size: 0.875rem;
  color: #6b7280;
}

/* Section Titles */
.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #111827;
  margin-bottom: 0.5rem;
}

.section-subtitle {
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 1rem;
}

/* Components List */
.components-list {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.component-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #f3f4f6;
}

.component-row:last-child {
  border-bottom: none;
}

.component-info {
  display: flex;
  flex-direction: column;
}

.component-name {
  font-weight: 500;
  color: #111827;
}

.component-desc {
  font-size: 0.8125rem;
  color: #6b7280;
}

.component-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.component-status.status-operational .status-dot {
  background-color: #22c55e;
}

.component-status.status-degraded .status-dot {
  background-color: #f59e0b;
}

.component-status.status-outage .status-dot {
  background-color: #ef4444;
}

.component-status.status-operational .status-text {
  color: #15803d;
}

.component-status.status-degraded .status-text {
  color: #b45309;
}

.component-status.status-outage .status-text {
  color: #dc2626;
}

.status-text {
  font-size: 0.875rem;
  font-weight: 500;
}

/* Active Incidents */
.incident-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.incident-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1.25rem;
  border-left: 4px solid;
}

.incident-card.incident-minor {
  border-left-color: #f59e0b;
}

.incident-card.incident-major {
  border-left-color: #f97316;
}

.incident-card.incident-critical {
  border-left-color: #ef4444;
}

.incident-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.incident-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.125rem 0.5rem;
  border-radius: 4px;
  text-transform: uppercase;
}

.badge-minor {
  background-color: #fef3c7;
  color: #b45309;
}

.badge-major {
  background-color: #ffedd5;
  color: #c2410c;
}

.badge-critical {
  background-color: #fee2e2;
  color: #dc2626;
}

.incident-status {
  font-size: 0.8125rem;
  color: #6b7280;
}

.incident-title {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  margin-bottom: 0.5rem;
}

.incident-summary {
  font-size: 0.9375rem;
  color: #4b5563;
  margin-bottom: 0.75rem;
}

.incident-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  font-size: 0.8125rem;
  color: #6b7280;
}

/* Incident History */
.incident-history {
  display: flex;
  flex-direction: column;
}

.history-item {
  display: flex;
  gap: 1.5rem;
  padding: 1.25rem 0;
  border-bottom: 1px solid #e5e7eb;
}

.history-item:last-child {
  border-bottom: none;
}

.history-date {
  flex-shrink: 0;
  width: 100px;
  font-size: 0.8125rem;
  color: #6b7280;
}

.history-content {
  flex: 1;
}

.history-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #111827;
  margin-bottom: 0.25rem;
}

.history-summary {
  font-size: 0.875rem;
  color: #4b5563;
  margin-bottom: 0.5rem;
}

.history-meta {
  display: flex;
  gap: 1rem;
  font-size: 0.8125rem;
}

.history-impact {
  font-weight: 500;
}

.impact-minor {
  color: #b45309;
}

.impact-major {
  color: #c2410c;
}

.impact-critical {
  color: #dc2626;
}

.history-duration {
  color: #6b7280;
}

/* No Incidents */
.no-incidents {
  text-align: center;
  padding: 3rem;
  color: #22c55e;
}

.no-incidents svg {
  margin-bottom: 1rem;
}

.no-incidents p {
  color: #6b7280;
  font-size: 0.9375rem;
}

/* Footer */
.status-footer {
  margin-top: auto;
  padding: 2rem 0;
  background: white;
  border-top: 1px solid #e5e7eb;
  text-align: center;
}

.status-footer p {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
  .history-item {
    flex-direction: column;
    gap: 0.5rem;
  }

  .history-date {
    width: auto;
  }

  .component-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}
</style>
