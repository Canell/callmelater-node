<template>
    <div class="container py-4">
        <h2 class="mb-4">Settings</h2>

        <!-- Confirm Modal -->
        <ConfirmModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirm-text="confirmModal.confirmText"
            :cancel-text="confirmModal.cancelText"
            :variant="confirmModal.variant"
            @confirm="handleConfirm"
            @cancel="confirmModal.show = false"
        />

        <div class="row">
            <!-- Left nav -->
            <div class="col-md-3 mb-4">
                <div class="list-group">
                    <button
                        v-for="tab in visibleTabs"
                        :key="tab.id"
                        class="list-group-item list-group-item-action"
                        :class="{ active: activeTab === tab.id }"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="col-md-9">
                <!-- Loading -->
                <div v-if="loading" class="text-center py-5">
                    <div class="spinner-border text-muted" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>

                <template v-else>
                    <!-- Profile -->
                    <div v-show="activeTab === 'profile'" class="card card-cml">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Profile</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <label class="form-label text-muted">Email</label>
                                <div class="d-flex align-items-center">
                                    <strong>{{ user?.email }}</strong>
                                    <span v-if="user?.email_verified_at" class="badge bg-success ms-2">Verified</span>
                                    <span v-else class="badge bg-warning ms-2">Unverified</span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-muted">Timezone</label>
                                <select class="form-select" v-model="profile.timezone" style="max-width: 300px;">
                                    <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                </select>
                                <small class="text-muted">Used for displaying dates and scheduling actions</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-muted">Member Since</label>
                                <div>{{ formatDate(user?.created_at) }}</div>
                            </div>

                            <button class="btn btn-cml-primary" @click="saveProfile" :disabled="savingProfile">
                                {{ savingProfile ? 'Saving...' : 'Save Changes' }}
                            </button>
                            <span v-if="profileSaved" class="text-success ms-2">Saved!</span>

                            <hr class="my-4">

                            <h6 class="mb-3">Change Password</h6>
                            <div class="row g-3" style="max-width: 400px;">
                                <div class="col-12">
                                    <input type="password" class="form-control" v-model="passwordForm.current_password" placeholder="Current password">
                                </div>
                                <div class="col-12">
                                    <input type="password" class="form-control" v-model="passwordForm.password" placeholder="New password">
                                </div>
                                <div class="col-12">
                                    <input type="password" class="form-control" v-model="passwordForm.password_confirmation" placeholder="Confirm new password">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-outline-secondary" @click="changePassword" :disabled="changingPassword">
                                        {{ changingPassword ? 'Updating...' : 'Update Password' }}
                                    </button>
                                    <span v-if="passwordChanged" class="text-success ms-2">Password updated!</span>
                                    <div v-if="passwordError" class="text-danger mt-2">{{ passwordError }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Members (Workspace) -->
                    <div v-show="activeTab === 'teams'" class="card card-cml">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Workspace Members</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-4">
                                <strong>All members share the same plan and usage limits.</strong>
                                Invite team members to collaborate on scheduled actions within your workspace.
                            </div>

                            <!-- Create new workspace/team -->
                            <div class="bg-light p-3 rounded mb-4">
                                <h6 class="mb-3">Create New Workspace</h6>
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label">Workspace Name</label>
                                        <input type="text" class="form-control" v-model="newTeamName" placeholder="e.g. Engineering, Operations">
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-cml-primary" @click="createTeam" :disabled="!newTeamName || creatingTeam">
                                            {{ creatingTeam ? 'Creating...' : 'Create Workspace' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Workspaces list -->
                            <div v-if="loadingTeams" class="text-center py-4">
                                <div class="spinner-border spinner-border-sm text-muted" role="status"></div>
                            </div>

                            <div v-else-if="teams.length === 0" class="text-center py-4 text-muted">
                                <p>No workspaces yet. Create your first workspace above.</p>
                            </div>

                            <div v-else>
                                <div v-for="team in teams" :key="team.id" class="card mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ team.name }}</strong>
                                            <span v-if="team.owner.id === user?.id" class="badge bg-primary ms-2">Owner</span>
                                        </div>
                                        <div>
                                            <button
                                                v-if="team.owner.id === user?.id"
                                                class="btn btn-sm btn-outline-danger"
                                                @click="confirmDeleteTeam(team)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Members -->
                                        <h6 class="mb-2">Members ({{ team.member_count }})</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-3">
                                                <tbody>
                                                    <tr v-for="member in team.members" :key="member.id">
                                                        <td>
                                                            {{ member.name }}
                                                            <span class="text-muted">({{ member.email }})</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge" :class="member.role === 'owner' ? 'bg-primary' : 'bg-secondary'">
                                                                {{ member.role }}
                                                            </span>
                                                        </td>
                                                        <td class="text-end">
                                                            <button
                                                                v-if="member.role !== 'owner' && team.owner.id === user?.id"
                                                                class="btn btn-sm btn-outline-secondary"
                                                                @click="confirmRemoveMember(team, member)"
                                                            >
                                                                Remove
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Add member (only for owners) -->
                                        <div v-if="team.owner.id === user?.id" class="row g-2 align-items-end">
                                            <div class="col">
                                                <input
                                                    type="email"
                                                    class="form-control form-control-sm"
                                                    v-model="team._addEmail"
                                                    placeholder="email@example.com"
                                                >
                                            </div>
                                            <div class="col-auto">
                                                <button
                                                    class="btn btn-sm btn-outline-primary"
                                                    @click="addMember(team)"
                                                    :disabled="!team._addEmail || addingMember"
                                                >
                                                    Invite Member
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API Keys -->
                    <div v-show="activeTab === 'api'" class="card card-cml">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">API Keys</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                Use API keys to authenticate requests to the CallMeLater API.
                                <a href="/docs/api" target="_blank">View API documentation</a>
                            </p>

                            <!-- Existing tokens -->
                            <div v-if="tokens.length" class="mb-4">
                                <table class="table table-cml">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Created</th>
                                            <th>Last Used</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="token in tokens" :key="token.id">
                                            <td>
                                                <strong>{{ token.name }}</strong>
                                                <div class="small text-muted">{{ token.abilities?.join(', ') || 'Full access' }}</div>
                                            </td>
                                            <td>{{ formatDate(token.created_at) }}</td>
                                            <td>{{ token.last_used_at ? formatDate(token.last_used_at) : 'Never' }}</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger" @click="confirmRevokeToken(token)">
                                                    Revoke
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- New token form -->
                            <div class="bg-light p-3 rounded">
                                <h6 class="mb-3">Create New API Key</h6>
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label">Key Name</label>
                                        <input type="text" class="form-control" v-model="newTokenName" placeholder="e.g. Production, CLI, Testing">
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-cml-primary" @click="createToken" :disabled="!newTokenName || creatingToken">
                                            {{ creatingToken ? 'Creating...' : 'Create Key' }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Show newly created token -->
                                <div v-if="newlyCreatedToken" class="alert alert-success mt-3 mb-0">
                                    <strong>API Key Created!</strong> Copy it now — you won't see it again.
                                    <div class="input-group mt-2">
                                        <input type="text" class="form-control font-monospace" :value="newlyCreatedToken" readonly>
                                        <button class="btn btn-outline-secondary" @click="copyToken">
                                            {{ tokenCopied ? 'Copied!' : 'Copy' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Webhook signing -->
                            <h6 class="mb-3">Webhook Signing</h6>
                            <p class="text-muted">
                                Webhook requests include a signature header (<code>X-CallMeLater-Signature</code>) so you can verify they came from us.
                            </p>
                            <div class="mb-3" style="max-width: 500px;">
                                <label class="form-label">Default Webhook Secret</label>
                                <div class="input-group">
                                    <input
                                        :type="showWebhookSecret ? 'text' : 'password'"
                                        class="form-control font-monospace"
                                        :value="webhookSecret || 'Not set'"
                                        readonly
                                    >
                                    <button class="btn btn-outline-secondary" @click="showWebhookSecret = !showWebhookSecret">
                                        {{ showWebhookSecret ? 'Hide' : 'Show' }}
                                    </button>
                                    <button class="btn btn-outline-secondary" @click="confirmRegenerateWebhookSecret">
                                        Regenerate
                                    </button>
                                </div>
                                <small class="text-muted">Override per-action using the <code>webhook_secret</code> parameter</small>
                            </div>

                            <hr class="my-4">

                            <!-- IP info -->
                            <h6 class="mb-3">IP Allowlisting</h6>
                            <p class="text-muted">
                                Webhook requests come from this IP address. Add it to your firewall allowlist.
                            </p>
                            <div class="input-group" style="max-width: 300px;">
                                <input type="text" class="form-control font-monospace" :value="outboundIp" readonly>
                                <button class="btn btn-outline-secondary" @click="copyIp">
                                    {{ ipCopied ? 'Copied!' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Usage -->
                    <div v-show="activeTab === 'usage'" class="card card-cml">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Usage & Limits</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Current Plan</label>
                                        <div>
                                            <strong class="text-capitalize">{{ usage.plan || 'Free' }}</strong>
                                            <router-link to="/pricing" class="ms-2 small">Upgrade</router-link>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Billing Period</label>
                                        <div>{{ usage.billing_period || 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="mb-3">This Month</h6>
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <div class="display-6">{{ usage.actions_created || 0 }}</div>
                                            <small class="text-muted">Actions Created</small>
                                            <div class="small text-muted mt-1">Limit: {{ usage.limits?.actions_per_month || '∞' }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <div class="display-6">{{ usage.executions || 0 }}</div>
                                            <small class="text-muted">Executions</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <div class="display-6">{{ usage.reminders_sent || 0 }}</div>
                                            <small class="text-muted">Reminders Sent</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <h6 class="mb-3">Plan Limits</h6>
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted">Active actions</td>
                                            <td>{{ usage.limits?.active_actions || '∞' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Max retry attempts</td>
                                            <td>{{ usage.limits?.max_attempts || 3 }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Reminder recipients per action</td>
                                            <td>{{ usage.limits?.recipients_per_reminder || 5 }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">New recipients per day</td>
                                            <td>{{ usage.limits?.new_recipients_per_day || 5 }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">History retention</td>
                                            <td>{{ formatHistoryDays(usage.limits?.history_days) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Billing -->
                    <div v-show="activeTab === 'billing'" class="card card-cml">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Billing & Subscription</h5>
                        </div>
                        <div class="card-body">
                            <!-- Current Plan -->
                            <div class="mb-4">
                                <label class="form-label text-muted">Current Plan</label>
                                <div class="d-flex align-items-center">
                                    <strong class="text-capitalize fs-5">{{ billing.plan }}</strong>
                                    <span v-if="billing.is_manually_managed" class="badge bg-primary ms-2">Managed by Support</span>
                                    <span v-else-if="billing.canceled" class="badge bg-warning ms-2">Canceling</span>
                                    <span v-else-if="billing.on_trial" class="badge bg-info ms-2">Trial</span>
                                    <span v-else-if="billing.subscribed" class="badge bg-success ms-2">Active</span>
                                </div>
                                <div v-if="billing.canceled && billing.ends_at" class="text-muted small mt-1">
                                    Access until {{ formatDate(billing.ends_at) }}
                                </div>
                                <div v-if="billing.is_manually_managed && billing.manual_plan_expires_at" class="text-muted small mt-1">
                                    Access until {{ formatDate(billing.manual_plan_expires_at) }}
                                </div>
                            </div>

                            <!-- Manual Plan Info -->
                            <div v-if="billing.is_manually_managed" class="alert alert-info mb-4">
                                <strong>Your plan is managed by CallMeLater support.</strong>
                                <p class="mb-0 mt-1 small">
                                    If you have questions about your plan, please <router-link to="/contact">contact us</router-link>.
                                </p>
                            </div>

                            <!-- Actions based on subscription state -->
                            <div v-if="billing.plan === 'free'" class="mb-4">
                                <p class="text-muted">Upgrade to Pro or Business for more actions, retries, and features.</p>
                                <router-link to="/pricing" class="btn btn-cml-primary">
                                    View Plans & Upgrade
                                </router-link>
                            </div>

                            <div v-else-if="billing.can_manage" class="mb-4">
                                <!-- Manage subscription -->
                                <p class="text-muted mb-3">
                                    Manage your subscription, update payment methods, and view invoices in the billing portal.
                                </p>
                                <button class="btn btn-cml-primary me-2" @click="openBillingPortal" :disabled="openingPortal">
                                    {{ openingPortal ? 'Opening...' : 'Manage Subscription' }}
                                </button>
                                <router-link to="/pricing" class="btn btn-outline-secondary">
                                    Change Plan
                                </router-link>

                                <hr class="my-4">

                                <!-- Cancel / Resume -->
                                <div v-if="billing.canceled">
                                    <p class="text-muted">Your subscription is set to cancel. You can resume it to keep your current plan.</p>
                                    <button class="btn btn-outline-success" @click="resumeSubscription" :disabled="resumingSubscription">
                                        {{ resumingSubscription ? 'Resuming...' : 'Resume Subscription' }}
                                    </button>
                                </div>
                                <div v-else>
                                    <h6 class="text-muted">Cancel Subscription</h6>
                                    <p class="text-muted small">You'll keep access until the end of your billing period.</p>
                                    <button class="btn btn-outline-danger btn-sm" @click="confirmCancelSubscription" :disabled="cancelingSubscription">
                                        {{ cancelingSubscription ? 'Canceling...' : 'Cancel Subscription' }}
                                    </button>
                                </div>
                            </div>

                            <div v-else class="mb-4">
                                <p class="text-muted">Only the account owner can manage billing.</p>
                            </div>

                            <!-- Billing Portal Features -->
                            <div v-if="billing.subscribed && billing.can_manage" class="bg-light p-3 rounded">
                                <h6 class="mb-3">In the billing portal you can:</h6>
                                <ul class="mb-3 text-muted">
                                    <li>Update payment method</li>
                                    <li>View and download invoices</li>
                                    <li>Update billing address</li>
                                    <li>View payment history</li>
                                </ul>
                                <button class="btn btn-outline-secondary btn-sm" @click="openBillingPortal" :disabled="openingPortal">
                                    {{ openingPortal ? 'Opening...' : 'Open Billing Portal' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div v-show="activeTab === 'notifications'" class="card card-cml">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Notification Preferences</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">Choose when we email you about your account.</p>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="notify-failures" v-model="notifications.action_failures">
                                <label class="form-check-label" for="notify-failures">
                                    <strong>Action failures</strong>
                                    <div class="text-muted small">Email me when an action fails after all retry attempts</div>
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="notify-expired" v-model="notifications.reminder_expired">
                                <label class="form-check-label" for="notify-expired">
                                    <strong>Expired reminders</strong>
                                    <div class="text-muted small">Email me when a reminder expires without response</div>
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="notify-limits" v-model="notifications.usage_limits">
                                <label class="form-check-label" for="notify-limits">
                                    <strong>Usage limit warnings</strong>
                                    <div class="text-muted small">Email me when I'm approaching my plan limits</div>
                                </label>
                            </div>

                            <button class="btn btn-cml-primary mt-3" @click="saveNotifications" :disabled="savingNotifications">
                                {{ savingNotifications ? 'Saving...' : 'Save Preferences' }}
                            </button>
                            <span v-if="notificationsSaved" class="text-success ms-2">Saved!</span>
                        </div>
                    </div>

                    <!-- Admin Notifications (admins only) -->
                    <div v-show="activeTab === 'admin'" class="card card-cml">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Health Monitoring Alerts</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                Receive alerts when system health issues are detected. CallMeLater monitors itself and can notify you of problems.
                            </p>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="admin-health" v-model="adminNotifications.health_alerts">
                                <label class="form-check-label" for="admin-health">
                                    <strong>Health alerts</strong>
                                    <div class="text-muted small">Notify me when components are degraded (e.g., high failure rates, stuck jobs)</div>
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="admin-incidents" v-model="adminNotifications.incident_alerts">
                                <label class="form-check-label" for="admin-incidents">
                                    <strong>Incident alerts</strong>
                                    <div class="text-muted small">Notify me when incidents are created or resolved</div>
                                </label>
                            </div>

                            <button class="btn btn-cml-primary mt-3" @click="saveAdminNotifications" :disabled="savingAdminNotifications">
                                {{ savingAdminNotifications ? 'Saving...' : 'Save Preferences' }}
                            </button>
                            <span v-if="adminNotificationsSaved" class="text-success ms-2">Saved!</span>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div v-show="activeTab === 'danger'" class="card card-cml border-danger">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0 text-danger">Danger Zone</h5>
                        </div>
                        <div class="card-body">
                            <h6>Delete Account</h6>
                            <p class="text-muted">
                                Permanently delete your account and all associated data. This action cannot be undone.
                            </p>
                            <button class="btn btn-outline-danger" @click="showDeleteConfirm = true">
                                Delete My Account
                            </button>

                            <!-- Delete confirmation modal -->
                            <div v-if="showDeleteConfirm" class="mt-4 p-3 bg-light rounded border border-danger">
                                <p class="mb-3"><strong>Are you absolutely sure?</strong></p>
                                <p class="text-muted small mb-3">
                                    This will permanently delete your account, all actions, API keys, and data.
                                    Type <code>{{ user?.email }}</code> to confirm.
                                </p>
                                <input
                                    type="text"
                                    class="form-control mb-3"
                                    v-model="deleteConfirmEmail"
                                    placeholder="Type your email to confirm"
                                    style="max-width: 300px;"
                                >
                                <button
                                    class="btn btn-danger me-2"
                                    @click="deleteAccount"
                                    :disabled="deleteConfirmEmail !== user?.email || deletingAccount"
                                >
                                    {{ deletingAccount ? 'Deleting...' : 'Permanently Delete Account' }}
                                </button>
                                <button class="btn btn-outline-secondary" @click="showDeleteConfirm = false; deleteConfirmEmail = ''">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { formatDate } from '../utils/dateFormatting';
import ConfirmModal from '../components/ConfirmModal.vue';

export default {
    name: 'Settings',
    components: {
        ConfirmModal,
    },
    inject: ['toast'],
    data() {
        return {
            loading: true,
            activeTab: 'profile',
            tabs: [
                { id: 'profile', label: 'Profile' },
                { id: 'teams', label: 'Members', businessOnly: true },
                { id: 'api', label: 'API & Security' },
                { id: 'usage', label: 'Usage & Limits' },
                { id: 'billing', label: 'Billing' },
                { id: 'notifications', label: 'Notifications' },
                { id: 'admin', label: 'Admin Alerts', adminOnly: true },
                { id: 'danger', label: 'Danger Zone' },
            ],
            user: null,
            tokens: [],
            usage: {},
            outboundIp: null,
            webhookSecret: null,
            showWebhookSecret: false,

            // Profile
            profile: {
                timezone: '',
            },
            savingProfile: false,
            profileSaved: false,
            timezones: [
                'UTC',
                'America/New_York',
                'America/Chicago',
                'America/Denver',
                'America/Los_Angeles',
                'Europe/London',
                'Europe/Paris',
                'Europe/Berlin',
                'Asia/Tokyo',
                'Asia/Shanghai',
                'Australia/Sydney',
            ],

            // Password
            passwordForm: {
                current_password: '',
                password: '',
                password_confirmation: '',
            },
            changingPassword: false,
            passwordChanged: false,
            passwordError: null,

            // Tokens
            newTokenName: '',
            creatingToken: false,
            newlyCreatedToken: null,
            tokenCopied: false,
            ipCopied: false,

            // Notifications
            notifications: {
                action_failures: true,
                reminder_expired: true,
                usage_limits: true,
            },
            savingNotifications: false,
            notificationsSaved: false,

            // Billing
            billing: {
                subscribed: false,
                plan: 'free',
                canceled: false,
                ends_at: null,
                on_trial: false,
                can_manage: false,
                is_manually_managed: false,
                manual_plan_expires_at: null,
            },
            openingPortal: false,
            cancelingSubscription: false,
            resumingSubscription: false,

            // Delete account
            showDeleteConfirm: false,
            deleteConfirmEmail: '',
            deletingAccount: false,

            // Teams
            teams: [],
            loadingTeams: false,
            newTeamName: '',
            creatingTeam: false,
            editingTeam: null,
            addMemberEmail: '',
            addingMember: false,

            // Admin Notifications
            adminNotifications: {
                health_alerts: true,
                incident_alerts: true,
            },
            savingAdminNotifications: false,
            adminNotificationsSaved: false,

            // Confirm Modal
            confirmModal: {
                show: false,
                title: '',
                message: '',
                confirmText: 'Confirm',
                cancelText: 'Cancel',
                variant: 'warning',
                action: null,
                data: null,
            },
        };
    },
    computed: {
        visibleTabs() {
            return this.tabs.filter(tab => {
                if (tab.businessOnly) {
                    return this.usage.plan === 'business';
                }
                if (tab.adminOnly) {
                    return this.user?.is_admin === true;
                }
                return true;
            });
        },
    },
    mounted() {
        // Check for tab query parameter (e.g., from subscription result page)
        const tab = this.$route.query.tab;
        if (tab && this.tabs.some(t => t.id === tab)) {
            this.activeTab = tab;
        }
        this.loadSettings();
    },
    watch: {
        'usage.plan'(newPlan) {
            // Load teams when user has Business plan
            if (newPlan === 'business') {
                this.loadTeams();
            }
        },
    },
    methods: {
        formatDate,
        showConfirm({ title, message, confirmText = 'Confirm', cancelText = 'Cancel', variant = 'warning', action, data = null }) {
            this.confirmModal = {
                show: true,
                title,
                message,
                confirmText,
                cancelText,
                variant,
                action,
                data,
            };
        },
        handleConfirm() {
            const { action, data } = this.confirmModal;
            this.confirmModal.show = false;
            if (action && typeof this[action] === 'function') {
                this[action](data);
            }
        },
        formatHistoryDays(days) {
            if (!days) return '7 days';
            if (days >= 365) return days === 365 ? '1 year' : `${Math.floor(days / 365)} years`;
            if (days >= 30) return days === 30 ? '30 days' : `${days} days`;
            return `${days} days`;
        },
        async loadSettings() {
            this.loading = true;
            try {
                const [userRes, tokensRes, serverRes, webhookRes] = await Promise.all([
                    axios.get('/api/user'),
                    axios.get('/api/tokens'),
                    axios.get('/api/public/server-info'),
                    axios.get('/api/user/webhook-secret'),
                ]);

                this.user = userRes.data;
                this.tokens = tokensRes.data.tokens || [];
                this.outboundIp = serverRes.data.outbound_ip;
                this.webhookSecret = webhookRes.data.secret;

                // Set profile defaults
                this.profile.timezone = this.user.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;

                // Set notification defaults from user preferences
                if (this.user.notification_preferences) {
                    this.notifications = {
                        action_failures: this.user.notification_preferences.action_failures ?? true,
                        reminder_expired: this.user.notification_preferences.reminder_expired ?? true,
                        usage_limits: this.user.notification_preferences.usage_limits ?? true,
                    };
                }

                // Load usage stats
                await this.loadUsage();

                // Load admin notifications for admin users
                if (this.user.is_admin) {
                    await this.loadAdminNotifications();
                }
            } catch (err) {
                console.error('Failed to load settings:', err);
            } finally {
                this.loading = false;
            }
        },
        async loadUsage() {
            try {
                const response = await axios.get('/api/subscription/status');
                const data = response.data;
                this.usage = {
                    plan: data.plan,
                    billing_period: data.billing_period,
                    actions_created: data.usage?.actions_this_month || 0,
                    executions: data.usage?.executions_this_month || 0,
                    reminders_sent: data.usage?.reminders_this_month || 0,
                    limits: data.limits,
                };
                // Also populate billing info
                this.billing = {
                    subscribed: data.subscribed,
                    plan: data.plan,
                    canceled: data.canceled,
                    ends_at: data.ends_at,
                    on_trial: data.on_trial,
                    can_manage: data.can_manage_billing,
                    is_manually_managed: data.is_manually_managed,
                    manual_plan_expires_at: data.manual_plan_expires_at,
                };
            } catch (err) {
                console.error('Failed to load usage:', err);
            }
        },
        async saveProfile() {
            this.savingProfile = true;
            this.profileSaved = false;
            try {
                await axios.put('/api/user/profile', {
                    timezone: this.profile.timezone,
                });
                this.profileSaved = true;
                setTimeout(() => { this.profileSaved = false; }, 3000);
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to save profile');
            } finally {
                this.savingProfile = false;
            }
        },
        async changePassword() {
            this.changingPassword = true;
            this.passwordChanged = false;
            this.passwordError = null;
            try {
                await axios.put('/api/user/password', this.passwordForm);
                this.passwordChanged = true;
                this.passwordForm = { current_password: '', password: '', password_confirmation: '' };
                setTimeout(() => { this.passwordChanged = false; }, 3000);
            } catch (err) {
                this.passwordError = err.response?.data?.message || 'Failed to change password';
            } finally {
                this.changingPassword = false;
            }
        },
        async createToken() {
            this.creatingToken = true;
            this.newlyCreatedToken = null;
            try {
                const response = await axios.post('/api/tokens', {
                    name: this.newTokenName,
                });
                this.newlyCreatedToken = response.data.token;
                this.tokens.unshift({
                    id: response.data.id,
                    name: response.data.name,
                    abilities: response.data.abilities,
                    created_at: new Date().toISOString(),
                    last_used_at: null,
                });
                this.newTokenName = '';
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to create token');
            } finally {
                this.creatingToken = false;
            }
        },
        confirmRevokeToken(token) {
            this.showConfirm({
                title: 'Revoke API Key',
                message: `Revoke "${token.name}"? Any applications using this key will stop working.`,
                confirmText: 'Revoke Key',
                variant: 'danger',
                action: 'doRevokeToken',
                data: token,
            });
        },
        async doRevokeToken(token) {
            try {
                await axios.delete(`/api/tokens/${token.id}`);
                this.tokens = this.tokens.filter(t => t.id !== token.id);
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to revoke token');
            }
        },
        copyToken() {
            navigator.clipboard.writeText(this.newlyCreatedToken);
            this.tokenCopied = true;
            setTimeout(() => { this.tokenCopied = false; }, 2000);
        },
        copyIp() {
            navigator.clipboard.writeText(this.outboundIp);
            this.ipCopied = true;
            setTimeout(() => { this.ipCopied = false; }, 2000);
        },
        confirmRegenerateWebhookSecret() {
            this.showConfirm({
                title: 'Regenerate Webhook Secret',
                message: 'Existing webhooks using the old secret will fail verification. Are you sure?',
                confirmText: 'Regenerate',
                variant: 'warning',
                action: 'doRegenerateWebhookSecret',
            });
        },
        async doRegenerateWebhookSecret() {
            try {
                const response = await axios.post('/api/user/webhook-secret');
                this.webhookSecret = response.data.secret;
                this.showWebhookSecret = true;
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to regenerate secret');
            }
        },
        async saveNotifications() {
            this.savingNotifications = true;
            this.notificationsSaved = false;
            try {
                await axios.put('/api/user/notifications', this.notifications);
                this.notificationsSaved = true;
                setTimeout(() => { this.notificationsSaved = false; }, 3000);
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to save preferences');
            } finally {
                this.savingNotifications = false;
            }
        },
        async openBillingPortal() {
            this.openingPortal = true;
            try {
                const response = await axios.post('/api/subscription/portal');
                if (response.data.portal_url) {
                    window.location.href = response.data.portal_url;
                }
            } catch (err) {
                this.toast.error(err.response?.data?.error || 'Failed to open billing portal');
            } finally {
                this.openingPortal = false;
            }
        },
        confirmCancelSubscription() {
            this.showConfirm({
                title: 'Cancel Subscription',
                message: 'You\'ll keep access until the end of your billing period. Are you sure you want to cancel?',
                confirmText: 'Yes, Cancel',
                variant: 'danger',
                action: 'doCancelSubscription',
            });
        },
        async doCancelSubscription() {
            this.cancelingSubscription = true;
            try {
                const response = await axios.post('/api/subscription/cancel');
                this.$router.push({
                    name: 'subscription-result',
                    query: {
                        status: 'canceled',
                        ends_at: this.formatDate(response.data.ends_at)
                    }
                });
            } catch (err) {
                this.toast.error(err.response?.data?.error || 'Failed to cancel subscription');
            } finally {
                this.cancelingSubscription = false;
            }
        },
        async resumeSubscription() {
            this.resumingSubscription = true;
            try {
                await axios.post('/api/subscription/resume');
                this.$router.push({
                    name: 'subscription-result',
                    query: { status: 'resumed' }
                });
            } catch (err) {
                this.toast.error(err.response?.data?.error || 'Failed to resume subscription');
            } finally {
                this.resumingSubscription = false;
            }
        },
        async saveAdminNotifications() {
            this.savingAdminNotifications = true;
            this.adminNotificationsSaved = false;
            try {
                await axios.put('/api/user/admin-notifications', this.adminNotifications);
                this.adminNotificationsSaved = true;
                setTimeout(() => { this.adminNotificationsSaved = false; }, 3000);
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to save admin preferences');
            } finally {
                this.savingAdminNotifications = false;
            }
        },
        async loadAdminNotifications() {
            try {
                const response = await axios.get('/api/user/admin-notifications');
                this.adminNotifications = {
                    health_alerts: response.data.health_alerts ?? true,
                    incident_alerts: response.data.incident_alerts ?? true,
                };
            } catch (err) {
                console.error('Failed to load admin notifications:', err);
            }
        },
        async deleteAccount() {
            this.deletingAccount = true;
            try {
                await axios.delete('/api/user');
                localStorage.removeItem('token');
                this.$router.push('/');
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to delete account');
            } finally {
                this.deletingAccount = false;
            }
        },

        // Team methods
        async loadTeams() {
            this.loadingTeams = true;
            try {
                const response = await axios.get('/api/teams');
                this.teams = response.data.data.map(team => ({ ...team, _addEmail: '' }));
            } catch (err) {
                console.error('Failed to load teams:', err);
            } finally {
                this.loadingTeams = false;
            }
        },
        async createTeam() {
            this.creatingTeam = true;
            try {
                const response = await axios.post('/api/teams', {
                    name: this.newTeamName,
                });
                this.teams.push({ ...response.data.data, _addEmail: '' });
                this.toast.success(`Workspace "${this.newTeamName}" created.`);
                this.newTeamName = '';
            } catch (err) {
                this.toast.error(err.response?.data?.error || 'Failed to create workspace');
            } finally {
                this.creatingTeam = false;
            }
        },
        confirmDeleteTeam(team) {
            this.showConfirm({
                title: 'Delete Workspace',
                message: `Delete "${team.name}"? All members will lose access to shared actions.`,
                confirmText: 'Delete Workspace',
                variant: 'danger',
                action: 'doDeleteTeam',
                data: team,
            });
        },
        async doDeleteTeam(team) {
            try {
                await axios.delete(`/api/teams/${team.id}`);
                this.teams = this.teams.filter(t => t.id !== team.id);
                this.toast.success('Workspace deleted.');
            } catch (err) {
                this.toast.error(err.response?.data?.error || 'Failed to delete workspace');
            }
        },
        async addMember(team) {
            this.addingMember = true;
            try {
                const response = await axios.post(`/api/teams/${team.id}/members`, {
                    email: team._addEmail,
                });
                // Update the team in our list
                const idx = this.teams.findIndex(t => t.id === team.id);
                if (idx !== -1) {
                    this.teams[idx] = { ...response.data.team, _addEmail: '' };
                }
                // Show success message
                if (response.data.invitation_sent) {
                    this.toast.success(response.data.message);
                } else {
                    this.toast.success('Member added to workspace.');
                }
            } catch (err) {
                this.toast.error(err.response?.data?.error || 'Failed to invite member');
            } finally {
                this.addingMember = false;
            }
        },
        confirmRemoveMember(team, member) {
            this.showConfirm({
                title: 'Remove Member',
                message: `Remove ${member.name} from ${team.name}?`,
                confirmText: 'Remove',
                variant: 'warning',
                action: 'doRemoveMember',
                data: { team, member },
            });
        },
        async doRemoveMember({ team, member }) {
            try {
                await axios.delete(`/api/teams/${team.id}/members/${member.id}`);
                // Remove member from local state
                const teamIdx = this.teams.findIndex(t => t.id === team.id);
                if (teamIdx !== -1) {
                    this.teams[teamIdx].members = this.teams[teamIdx].members.filter(m => m.id !== member.id);
                    this.teams[teamIdx].member_count--;
                }
                this.toast.success('Member removed.');
            } catch (err) {
                this.toast.error(err.response?.data?.error || 'Failed to remove member');
            }
        },
    },
};
</script>

<style scoped>
.list-group-item.active {
    background-color: #111827;
    border-color: #111827;
}
</style>
