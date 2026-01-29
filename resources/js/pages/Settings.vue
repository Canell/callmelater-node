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
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label text-muted">First Name</label>
                                    <input type="text" class="form-control" v-model="profile.first_name" placeholder="John">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted">Last Name</label>
                                    <input type="text" class="form-control" v-model="profile.last_name" placeholder="Doe">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-muted">Email</label>
                                <div class="d-flex align-items-center">
                                    <strong>{{ user?.email }}</strong>
                                    <span v-if="user?.email_verified_at" class="badge bg-success ms-2">Verified</span>
                                    <span v-else class="badge bg-warning ms-2">Unverified</span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-muted">Phone</label>
                                <input type="tel" class="form-control" v-model="profile.phone" placeholder="+1 555 123 4567" style="max-width: 300px;">
                                <small class="text-muted">Used when selecting yourself as a reminder recipient</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-muted">Timezone</label>
                                <select class="form-select" v-model="profile.timezone" style="max-width: 300px;">
                                    <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                </select>
                                <small class="text-muted">Used to display dates and as the default when scheduling actions</small>
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

                    <!-- Contacts -->
                    <div v-show="activeTab === 'contacts'" class="card card-cml">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Contacts</h5>
                            <button class="btn btn-cml-primary btn-sm" @click="openContactModal()">
                                Add Contact
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                Manage contacts that can receive reminders. When creating gated actions, you can select contacts by name instead of typing email addresses.
                            </p>

                            <!-- Search -->
                            <div class="mb-4" style="max-width: 300px;">
                                <input
                                    type="text"
                                    class="form-control"
                                    v-model="contactsSearch"
                                    placeholder="Search contacts..."
                                    @input="searchContacts"
                                >
                            </div>

                            <!-- Loading -->
                            <div v-if="loadingContacts" class="text-center py-4">
                                <div class="spinner-border spinner-border-sm text-muted" role="status"></div>
                            </div>

                            <!-- Empty state -->
                            <div v-else-if="contacts.length === 0" class="text-center py-4 text-muted">
                                <p v-if="contactsSearch">No contacts found matching "{{ contactsSearch }}"</p>
                                <p v-else>No contacts yet. Add your first contact above.</p>
                            </div>

                            <!-- Contacts table -->
                            <div v-else class="table-responsive">
                                <table class="table table-cml">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="contact in contacts" :key="contact.id">
                                            <td><strong>{{ contact.full_name }}</strong></td>
                                            <td>{{ contact.email || '-' }}</td>
                                            <td>{{ contact.phone || '-' }}</td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-secondary me-1" @click="openContactModal(contact)">
                                                    Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" @click="confirmDeleteContact(contact)">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Modal -->
                    <div v-if="showContactModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">{{ editingContact ? 'Edit Contact' : 'Add Contact' }}</h5>
                                    <button type="button" class="btn-close" @click="closeContactModal"></button>
                                </div>
                                <div class="modal-body">
                                    <div v-if="contactFormError" class="alert alert-danger">{{ contactFormError }}</div>

                                    <div class="mb-3">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" class="form-control" v-model="contactForm.first_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" v-model="contactForm.last_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" v-model="contactForm.email" placeholder="email@example.com">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control" v-model="contactForm.phone" placeholder="+15551234567">
                                        <small class="text-muted">E.164 format required (e.g., +15551234567)</small>
                                    </div>
                                    <div class="text-muted small">* At least one contact method (email or phone) is required.</div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" @click="closeContactModal">Cancel</button>
                                    <button type="button" class="btn btn-cml-primary" @click="saveContact" :disabled="savingContact">
                                        {{ savingContact ? 'Saving...' : (editingContact ? 'Update' : 'Add Contact') }}
                                    </button>
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

                    <!-- Domains -->
                    <div v-show="activeTab === 'domains'" class="card card-cml">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Domain Verification</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                Verify ownership of domains you send webhooks to. Verification is required after
                                <strong>{{ domainThresholds.daily }} actions/day</strong> or
                                <strong>{{ domainThresholds.monthly }} actions/month</strong> to the same domain.
                            </p>

                            <!-- Add domain form -->
                            <div class="bg-light p-3 rounded mb-4">
                                <h6 class="mb-3">Add Domain for Verification</h6>
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label">Domain</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            v-model="newDomain"
                                            placeholder="api.example.com"
                                            @keyup.enter="addDomain"
                                        >
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-cml-primary" @click="addDomain" :disabled="!newDomain || addingDomain">
                                            {{ addingDomain ? 'Adding...' : 'Add Domain' }}
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    Add domains proactively to verify them before reaching thresholds.
                                </small>
                            </div>

                            <!-- Loading -->
                            <div v-if="loadingDomains" class="text-center py-4">
                                <div class="spinner-border spinner-border-sm text-muted" role="status"></div>
                            </div>

                            <!-- Empty state -->
                            <div v-else-if="domains.length === 0" class="text-center py-4 text-muted">
                                <p>No domains tracked yet. Domains are automatically added when you create actions targeting them.</p>
                            </div>

                            <!-- Domains list -->
                            <div v-else>
                                <div v-for="domain in domains" :key="domain.id" class="card mb-3" :class="getDomainCardClass(domain)">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <strong>{{ domain.domain }}</strong>
                                                    <span v-if="domain.verified" class="badge bg-success ms-2">Verified</span>
                                                    <span v-else class="badge bg-warning text-dark ms-2">Unverified</span>
                                                </h6>
                                                <div class="small text-muted">
                                                    <span v-if="domain.verified && domain.method">
                                                        Verified via {{ domain.method === 'dns' ? 'DNS' : 'File' }}
                                                    </span>
                                                    <span v-if="domain.verified_at">
                                                        on {{ formatDate(domain.verified_at) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button
                                                    v-if="!domain.verified"
                                                    class="btn btn-sm btn-cml-primary"
                                                    @click="verifyDomain(domain)"
                                                    :disabled="verifyingDomain === domain.domain"
                                                >
                                                    {{ verifyingDomain === domain.domain ? 'Verifying...' : 'Verify Now' }}
                                                </button>
                                                <button
                                                    class="btn btn-sm btn-outline-secondary"
                                                    @click="toggleDomainInstructions(domain)"
                                                >
                                                    {{ showDomainInstructions === domain.domain ? 'Hide' : 'Instructions' }}
                                                </button>
                                                <button
                                                    class="btn btn-sm btn-outline-danger"
                                                    @click="confirmDeleteDomain(domain)"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Usage stats with warnings -->
                                        <div class="mt-3">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <small class="text-muted me-2">Daily:</small>
                                                        <div class="progress flex-grow-1" style="height: 8px;">
                                                            <div
                                                                class="progress-bar"
                                                                :class="getUsageBarClass(domain.usage.daily, domainThresholds.daily)"
                                                                :style="{ width: getUsagePercent(domain.usage.daily, domainThresholds.daily) + '%' }"
                                                            ></div>
                                                        </div>
                                                        <small class="ms-2" :class="getUsageTextClass(domain.usage.daily, domainThresholds.daily)">
                                                            {{ domain.usage.daily }}/{{ domainThresholds.daily }}
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <small class="text-muted me-2">Monthly:</small>
                                                        <div class="progress flex-grow-1" style="height: 8px;">
                                                            <div
                                                                class="progress-bar"
                                                                :class="getUsageBarClass(domain.usage.monthly, domainThresholds.monthly)"
                                                                :style="{ width: getUsagePercent(domain.usage.monthly, domainThresholds.monthly) + '%' }"
                                                            ></div>
                                                        </div>
                                                        <small class="ms-2" :class="getUsageTextClass(domain.usage.monthly, domainThresholds.monthly)">
                                                            {{ domain.usage.monthly }}/{{ domainThresholds.monthly }}
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Warning alert -->
                                            <div v-if="shouldShowUsageWarning(domain) && !domain.verified" class="alert alert-warning mt-3 mb-0 py-2">
                                                <strong>Approaching limit!</strong>
                                                You're close to the verification threshold. Please verify this domain to continue sending actions.
                                            </div>
                                            <div v-else-if="isOverThreshold(domain) && !domain.verified" class="alert alert-danger mt-3 mb-0 py-2">
                                                <strong>Verification required!</strong>
                                                This domain has exceeded the threshold. New actions will be blocked until verified.
                                            </div>
                                        </div>

                                        <!-- Verification instructions (expandable) -->
                                        <div v-if="showDomainInstructions === domain.domain" class="mt-3 p-3 bg-light rounded">
                                            <h6 class="mb-3">Verification Methods</h6>
                                            <p class="small text-muted mb-3">Choose one of the following methods to verify domain ownership:</p>

                                            <!-- DNS Method -->
                                            <div class="mb-3">
                                                <strong class="d-block mb-2">Option 1: DNS TXT Record (Recommended)</strong>
                                                <p class="small text-muted mb-2">Add this TXT record to your domain's DNS settings:</p>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control font-monospace" :value="`callmelater-verification=${domain.verification_token}`" readonly>
                                                    <button class="btn btn-outline-secondary" @click="copyToClipboard(`callmelater-verification=${domain.verification_token}`)">
                                                        Copy
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- File Method -->
                                            <div>
                                                <strong class="d-block mb-2">Option 2: Verification File</strong>
                                                <p class="small text-muted mb-2">
                                                    Create a file at <code>https://{{ domain.domain }}/.well-known/callmelater.txt</code> with this content:
                                                </p>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control font-monospace" :value="`callmelater-verification=${domain.verification_token}`" readonly>
                                                    <button class="btn btn-outline-secondary" @click="copyToClipboard(`callmelater-verification=${domain.verification_token}`)">
                                                        Copy
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Integrations -->
                    <div v-show="activeTab === 'integrations'" class="card card-cml">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Chat Integrations</h5>
                            <button
                                v-if="canCreateIntegration"
                                class="btn btn-cml-primary btn-sm"
                                @click="openIntegrationModal()"
                            >
                                Add Integration
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                Connect Microsoft Teams or Slack to receive gated action reminders directly in your chat channels.
                                Recipients can respond with buttons right from the chat message.
                            </p>

                            <!-- Loading -->
                            <div v-if="loadingIntegrations" class="text-center py-4">
                                <div class="spinner-border spinner-border-sm text-muted" role="status"></div>
                            </div>

                            <!-- Empty state -->
                            <div v-else-if="integrations.length === 0" class="text-center py-4">
                                <div class="text-muted mb-3">
                                    <i class="bi bi-chat-dots fs-1"></i>
                                </div>
                                <p class="text-muted mb-3">No integrations configured yet.</p>
                                <button
                                    v-if="canCreateIntegration"
                                    class="btn btn-cml-primary"
                                    @click="openIntegrationModal()"
                                >
                                    Add Your First Integration
                                </button>
                            </div>

                            <!-- Integrations list -->
                            <div v-else class="row g-3">
                                <div v-for="integration in integrations" :key="integration.id" class="col-md-6">
                                    <div class="card" :class="{ 'border-success': integration.is_active, 'border-secondary': !integration.is_active }">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <i :class="getProviderIcon(integration.provider)" class="me-1"></i>
                                                        {{ integration.name }}
                                                    </h6>
                                                    <small class="text-muted">
                                                        {{ getProviderLabel(integration.provider) }}
                                                        <span v-if="integration.slack_channel_name"> · #{{ integration.slack_channel_name }}</span>
                                                    </small>
                                                </div>
                                                <span :class="integration.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                                    {{ integration.is_active ? 'Active' : 'Disabled' }}
                                                </span>
                                            </div>
                                            <div class="d-flex gap-2 mt-3">
                                                <button
                                                    class="btn btn-sm btn-outline-secondary"
                                                    @click="openIntegrationModal(integration)"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    class="btn btn-sm btn-outline-primary"
                                                    @click="testIntegration(integration)"
                                                    :disabled="testingIntegration === integration.id || !integration.is_active"
                                                >
                                                    {{ testingIntegration === integration.id ? 'Testing...' : 'Test' }}
                                                </button>
                                                <button
                                                    class="btn btn-sm"
                                                    :class="integration.is_active ? 'btn-outline-secondary' : 'btn-outline-success'"
                                                    @click="toggleIntegration(integration)"
                                                >
                                                    {{ integration.is_active ? 'Disable' : 'Enable' }}
                                                </button>
                                                <button
                                                    class="btn btn-sm btn-outline-danger"
                                                    @click="confirmDeleteIntegration(integration)"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Integration Modal -->
                    <div v-if="showIntegrationModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">{{ editingIntegration ? 'Edit' : 'Add' }} Chat Integration</h5>
                                    <button type="button" class="btn-close" @click="closeIntegrationModal"></button>
                                </div>
                                <div class="modal-body">
                                    <div v-if="integrationFormError" class="alert alert-danger">{{ integrationFormError }}</div>

                                    <div class="mb-3">
                                        <label class="form-label">Provider</label>
                                        <select class="form-select" v-model="integrationForm.provider" @change="onProviderChange" :disabled="!!editingIntegration">
                                            <option value="teams">Microsoft Teams</option>
                                            <option value="slack">Slack</option>
                                        </select>
                                        <div v-if="editingIntegration" class="form-text">Provider cannot be changed after creation.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Name *</label>
                                        <input type="text" class="form-control" v-model="integrationForm.name" placeholder="e.g., DevOps Team Channel">
                                        <small class="text-muted">A friendly name to identify this connection</small>
                                    </div>

                                    <!-- Teams configuration -->
                                    <div v-if="integrationForm.provider === 'teams'">
                                        <div class="mb-3">
                                            <label class="form-label">Webhook URL {{ editingIntegration ? '' : '*' }}</label>
                                            <input
                                                type="url"
                                                class="form-control"
                                                v-model="integrationForm.teams_webhook_url"
                                                :placeholder="editingIntegration ? 'Leave blank to keep current URL' : 'Paste your Teams webhook URL here'"
                                            >
                                            <small class="text-muted">
                                                {{ editingIntegration ? 'Only enter a new URL if you want to change it' : 'See instructions below for how to create a webhook in Teams' }}
                                            </small>
                                        </div>

                                        <div class="alert alert-info small">
                                            <strong>How to get a webhook URL:</strong>
                                            <p class="mb-2 mt-2"><strong>Option 1: Workflows (Recommended)</strong></p>
                                            <ol class="mb-2 ps-3">
                                                <li>Right-click the channel &gt; <strong>Workflows</strong></li>
                                                <li>Search for "Post to a channel when a webhook request is received"</li>
                                                <li>Name your workflow, select the channel, and click <strong>Add workflow</strong></li>
                                                <li>Copy the webhook URL</li>
                                            </ol>
                                            <p class="mb-2"><strong>Option 2: Connectors (Legacy)</strong></p>
                                            <ol class="mb-0 ps-3">
                                                <li>Right-click the channel &gt; <strong>Manage channel</strong> &gt; <strong>Connectors</strong></li>
                                                <li>Find "Incoming Webhook" and click <strong>Configure</strong></li>
                                                <li>Name your webhook and click <strong>Create</strong></li>
                                                <li>Copy the webhook URL</li>
                                            </ol>
                                        </div>
                                    </div>

                                    <!-- Slack configuration -->
                                    <div v-if="integrationForm.provider === 'slack'">
                                        <div class="mb-3">
                                            <label class="form-label">Bot Token {{ editingIntegration ? '' : '*' }}</label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                v-model="integrationForm.slack_bot_token"
                                                :placeholder="editingIntegration ? 'Leave blank to keep current token' : 'xoxb-...'"
                                                @blur="fetchSlackChannels"
                                            >
                                            <small class="text-muted">{{ editingIntegration ? 'Only enter a new token if you want to change it' : 'Your Slack app\'s Bot User OAuth Token' }}</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Channel {{ editingIntegration ? '' : '*' }}</label>
                                            <div v-if="editingIntegration && editingIntegration.slack_channel_name && slackChannels.length === 0" class="mb-2">
                                                <span class="badge bg-secondary">Current: #{{ editingIntegration.slack_channel_name }}</span>
                                            </div>
                                            <div v-if="loadingSlackChannels" class="text-muted small">
                                                <span class="spinner-border spinner-border-sm me-1"></span> Loading channels...
                                            </div>
                                            <select
                                                v-else
                                                class="form-select"
                                                v-model="integrationForm.slack_channel_id"
                                                :disabled="slackChannels.length === 0 && !editingIntegration"
                                            >
                                                <option value="">{{ slackChannels.length === 0 ? (editingIntegration ? 'Keep current channel' : 'Enter bot token first') : 'Select a channel' }}</option>
                                                <option v-for="ch in slackChannels" :key="ch.id" :value="ch.id">
                                                    {{ ch.is_private ? '🔒 ' : '#' }}{{ ch.name }}
                                                </option>
                                            </select>
                                            <small class="text-muted">{{ editingIntegration ? 'Select a new channel to change it, or leave empty to keep current' : 'The channel where reminders will be posted' }}</small>
                                        </div>

                                        <div class="alert alert-info small">
                                            <strong>How to create a Slack App:</strong>
                                            <ol class="mb-0 ps-3 mt-2">
                                                <li>Go to <a href="https://api.slack.com/apps" target="_blank">api.slack.com/apps</a> and click <strong>Create New App</strong></li>
                                                <li>Choose "From scratch", name it (e.g., "CallMeLater"), and select your workspace</li>
                                                <li>Go to <strong>OAuth & Permissions</strong> and add these Bot Token Scopes:
                                                    <ul class="mb-1 ps-3">
                                                        <li><code>chat:write</code> - Send messages</li>
                                                        <li><code>channels:read</code> - List public channels</li>
                                                        <li><code>groups:read</code> - List private channels (optional)</li>
                                                    </ul>
                                                </li>
                                                <li>Click <strong>Install to Workspace</strong> and authorize</li>
                                                <li>Copy the <strong>Bot User OAuth Token</strong> (starts with xoxb-)</li>
                                                <li><strong>Important:</strong> Invite the bot to your channel: <code>/invite @YourBotName</code></li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" @click="closeIntegrationModal">Cancel</button>
                                    <button
                                        type="button"
                                        class="btn btn-cml-primary"
                                        @click="saveIntegration"
                                        :disabled="savingIntegration || !integrationForm.name || !isIntegrationFormValid"
                                    >
                                        {{ savingIntegration ? 'Adding...' : 'Add Integration' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Branding (Business only) -->
                    <div v-show="activeTab === 'branding'" class="card card-cml">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Email Branding</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                Customize reminder emails with your company's branding. Your logo and colors will appear in emails sent to recipients.
                            </p>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label class="form-label">Logo URL</label>
                                        <input
                                            type="url"
                                            class="form-control"
                                            v-model="branding.logo_url"
                                            placeholder="https://example.com/logo.png"
                                        >
                                        <small class="text-muted">
                                            Use a publicly accessible URL. Recommended size: 200x60px, PNG or SVG format.
                                        </small>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label">Brand Color</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <input
                                                type="color"
                                                class="form-control form-control-color"
                                                v-model="branding.brand_color"
                                                title="Choose your brand color"
                                            >
                                            <input
                                                type="text"
                                                class="form-control"
                                                v-model="branding.brand_color"
                                                placeholder="#22c55e"
                                                style="max-width: 120px;"
                                            >
                                        </div>
                                        <small class="text-muted">
                                            Used for the "Confirm" button in emails. Default: green (#22c55e).
                                        </small>
                                    </div>

                                    <button
                                        class="btn btn-cml-primary"
                                        @click="saveBranding"
                                        :disabled="savingBranding"
                                    >
                                        {{ savingBranding ? 'Saving...' : 'Save Branding' }}
                                    </button>
                                </div>

                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="mb-3">Preview</h6>
                                            <div class="bg-white p-3 rounded border">
                                                <div class="text-center mb-3 pb-3 border-bottom">
                                                    <img
                                                        v-if="branding.logo_url"
                                                        :src="branding.logo_url"
                                                        alt="Logo preview"
                                                        style="max-height: 50px; max-width: 150px;"
                                                        @error="handleLogoError"
                                                    >
                                                    <div v-else class="text-muted small">
                                                        <i class="bi bi-image fs-2 d-block"></i>
                                                        No logo set
                                                    </div>
                                                </div>
                                                <div class="text-center">
                                                    <span
                                                        class="d-inline-block px-3 py-2 rounded text-white small"
                                                        :style="{ backgroundColor: branding.brand_color || '#22c55e' }"
                                                    >
                                                        Confirm Button
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
                                            <a href="/pricing" class="ms-2 small">Upgrade</a>
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
                                    If you have questions about your plan, please <a href="/contact">contact us</a>.
                                </p>
                            </div>

                            <!-- Actions based on subscription state -->
                            <div v-if="billing.plan === 'free'" class="mb-4">
                                <p class="text-muted">Upgrade to Pro or Business for more actions, retries, and features.</p>
                                <a href="/pricing" class="btn btn-cml-primary">
                                    View Plans & Upgrade
                                </a>
                            </div>

                            <div v-else-if="billing.can_manage" class="mb-4">
                                <!-- Manage subscription -->
                                <p class="text-muted mb-3">
                                    Manage your subscription, update payment methods, and view invoices in the billing portal.
                                </p>
                                <button class="btn btn-cml-primary me-2" @click="openBillingPortal" :disabled="openingPortal">
                                    {{ openingPortal ? 'Opening...' : 'Manage Subscription' }}
                                </button>
                                <a href="/pricing" class="btn btn-outline-secondary">
                                    Change Plan
                                </a>

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
                { id: 'contacts', label: 'Contacts' },
                { id: 'teams', label: 'Members', businessOnly: true },
                { id: 'branding', label: 'Branding', businessOnly: true },
                { id: 'api', label: 'API & Security' },
                { id: 'domains', label: 'Domains' },
                { id: 'integrations', label: 'Integrations', paidOnly: true },
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
                first_name: '',
                last_name: '',
                phone: '',
                timezone: '',
            },
            savingProfile: false,
            profileSaved: false,
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

            // Contacts (Team Members)
            contacts: [],
            loadingContacts: false,
            contactsSearch: '',
            showContactModal: false,
            editingContact: null,
            contactForm: {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
            },
            savingContact: false,
            contactFormError: null,

            // Admin Notifications
            adminNotifications: {
                health_alerts: true,
                incident_alerts: true,
            },
            savingAdminNotifications: false,
            adminNotificationsSaved: false,

            // Domains
            domains: [],
            domainThresholds: { daily: 10, monthly: 100 },
            loadingDomains: false,
            newDomain: '',
            addingDomain: false,
            verifyingDomain: null,
            showDomainInstructions: null,

            // Integrations
            integrations: [],
            loadingIntegrations: false,
            showIntegrationModal: false,
            editingIntegration: null,
            integrationForm: {
                provider: 'teams',
                name: '',
                teams_webhook_url: '',
                slack_bot_token: '',
                slack_signing_secret: '',
                slack_channel_id: '',
                slack_channel_name: '',
            },
            savingIntegration: false,
            integrationFormError: null,
            testingIntegration: null,
            canCreateIntegration: false,
            slackChannels: [],
            loadingSlackChannels: false,

            // Branding (Business only)
            branding: {
                logo_url: '',
                brand_color: '#22c55e',
            },
            savingBranding: false,

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
                if (tab.paidOnly) {
                    return this.usage.plan === 'pro' || this.usage.plan === 'business';
                }
                if (tab.adminOnly) {
                    return this.user?.is_admin === true;
                }
                return true;
            });
        },
        isIntegrationFormValid() {
            if (this.integrationForm.provider === 'teams') {
                return !!this.integrationForm.teams_webhook_url;
            }
            if (this.integrationForm.provider === 'slack') {
                return !!this.integrationForm.slack_bot_token && !!this.integrationForm.slack_channel_id;
            }
            return false;
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
                this.profile.first_name = this.user.first_name || '';
                this.profile.last_name = this.user.last_name || '';
                this.profile.phone = this.user.phone || '';
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

                // Load contacts
                await this.loadContacts();

                // Load domains
                await this.loadDomains();

                // Load integrations for paid plans
                if (this.usage.plan === 'pro' || this.usage.plan === 'business') {
                    await this.loadIntegrations();
                }

                // Load branding for business plan
                if (this.usage.plan === 'business') {
                    await this.loadBranding();
                }

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
                    first_name: this.profile.first_name || null,
                    last_name: this.profile.last_name || null,
                    phone: this.profile.phone || null,
                    timezone: this.profile.timezone,
                });
                this.profileSaved = true;
                // Update local user object and localStorage
                this.user.first_name = this.profile.first_name;
                this.user.last_name = this.profile.last_name;
                this.user.phone = this.profile.phone;
                this.user.timezone = this.profile.timezone;
                localStorage.setItem('userTimezone', this.profile.timezone);
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
                localStorage.removeItem('userTimezone');
                // Navigate to Blade home page (not a Vue route)
                window.location.href = '/';
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

        // Contact methods
        async loadContacts() {
            this.loadingContacts = true;
            try {
                const params = {};
                if (this.contactsSearch) {
                    params.search = this.contactsSearch;
                }
                const response = await axios.get('/api/v1/team-members', { params });
                this.contacts = response.data.data || [];
            } catch (err) {
                console.error('Failed to load contacts:', err);
            } finally {
                this.loadingContacts = false;
            }
        },
        searchContacts() {
            // Debounce search
            clearTimeout(this._searchTimeout);
            this._searchTimeout = setTimeout(() => {
                this.loadContacts();
            }, 300);
        },
        openContactModal(contact = null) {
            this.editingContact = contact;
            this.contactFormError = null;
            if (contact) {
                this.contactForm = {
                    first_name: contact.first_name,
                    last_name: contact.last_name,
                    email: contact.email || '',
                    phone: contact.phone || '',
                };
            } else {
                this.contactForm = {
                    first_name: '',
                    last_name: '',
                    email: '',
                    phone: '',
                };
            }
            this.showContactModal = true;
        },
        closeContactModal() {
            this.showContactModal = false;
            this.editingContact = null;
            this.contactFormError = null;
        },
        async saveContact() {
            this.savingContact = true;
            this.contactFormError = null;
            try {
                const data = {
                    first_name: this.contactForm.first_name,
                    last_name: this.contactForm.last_name,
                    email: this.contactForm.email || null,
                    phone: this.contactForm.phone || null,
                };

                if (this.editingContact) {
                    // Update
                    const response = await axios.put(`/api/v1/team-members/${this.editingContact.id}`, data);
                    const idx = this.contacts.findIndex(c => c.id === this.editingContact.id);
                    if (idx !== -1) {
                        this.contacts[idx] = response.data.data;
                    }
                    this.toast.success('Contact updated.');
                } else {
                    // Create
                    const response = await axios.post('/api/v1/team-members', data);
                    this.contacts.unshift(response.data.data);
                    this.toast.success('Contact added.');
                }
                this.closeContactModal();
            } catch (err) {
                const errorData = err.response?.data;
                if (errorData?.errors) {
                    // Get first error message
                    const firstError = Object.values(errorData.errors)[0];
                    this.contactFormError = Array.isArray(firstError) ? firstError[0] : firstError;
                } else {
                    this.contactFormError = errorData?.message || 'Failed to save contact';
                }
            } finally {
                this.savingContact = false;
            }
        },
        confirmDeleteContact(contact) {
            this.showConfirm({
                title: 'Delete Contact',
                message: `Delete "${contact.full_name}"? This cannot be undone.`,
                confirmText: 'Delete',
                variant: 'danger',
                action: 'doDeleteContact',
                data: contact,
            });
        },
        async doDeleteContact(contact) {
            try {
                await axios.delete(`/api/v1/team-members/${contact.id}`);
                this.contacts = this.contacts.filter(c => c.id !== contact.id);
                this.toast.success('Contact deleted.');
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to delete contact');
            }
        },

        // Domain methods
        async loadDomains() {
            this.loadingDomains = true;
            try {
                const response = await axios.get('/api/v1/domains');
                this.domains = response.data.data || [];
                if (response.data.thresholds) {
                    this.domainThresholds = response.data.thresholds;
                }
            } catch (err) {
                console.error('Failed to load domains:', err);
            } finally {
                this.loadingDomains = false;
            }
        },
        async addDomain() {
            if (!this.newDomain) return;
            this.addingDomain = true;
            try {
                // Getting verification instructions will create the domain record
                const response = await axios.get(`/api/v1/domains/${encodeURIComponent(this.newDomain)}`);
                // Reload domains to get full list with usage
                await this.loadDomains();
                this.newDomain = '';
                this.toast.success('Domain added. Follow the verification instructions.');
                // Show instructions for the new domain
                this.showDomainInstructions = response.data.domain;
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to add domain');
            } finally {
                this.addingDomain = false;
            }
        },
        async verifyDomain(domain) {
            this.verifyingDomain = domain.domain;
            try {
                const response = await axios.post(`/api/v1/domains/${encodeURIComponent(domain.domain)}/verify`);
                if (response.data.verified) {
                    this.toast.success('Domain verified successfully!');
                    await this.loadDomains();
                    this.showDomainInstructions = null;
                } else {
                    this.toast.error('Verification failed. Please check your DNS record or verification file.');
                }
            } catch (err) {
                const message = err.response?.data?.message || 'Verification failed';
                this.toast.error(message);
            } finally {
                this.verifyingDomain = null;
            }
        },
        toggleDomainInstructions(domain) {
            if (this.showDomainInstructions === domain.domain) {
                this.showDomainInstructions = null;
            } else {
                this.showDomainInstructions = domain.domain;
            }
        },
        confirmDeleteDomain(domain) {
            this.showConfirm({
                title: 'Remove Domain',
                message: `Remove "${domain.domain}" from verification tracking? This will not affect existing actions.`,
                confirmText: 'Remove',
                variant: 'danger',
                action: 'doDeleteDomain',
                data: domain,
            });
        },
        async doDeleteDomain(domain) {
            try {
                await axios.delete(`/api/v1/domains/${encodeURIComponent(domain.domain)}`);
                this.domains = this.domains.filter(d => d.id !== domain.id);
                this.toast.success('Domain removed.');
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to remove domain');
            }
        },
        getUsagePercent(current, max) {
            return Math.min((current / max) * 100, 100);
        },
        getUsageBarClass(current, max) {
            const percent = (current / max) * 100;
            if (percent >= 100) return 'bg-danger';
            if (percent >= 80) return 'bg-warning';
            return 'bg-success';
        },
        getUsageTextClass(current, max) {
            const percent = (current / max) * 100;
            if (percent >= 100) return 'text-danger fw-bold';
            if (percent >= 80) return 'text-warning fw-bold';
            return '';
        },
        getDomainCardClass(domain) {
            if (!domain.verified && this.isOverThreshold(domain)) {
                return 'border-danger';
            }
            if (!domain.verified && this.shouldShowUsageWarning(domain)) {
                return 'border-warning';
            }
            return '';
        },
        shouldShowUsageWarning(domain) {
            const dailyPercent = (domain.usage.daily / this.domainThresholds.daily) * 100;
            const monthlyPercent = (domain.usage.monthly / this.domainThresholds.monthly) * 100;
            return (dailyPercent >= 80 && dailyPercent < 100) || (monthlyPercent >= 80 && monthlyPercent < 100);
        },
        isOverThreshold(domain) {
            return domain.usage.daily >= this.domainThresholds.daily || domain.usage.monthly >= this.domainThresholds.monthly;
        },
        copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            this.toast.success('Copied to clipboard');
        },

        // Integration methods
        async loadIntegrations() {
            this.loadingIntegrations = true;
            try {
                const response = await axios.get('/api/v1/integrations');
                this.integrations = response.data.data || [];
                this.canCreateIntegration = response.data.can_create ?? false;
            } catch (err) {
                console.error('Failed to load integrations:', err);
            } finally {
                this.loadingIntegrations = false;
            }
        },
        openIntegrationModal(integration = null) {
            this.editingIntegration = integration;
            this.integrationFormError = null;
            this.slackChannels = [];
            if (integration) {
                this.integrationForm = {
                    provider: integration.provider,
                    name: integration.name,
                    teams_webhook_url: '',
                    slack_bot_token: '',
                    slack_signing_secret: '',
                    slack_channel_id: '',
                    slack_channel_name: '',
                };
            } else {
                this.integrationForm = {
                    provider: 'teams',
                    name: '',
                    teams_webhook_url: '',
                    slack_bot_token: '',
                    slack_signing_secret: '',
                    slack_channel_id: '',
                    slack_channel_name: '',
                };
            }
            this.showIntegrationModal = true;
        },
        closeIntegrationModal() {
            this.showIntegrationModal = false;
            this.editingIntegration = null;
            this.integrationFormError = null;
            this.slackChannels = [];
        },
        onProviderChange() {
            // Reset slack channels when switching providers
            this.slackChannels = [];
            this.integrationForm.slack_channel_id = '';
            this.integrationForm.slack_channel_name = '';
        },
        async fetchSlackChannels() {
            const token = this.integrationForm.slack_bot_token;
            if (!token || !token.startsWith('xoxb-')) {
                return;
            }

            this.loadingSlackChannels = true;
            this.slackChannels = [];
            try {
                const response = await axios.post('/api/v1/integrations/slack/channels', {
                    bot_token: token,
                });
                this.slackChannels = response.data.channels || [];
            } catch (err) {
                this.integrationFormError = err.response?.data?.message || 'Failed to fetch Slack channels. Check your bot token.';
            } finally {
                this.loadingSlackChannels = false;
            }
        },
        async saveIntegration() {
            this.savingIntegration = true;
            this.integrationFormError = null;
            try {
                const payload = {
                    name: this.integrationForm.name,
                };

                if (this.editingIntegration) {
                    // Update existing - only send changed fields
                    if (this.integrationForm.provider === 'teams' && this.integrationForm.teams_webhook_url) {
                        payload.teams_webhook_url = this.integrationForm.teams_webhook_url;
                    } else if (this.integrationForm.provider === 'slack') {
                        if (this.integrationForm.slack_bot_token) {
                            payload.slack_bot_token = this.integrationForm.slack_bot_token;
                        }
                        if (this.integrationForm.slack_channel_id) {
                            payload.slack_channel_id = this.integrationForm.slack_channel_id;
                            const selectedChannel = this.slackChannels.find(ch => ch.id === this.integrationForm.slack_channel_id);
                            if (selectedChannel) {
                                payload.slack_channel_name = selectedChannel.name;
                            }
                        }
                    }
                    await axios.put(`/api/v1/integrations/${this.editingIntegration.id}`, payload);
                    this.toast.success('Integration updated successfully.');
                } else {
                    // Create new
                    payload.provider = this.integrationForm.provider;
                    if (this.integrationForm.provider === 'teams') {
                        payload.teams_webhook_url = this.integrationForm.teams_webhook_url;
                    } else if (this.integrationForm.provider === 'slack') {
                        payload.slack_bot_token = this.integrationForm.slack_bot_token;
                        payload.slack_channel_id = this.integrationForm.slack_channel_id;
                        const selectedChannel = this.slackChannels.find(ch => ch.id === this.integrationForm.slack_channel_id);
                        if (selectedChannel) {
                            payload.slack_channel_name = selectedChannel.name;
                        }
                    }
                    await axios.post('/api/v1/integrations', payload);
                    this.toast.success('Integration added successfully.');
                }

                this.closeIntegrationModal();
                await this.loadIntegrations();
            } catch (err) {
                this.integrationFormError = err.response?.data?.message || 'Failed to save integration';
            } finally {
                this.savingIntegration = false;
            }
        },
        async testIntegration(integration) {
            this.testingIntegration = integration.id;
            try {
                const response = await axios.post(`/api/v1/integrations/${integration.id}/test`);
                if (response.data.success) {
                    this.toast.success('Test message sent! Check your channel.');
                } else {
                    this.toast.error(response.data.message || 'Test failed');
                }
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to test integration');
            } finally {
                this.testingIntegration = null;
            }
        },
        async toggleIntegration(integration) {
            try {
                const response = await axios.post(`/api/v1/integrations/${integration.id}/toggle`);
                integration.is_active = response.data.is_active;
                this.toast.success(response.data.message);
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to toggle integration');
            }
        },
        confirmDeleteIntegration(integration) {
            this.showConfirm({
                title: 'Delete Integration',
                message: `Delete "${integration.name}"? This will remove the connection and any pending reminders using this integration may fail.`,
                confirmText: 'Delete',
                variant: 'danger',
                action: 'doDeleteIntegration',
                data: integration,
            });
        },
        async doDeleteIntegration(integration) {
            try {
                await axios.delete(`/api/v1/integrations/${integration.id}`);
                this.integrations = this.integrations.filter(i => i.id !== integration.id);
                this.toast.success('Integration deleted.');
            } catch (err) {
                this.toast.error(err.response?.data?.message || 'Failed to delete integration');
            }
        },
        getProviderLabel(provider) {
            return provider === 'teams' ? 'Microsoft Teams' : 'Slack';
        },
        getProviderIcon(provider) {
            return provider === 'teams' ? 'bi-microsoft-teams' : 'bi-slack';
        },
        // Branding methods
        async loadBranding() {
            try {
                const response = await axios.get('/api/account');
                const account = response.data.data;
                this.branding.logo_url = account.logo_url || '';
                this.branding.brand_color = account.brand_color || '#22c55e';
            } catch (err) {
                console.error('Failed to load branding:', err);
            }
        },
        async saveBranding() {
            this.savingBranding = true;
            try {
                await axios.put('/api/account/branding', {
                    logo_url: this.branding.logo_url || null,
                    brand_color: this.branding.brand_color || null,
                });
                this.toast.success('Branding saved successfully');
            } catch (err) {
                this.toast.error(err.response?.data?.error || 'Failed to save branding');
            } finally {
                this.savingBranding = false;
            }
        },
        handleLogoError(event) {
            // Handle logo load errors
            event.target.style.display = 'none';
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
