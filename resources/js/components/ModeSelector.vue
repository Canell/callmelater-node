<template>
    <div class="card card-cml mb-4">
        <div class="card-header bg-transparent">
            <h5 class="mb-0">{{ title }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6">
                    <div
                        class="card h-100 cursor-pointer"
                        :class="{ 'border-primary border-2': modelValue === 'immediate' }"
                        @click="$emit('update:modelValue', 'immediate')"
                        role="button"
                    >
                        <div class="card-body text-center py-4">
                            <div class="fs-2 mb-2">&#9889;</div>
                            <h5 class="mb-1">Webhook</h5>
                            <p class="text-muted mb-0 small">{{ immediateDescription }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div
                        class="card h-100 cursor-pointer"
                        :class="{ 'border-primary border-2': modelValue === 'gated' }"
                        @click="$emit('update:modelValue', 'gated')"
                        role="button"
                    >
                        <div class="card-body text-center py-4">
                            <div class="fs-2 mb-2">&#128275;</div>
                            <h5 class="mb-1">Approval</h5>
                            <p class="text-muted mb-0 small">Requires human approval first</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ModeSelector',
    props: {
        modelValue: {
            type: String,
            required: true,
        },
        title: {
            type: String,
            default: 'How should this action execute?',
        },
        immediateDescription: {
            type: String,
            default: 'Execute automatically at scheduled time',
        },
    },
    emits: ['update:modelValue'],
};
</script>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}
</style>
