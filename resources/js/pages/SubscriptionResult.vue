<template>
    <div class="subscription-result">
        <div class="card">
            <div class="icon" :class="iconClass">
                <span v-if="isSuccess">&#10003;</span>
                <span v-else-if="isCanceled">&#10005;</span>
                <span v-else>&#33;</span>
            </div>

            <h1>{{ title }}</h1>
            <p>{{ message }}</p>
            <p v-if="endsAt" class="ends-at">Access until: <strong>{{ endsAt }}</strong></p>

            <router-link to="/settings" class="btn btn-primary" @click="goToBilling">
                Go to Billing
            </router-link>

            <router-link to="/dashboard" class="btn btn-secondary">
                Go to Dashboard
            </router-link>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SubscriptionResult',
    computed: {
        type() {
            return this.$route.query.status || 'success';
        },
        plan() {
            return this.$route.query.plan || null;
        },
        endsAt() {
            return this.$route.query.ends_at || null;
        },
        errorMessage() {
            return this.$route.query.message || null;
        },
        isSuccess() {
            return ['success', 'subscribed', 'changed', 'resumed'].includes(this.type);
        },
        isCanceled() {
            return this.type === 'canceled';
        },
        iconClass() {
            if (this.isSuccess) return 'success';
            if (this.isCanceled) return 'neutral';
            return 'error';
        },
        title() {
            switch (this.type) {
                case 'success':
                case 'subscribed':
                    return 'Welcome to ' + (this.plan ? this.capitalize(this.plan) : 'your new plan') + '!';
                case 'changed':
                    return 'Plan changed successfully!';
                case 'resumed':
                    return 'Subscription resumed!';
                case 'canceled':
                    return 'Subscription canceled';
                case 'cancelled':
                    return 'Checkout cancelled';
                default:
                    return 'Something went wrong';
            }
        },
        message() {
            switch (this.type) {
                case 'success':
                case 'subscribed':
                    return 'Your subscription is now active. You have access to all ' + (this.plan ? this.capitalize(this.plan) : 'premium') + ' features.';
                case 'changed':
                    return 'Your plan has been updated. The new limits are now active.';
                case 'resumed':
                    return 'Great to have you back! Your subscription is active again.';
                case 'canceled':
                    return 'Your subscription has been canceled. You\'ll keep access until the end of your billing period.';
                case 'cancelled':
                    return 'You cancelled the checkout process. No charges were made.';
                case 'error':
                    return this.errorMessage || 'There was a problem processing your subscription. Please try again or contact support.';
                default:
                    return 'There was a problem processing your subscription. Please try again or contact support.';
            }
        }
    },
    methods: {
        capitalize(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        },
        goToBilling() {
            // Set the billing tab as active when navigating to settings
            this.$router.push({ path: '/settings', query: { tab: 'billing' } });
        }
    }
};
</script>

<style scoped>
.subscription-result {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f3f4f6;
    padding: 20px;
}

.card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 48px;
    text-align: center;
    max-width: 480px;
}

.icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    font-size: 32px;
    font-weight: bold;
}

.icon.success {
    background-color: #dcfce7;
    color: #16a34a;
}

.icon.neutral {
    background-color: #fef3c7;
    color: #d97706;
}

.icon.error {
    background-color: #fef2f2;
    color: #dc2626;
}

h1 {
    font-size: 24px;
    font-weight: 600;
    color: #111827;
    margin: 0 0 12px;
}

p {
    font-size: 16px;
    color: #6b7280;
    line-height: 1.6;
    margin: 0 0 24px;
}

.ends-at {
    font-size: 14px;
    color: #6b7280;
    background-color: #f9fafb;
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 24px;
}

.ends-at strong {
    color: #111827;
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: background-color 0.2s;
    margin: 0 8px 8px;
}

.btn-primary {
    background-color: #22c55e;
    color: #fff;
}

.btn-primary:hover {
    background-color: #16a34a;
    color: #fff;
}

.btn-secondary {
    background-color: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}

.btn-secondary:hover {
    background-color: #e5e7eb;
    color: #111827;
}
</style>
