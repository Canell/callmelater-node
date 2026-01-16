<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="modal-overlay" @click.self="cancel">
                <div class="modal-card" :class="variantClass">
                    <div class="modal-icon" :class="iconClass">
                        <span v-if="variant === 'danger'">&#9888;</span>
                        <span v-else-if="variant === 'warning'">&#63;</span>
                        <span v-else>&#8505;</span>
                    </div>

                    <h3 class="modal-title">{{ title }}</h3>
                    <p class="modal-message">{{ message }}</p>

                    <div class="modal-actions">
                        <button class="btn btn-cancel" @click="cancel">
                            {{ cancelText }}
                        </button>
                        <button class="btn" :class="confirmButtonClass" @click="confirm">
                            {{ confirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script>
export default {
    name: 'ConfirmModal',
    props: {
        show: {
            type: Boolean,
            default: false
        },
        title: {
            type: String,
            default: 'Are you sure?'
        },
        message: {
            type: String,
            default: 'This action cannot be undone.'
        },
        confirmText: {
            type: String,
            default: 'Confirm'
        },
        cancelText: {
            type: String,
            default: 'Cancel'
        },
        variant: {
            type: String,
            default: 'warning', // 'danger', 'warning', 'info'
            validator: (v) => ['danger', 'warning', 'info'].includes(v)
        }
    },
    emits: ['confirm', 'cancel'],
    computed: {
        variantClass() {
            return `variant-${this.variant}`;
        },
        iconClass() {
            return `icon-${this.variant}`;
        },
        confirmButtonClass() {
            if (this.variant === 'danger') return 'btn-danger';
            if (this.variant === 'warning') return 'btn-warning';
            return 'btn-primary';
        }
    },
    methods: {
        confirm() {
            this.$emit('confirm');
        },
        cancel() {
            this.$emit('cancel');
        }
    },
    watch: {
        show(newVal) {
            // Prevent body scroll when modal is open
            document.body.style.overflow = newVal ? 'hidden' : '';
        }
    }
};
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}

.modal-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    padding: 32px;
    text-align: center;
    max-width: 400px;
    width: 100%;
}

.modal-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 28px;
}

.icon-danger {
    background-color: #fef2f2;
    color: #dc2626;
}

.icon-warning {
    background-color: #fef3c7;
    color: #d97706;
}

.icon-info {
    background-color: #dbeafe;
    color: #2563eb;
}

.modal-title {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin: 0 0 8px;
}

.modal-message {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.5;
    margin: 0 0 24px;
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.btn {
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.btn-cancel {
    background-color: #f3f4f6;
    color: #374151;
}

.btn-cancel:hover {
    background-color: #e5e7eb;
}

.btn-danger {
    background-color: #dc2626;
    color: #fff;
}

.btn-danger:hover {
    background-color: #b91c1c;
}

.btn-warning {
    background-color: #d97706;
    color: #fff;
}

.btn-warning:hover {
    background-color: #b45309;
}

.btn-primary {
    background-color: #22c55e;
    color: #fff;
}

.btn-primary:hover {
    background-color: #16a34a;
}

/* Transitions */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-active .modal-card,
.modal-leave-active .modal-card {
    transition: transform 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .modal-card,
.modal-leave-to .modal-card {
    transform: scale(0.95);
}
</style>
