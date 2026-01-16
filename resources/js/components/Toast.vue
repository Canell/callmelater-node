<template>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <TransitionGroup name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="toast show"
                :class="toastClass(toast.type)"
                role="alert"
            >
                <div class="toast-header">
                    <strong class="me-auto">{{ toast.title || defaultTitle(toast.type) }}</strong>
                    <button type="button" class="btn-close" @click="remove(toast.id)"></button>
                </div>
                <div class="toast-body">
                    {{ toast.message }}
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>

<script>
export default {
    name: 'Toast',
    data() {
        return {
            toasts: [],
            counter: 0,
        };
    },
    methods: {
        show({ message, type = 'info', title = null, duration = 5000 }) {
            const id = ++this.counter;
            this.toasts.push({ id, message, type, title });

            if (duration > 0) {
                setTimeout(() => this.remove(id), duration);
            }
        },
        success(message, title = null) {
            this.show({ message, type: 'success', title });
        },
        error(message, title = null) {
            this.show({ message, type: 'error', title, duration: 8000 });
        },
        warning(message, title = null) {
            this.show({ message, type: 'warning', title });
        },
        info(message, title = null) {
            this.show({ message, type: 'info', title });
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
        toastClass(type) {
            const classes = {
                success: 'border-success',
                error: 'border-danger',
                warning: 'border-warning',
                info: 'border-primary',
            };
            return classes[type] || 'border-primary';
        },
        defaultTitle(type) {
            const titles = {
                success: 'Success',
                error: 'Error',
                warning: 'Warning',
                info: 'Info',
            };
            return titles[type] || 'Notice';
        },
    },
};
</script>

<style scoped>
.toast {
    min-width: 300px;
    border-left-width: 4px !important;
}

.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>
