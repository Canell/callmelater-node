/**
 * Composable for action status formatting and badge styling.
 * Used across Dashboard, ActionDetail, and other action-related pages.
 */

// ==================== TERMINOLOGY MAPPINGS ====================

/**
 * Map internal action mode to user-friendly type label.
 * immediate -> Webhook, gated -> Approval
 */
const ACTION_TYPE_LABELS = {
    immediate: 'Webhook',
    gated: 'Approval',
    webhook: 'Webhook',
    approval: 'Approval',
};

/**
 * Map internal step type to user-friendly label.
 * http_call -> Webhook, gated -> Approval, delay -> Wait
 */
const STEP_TYPE_LABELS = {
    http_call: 'Webhook',
    gated: 'Approval',
    delay: 'Wait',
    webhook: 'Webhook',
    approval: 'Approval',
    wait: 'Wait',
};

/**
 * Badge classes for step types.
 */
const STEP_TYPE_BADGE_CLASSES = {
    http_call: 'bg-info text-dark',
    gated: 'bg-warning text-dark',
    delay: 'bg-secondary',
    webhook: 'bg-info text-dark',
    approval: 'bg-warning text-dark',
    wait: 'bg-secondary',
};

// ==================== STATUS MAPPINGS ====================

const ACTION_STATUS_LABELS = {
    // New terminology
    scheduled: 'Scheduled',
    // Legacy terminology (for backwards compatibility)
    pending_resolution: 'Scheduled',
    resolved: 'Resolved',
    executing: 'Executing',
    awaiting_response: 'Awaiting Response',
    executed: 'Executed',
    failed: 'Failed',
    cancelled: 'Cancelled',
    expired: 'Expired',
};

const ACTION_STATUS_BADGE_CLASSES = {
    // New terminology
    scheduled: 'bg-primary',
    // Legacy terminology (for backwards compatibility)
    pending_resolution: 'bg-secondary',
    resolved: 'bg-primary',
    executing: 'bg-info',
    awaiting_response: 'bg-warning text-dark',
    executed: 'bg-success',
    failed: 'bg-danger',
    cancelled: 'bg-secondary',
    expired: 'bg-secondary',
};

const RECIPIENT_STATUS_BADGE_CLASSES = {
    pending: 'bg-secondary',
    confirmed: 'bg-success',
    declined: 'bg-danger',
    snoozed: 'bg-warning text-dark',
};

const CANCELLABLE_STATUSES = ['pending_resolution', 'resolved', 'awaiting_response', 'scheduled'];

const RETRYABLE_STATUSES = ['failed'];

export function useActionStatus() {
    /**
     * Format an action status to a human-readable label.
     */
    const formatStatus = (status) => {
        return ACTION_STATUS_LABELS[status] || status;
    };

    /**
     * Get the Bootstrap badge class for an action status.
     */
    const statusBadgeClass = (status) => {
        return ACTION_STATUS_BADGE_CLASSES[status] || 'bg-secondary';
    };

    /**
     * Get the Bootstrap badge class for a recipient status.
     */
    const recipientBadgeClass = (status) => {
        return RECIPIENT_STATUS_BADGE_CLASSES[status] || 'bg-secondary';
    };

    /**
     * Check if an action can be cancelled based on its status.
     */
    const canCancel = (status) => {
        return CANCELLABLE_STATUSES.includes(status);
    };

    /**
     * Check if an action can be manually retried based on its status.
     */
    const canRetry = (status) => {
        return RETRYABLE_STATUSES.includes(status);
    };

    // ==================== TERMINOLOGY FUNCTIONS ====================

    /**
     * Format action mode/type to user-friendly label.
     * @param {string} mode - 'immediate', 'gated', 'webhook', or 'approval'
     * @returns {string} - 'Webhook' or 'Approval'
     */
    const formatType = (mode) => {
        return ACTION_TYPE_LABELS[mode] || mode;
    };

    /**
     * Format step type to user-friendly label.
     * @param {string} type - 'http_call', 'gated', 'delay', 'webhook', 'approval', or 'wait'
     * @returns {string} - 'Webhook', 'Approval', or 'Wait'
     */
    const formatStepType = (type) => {
        return STEP_TYPE_LABELS[type] || type;
    };

    /**
     * Get badge class for step type.
     * @param {string} type - Step type
     * @returns {string} - Bootstrap badge class
     */
    const stepTypeBadgeClass = (type) => {
        return STEP_TYPE_BADGE_CLASSES[type] || 'bg-secondary';
    };

    return {
        // Status functions
        formatStatus,
        statusBadgeClass,
        recipientBadgeClass,
        canCancel,
        canRetry,
        // Terminology functions
        formatType,
        formatStepType,
        stepTypeBadgeClass,
        // Export constants for direct access if needed
        ACTION_STATUS_LABELS,
        ACTION_STATUS_BADGE_CLASSES,
        RECIPIENT_STATUS_BADGE_CLASSES,
        CANCELLABLE_STATUSES,
        RETRYABLE_STATUSES,
        ACTION_TYPE_LABELS,
        STEP_TYPE_LABELS,
        STEP_TYPE_BADGE_CLASSES,
    };
}
