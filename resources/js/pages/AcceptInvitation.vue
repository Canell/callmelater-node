<template>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <!-- Loading -->
                <div v-if="loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 text-muted">Loading invitation...</p>
                </div>

                <!-- Error -->
                <div v-else-if="error" class="card card-cml">
                    <div class="card-body text-center py-5">
                        <div class="text-danger mb-3">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                        </div>
                        <h4>{{ errorTitle }}</h4>
                        <p class="text-muted mb-4">{{ error }}</p>
                        <router-link to="/" class="btn btn-cml-primary">Go to Homepage</router-link>
                    </div>
                </div>

                <!-- Invitation Details -->
                <div v-else-if="invitation" class="card card-cml">
                    <div class="card-body py-5">
                        <div class="text-center mb-4">
                            <div class="text-success mb-3">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <h3 class="mb-2">You're Invited!</h3>
                            <p class="text-muted">
                                <strong>{{ invitation.inviter_name }}</strong> has invited you to join
                            </p>
                            <h4 class="text-primary">{{ invitation.workspace_name }}</h4>
                        </div>

                        <div class="bg-light rounded p-3 mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Role:</span>
                                <span class="text-capitalize fw-medium">{{ invitation.role }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Plan:</span>
                                <span class="text-capitalize fw-medium">{{ invitation.plan }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Expires:</span>
                                <span>{{ formatDate(invitation.expires_at) }}</span>
                            </div>
                        </div>

                        <!-- Not authenticated -->
                        <div v-if="!isAuthenticated">
                            <!-- Magic link sent -->
                            <div v-if="magicLinkSent" class="text-center">
                                <div class="mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                        <polyline points="22 4 12 14.01 9 11.01"/>
                                    </svg>
                                </div>
                                <h5>Check your email</h5>
                                <p class="text-muted small mb-0">
                                    We've sent a magic link to <strong>{{ invitation.email }}</strong>.
                                    Click the link to continue.
                                </p>
                            </div>

                            <!-- Send magic link form -->
                            <div v-else class="d-grid gap-2">
                                <p class="text-center text-muted mb-3">
                                    Click below to receive a magic link at <strong>{{ invitation.email }}</strong>
                                </p>
                                <button
                                    class="btn btn-cml-primary btn-lg"
                                    @click="sendMagicLink"
                                    :disabled="sendingMagicLink"
                                >
                                    <span v-if="sendingMagicLink" class="spinner-border spinner-border-sm me-2"></span>
                                    {{ sendingMagicLink ? 'Sending...' : 'Continue with ' + invitation.email }}
                                </button>
                                <p class="text-center text-muted small mt-2 mb-0">
                                    We'll email you a link to verify your identity.
                                </p>
                            </div>
                        </div>

                        <!-- Authenticated -->
                        <div v-else>
                            <!-- Email mismatch warning -->
                            <div v-if="emailMismatch" class="alert alert-warning mb-4">
                                <strong>Email mismatch:</strong> This invitation was sent to
                                <strong>{{ invitation.email }}</strong>, but you're logged in as
                                <strong>{{ userEmail }}</strong>.
                                <br><br>
                                Please log out and log in with the correct email to accept this invitation.
                            </div>

                            <!-- Account conflict warning -->
                            <div v-else-if="conflict" class="alert alert-warning mb-4">
                                <h5 class="alert-heading mb-3">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                        <line x1="12" y1="9" x2="12" y2="13"/>
                                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                                    </svg>
                                    You already have an account
                                </h5>
                                <p class="mb-2">
                                    You're currently the owner of <strong>{{ conflict.current_account_name }}</strong>
                                    <span v-if="conflict.current_plan !== 'free'" class="badge bg-primary ms-1">{{ conflict.current_plan }}</span>
                                </p>
                                <p class="mb-3">
                                    To join <strong>{{ conflict.target_workspace_name }}</strong>, you must leave your current account.
                                </p>
                                <div v-if="conflict.has_paid_subscription" class="bg-white rounded p-3 mb-3">
                                    <p class="mb-0 small">
                                        <strong>Your subscription will be cancelled.</strong>
                                        Any remaining balance will be prorated automatically.
                                    </p>
                                </div>
                                <p class="small text-muted mb-0">
                                    All members share the same plan and usage limits within a workspace.
                                </p>
                            </div>

                            <!-- Action buttons -->
                            <div v-if="!emailMismatch" class="d-grid gap-2">
                                <button
                                    class="btn btn-cml-primary btn-lg"
                                    @click="acceptInvitation"
                                    :disabled="accepting"
                                >
                                    <span v-if="accepting" class="spinner-border spinner-border-sm me-2"></span>
                                    <template v-if="conflict">
                                        {{ accepting ? 'Joining...' : 'Leave Current Account & Join' }}
                                    </template>
                                    <template v-else>
                                        {{ accepting ? 'Joining...' : 'Accept & Join Workspace' }}
                                    </template>
                                </button>
                                <router-link to="/dashboard" class="btn btn-outline-secondary">
                                    Decline
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success -->
                <div v-else-if="success" class="card card-cml">
                    <div class="card-body text-center py-5">
                        <div class="text-success mb-3">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <h4>Welcome to the workspace!</h4>
                        <p class="text-muted mb-4">{{ successMessage }}</p>
                        <router-link to="/dashboard" class="btn btn-cml-primary">
                            Go to Dashboard
                        </router-link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'AcceptInvitation',
    inject: ['toast'],
    data() {
        return {
            loading: true,
            invitation: null,
            error: null,
            errorTitle: 'Invalid Invitation',
            accepting: false,
            success: false,
            successMessage: '',
            userEmail: null,
            // Magic link state
            sendingMagicLink: false,
            magicLinkSent: false,
            // Account conflict state
            conflict: null,
        };
    },
    computed: {
        isAuthenticated() {
            return !!localStorage.getItem('token');
        },
        emailMismatch() {
            if (!this.invitation || !this.userEmail) return false;
            return this.invitation.email.toLowerCase() !== this.userEmail.toLowerCase();
        },
    },
    async mounted() {
        await this.loadInvitation();
        if (this.isAuthenticated) {
            await this.loadUser();
        }
    },
    methods: {
        async loadInvitation() {
            try {
                const token = this.$route.params.token;
                const response = await axios.get(`/api/invitations/${token}`);
                this.invitation = response.data;
            } catch (err) {
                if (err.response?.status === 404) {
                    this.error = 'This invitation link is invalid or has been cancelled.';
                } else if (err.response?.status === 410) {
                    this.errorTitle = 'Invitation Expired';
                    this.error = err.response.data.error || 'This invitation has expired or already been used.';
                } else {
                    this.error = 'Failed to load invitation. Please try again.';
                }
            } finally {
                this.loading = false;
            }
        },
        async loadUser() {
            try {
                const response = await axios.get('/api/user');
                this.userEmail = response.data.email;
            } catch (err) {
                // Not authenticated or error - ignore
            }
        },
        async acceptInvitation() {
            this.accepting = true;
            try {
                const token = this.$route.params.token;
                const response = await axios.post(`/api/invitations/${token}/accept`, {
                    confirm_leave_account: this.conflict !== null,
                });

                // Check if we need confirmation
                if (response.data.requires_confirmation) {
                    this.conflict = response.data.conflict;
                    this.accepting = false;
                    return;
                }

                this.success = true;
                this.successMessage = response.data.message;
                this.invitation = null;
                this.conflict = null;
            } catch (err) {
                if (err.response?.status === 409 && err.response.data.requires_confirmation) {
                    // Account conflict - show confirmation UI
                    this.conflict = err.response.data.conflict;
                } else {
                    this.toast.error(err.response?.data?.error || 'Failed to accept invitation');
                }
            } finally {
                this.accepting = false;
            }
        },
        formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            });
        },
        async sendMagicLink() {
            this.sendingMagicLink = true;
            try {
                // Get CSRF cookie first
                await axios.get('/sanctum/csrf-cookie');

                // Store redirect URL for after magic link verification
                localStorage.setItem('auth_redirect', this.$route.fullPath);

                // Use signup endpoint - it handles both new and existing users
                await axios.post('/auth/magic-link/signup', {
                    email: this.invitation.email,
                });

                this.magicLinkSent = true;
            } catch (err) {
                if (err.response?.status === 429) {
                    this.toast.error('Too many requests. Please wait a few minutes.');
                } else {
                    this.toast.error('Failed to send magic link. Please try again.');
                }
            } finally {
                this.sendingMagicLink = false;
            }
        },
    },
};
</script>
