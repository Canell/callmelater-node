/**
 * Date formatting utilities used across the application.
 */

/**
 * Get the user's preferred timezone from localStorage, falling back to browser timezone.
 * @returns {string} IANA timezone identifier
 */
export function getUserTimezone() {
    return localStorage.getItem('userTimezone') || Intl.DateTimeFormat().resolvedOptions().timeZone;
}

/**
 * Format a date string to locale string (date + time).
 * Displays in user's preferred timezone (from profile settings).
 * @param {string|null} dateStr - ISO date string
 * @returns {string} Formatted date or '-' if null
 */
export function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString(undefined, { timeZone: getUserTimezone() });
}

/**
 * Format a date string in a specific timezone.
 * @param {string|null} dateStr - ISO date string
 * @param {string} timezone - IANA timezone (e.g., 'UTC', 'America/New_York')
 * @returns {string} Formatted date or '-' if null
 */
export function formatDateInTimezone(dateStr, timezone = 'UTC') {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString(undefined, { timeZone: timezone });
}

/**
 * Format a date string to time only.
 * @param {string|null} dateStr - ISO date string
 * @returns {string} Formatted time or '-' if null
 */
export function formatTime(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleTimeString(undefined, { timeZone: getUserTimezone() });
}

/**
 * Format a date string to short date (e.g., "Jan 5").
 * @param {string|null} dateStr - ISO date string
 * @returns {string} Formatted date or '-' if null
 */
export function formatShortDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        timeZone: getUserTimezone(),
    });
}

/**
 * Format a date string to relative time (e.g., "5m ago", "2h ago").
 * Falls back to short date/time for older dates.
 * @param {string|null} dateStr - ISO date string
 * @returns {string} Relative time or '-' if null
 */
export function formatRelativeTime(dateStr) {
    if (!dateStr) return '-';

    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);

    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: getUserTimezone(),
    });
}

/**
 * Format a date string with custom options.
 * @param {string|null} dateStr - ISO date string
 * @param {Intl.DateTimeFormatOptions} options - Intl options
 * @returns {string} Formatted date or '-' if null
 */
export function formatDateCustom(dateStr, options) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', {
        ...options,
        timeZone: getUserTimezone(),
    });
}
