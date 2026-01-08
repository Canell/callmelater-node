<template>
    <div class="consent-result">
        <div class="card">
            <div class="icon" :class="iconClass">
                <span v-if="type === 'accepted'">&#10003;</span>
                <span v-else-if="type === 'declined' || type === 'unsubscribed'">&#10005;</span>
                <span v-else>&#33;</span>
            </div>

            <h1>{{ title }}</h1>
            <p>{{ message }}</p>

            <a href="/" class="btn">Go to Homepage</a>

            <p v-if="showResubscribe" class="resubscribe">
                Changed your mind?
                <a :href="resubscribeUrl">Click here to re-subscribe</a>
            </p>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ConsentResult',
    computed: {
        type() {
            const path = this.$route.path;
            if (path.includes('accepted')) return 'accepted';
            if (path.includes('declined')) return 'declined';
            if (path.includes('unsubscribed')) return 'unsubscribed';
            return 'error';
        },
        token() {
            return this.$route.query.token || null;
        },
        showResubscribe() {
            return (this.type === 'declined' || this.type === 'unsubscribed') && this.token;
        },
        resubscribeUrl() {
            return `/api/v1/consent/accept/${this.token}`;
        },
        iconClass() {
            if (this.type === 'accepted') return 'success';
            if (this.type === 'declined' || this.type === 'unsubscribed') return 'neutral';
            return 'error';
        },
        title() {
            switch (this.type) {
                case 'accepted':
                    return 'You\'re all set!';
                case 'declined':
                    return 'Preference saved';
                case 'unsubscribed':
                    return 'You\'ve been unsubscribed';
                default:
                    return 'Something went wrong';
            }
        },
        message() {
            switch (this.type) {
                case 'accepted':
                    return 'You\'ve opted in to receive reminder emails. You can unsubscribe at any time using the link at the bottom of any reminder.';
                case 'declined':
                    return 'You\'ve declined to receive reminders. You won\'t receive any reminder emails from CallMeLater.';
                case 'unsubscribed':
                    return 'You\'ve been unsubscribed from all reminder emails. You won\'t receive any more reminders from CallMeLater.';
                default:
                    return 'The link you used is invalid or has expired. Please check your email for a valid link.';
            }
        }
    }
};
</script>

<style scoped>
.consent-result {
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
    max-width: 440px;
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
    background-color: #f3f4f6;
    color: #6b7280;
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
    margin: 0 0 32px;
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    background-color: #111827;
    color: #fff;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: background-color 0.2s;
}

.btn:hover {
    background-color: #374151;
}

.resubscribe {
    margin-top: 24px;
    font-size: 14px;
    color: #6b7280;
}

.resubscribe a {
    color: #2563eb;
    text-decoration: underline;
}

.resubscribe a:hover {
    color: #1d4ed8;
}
</style>
