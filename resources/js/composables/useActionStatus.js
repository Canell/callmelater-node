/**
 * Composable for action status formatting and badge styling.
 * Used across Dashboard, ActionDetail, and other action-related pages.
 */

const ACTION_STATUS_LABELS = {
    pending_resolution: 'Pending',
    resolved: 'Scheduled',
    executing: 'Executing',
    awaiting_response: 'Awaiting Response',
    executed: 'Executed',
    failed: 'Failed',
    cancelled: 'Cancelled',
    expired: 'Expired',
};

const ACTION_STATUS_BADGE_CLASSES = {
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

const CANCELLABLE_STATUSES = ['pending_resolution', 'resolved', 'awaiting_response'];

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

    return {
        formatStatus,
        statusBadgeClass,
        recipientBadgeClass,
        canCancel,
        // Export constants for direct access if needed
        ACTION_STATUS_LABELS,
        ACTION_STATUS_BADGE_CLASSES,
        RECIPIENT_STATUS_BADGE_CLASSES,
        CANCELLABLE_STATUSES,
    };
}
