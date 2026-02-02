<template>
    <div class="unified-recipient-selector">
        <label v-if="label" class="form-label">{{ label }}</label>

        <!-- Selected recipients as tags -->
        <div v-if="selected.length > 0" class="selected-recipients mb-2">
            <span
                v-for="recipient in selected"
                :key="recipient.uri"
                class="badge me-1 mb-1 d-inline-flex align-items-center"
                :class="getBadgeClass(recipient)"
            >
                <span class="me-1">{{ getIcon(recipient) }}</span>
                {{ recipient.label }}
                <small v-if="recipient.sublabel" class="ms-1 opacity-75">({{ recipient.sublabel }})</small>
                <button
                    type="button"
                    class="btn-close btn-close-white ms-1"
                    style="font-size: 0.6rem;"
                    @click="removeRecipient(recipient)"
                    :aria-label="'Remove ' + recipient.label"
                ></button>
            </span>
        </div>

        <!-- Dropdown selector -->
        <div class="position-relative" ref="dropdownContainer">
            <input
                type="text"
                class="form-control"
                :placeholder="placeholder"
                v-model="searchQuery"
                @focus="showDropdown = true"
                @click="showDropdown = true"
                @input="onSearchInput"
                @keydown.enter.prevent="onEnter"
                @keydown.escape="showDropdown = false"
                @keydown.down.prevent="navigateDown"
                @keydown.up.prevent="navigateUp"
                ref="searchInput"
            />

            <!-- Dropdown menu -->
            <div
                v-if="showDropdown && (filteredRecipients.length > 0 || canAddManualEntry)"
                class="dropdown-menu show w-100 mt-1"
                style="max-height: 300px; overflow-y: auto;"
            >
                <!-- Workspace Members group (users in same account) -->
                <template v-if="groupedRecipients.users.length > 0">
                    <h6 class="dropdown-header">Workspace Members</h6>
                    <button
                        v-for="(recipient, idx) in groupedRecipients.users"
                        :key="recipient.uri"
                        type="button"
                        class="dropdown-item d-flex align-items-center"
                        :class="{ active: highlightedIndex === getGlobalIndex('users', idx) }"
                        @click="selectRecipient(recipient)"
                        @mouseenter="highlightedIndex = getGlobalIndex('users', idx)"
                    >
                        <span class="me-2">{{ getIcon(recipient) }}</span>
                        <div>
                            <div>{{ recipient.label }}</div>
                            <small class="text-muted">{{ recipient.sublabel }}</small>
                        </div>
                    </button>
                </template>

                <!-- Contacts group -->
                <template v-if="groupedRecipients.contacts.length > 0">
                    <div v-if="groupedRecipients.users.length > 0" class="dropdown-divider"></div>
                    <h6 class="dropdown-header">Contacts</h6>
                    <button
                        v-for="(recipient, idx) in groupedRecipients.contacts"
                        :key="recipient.uri"
                        type="button"
                        class="dropdown-item d-flex align-items-center"
                        :class="{ active: highlightedIndex === getGlobalIndex('contacts', idx) }"
                        @click="selectRecipient(recipient)"
                        @mouseenter="highlightedIndex = getGlobalIndex('contacts', idx)"
                    >
                        <span class="me-2">{{ getIcon(recipient) }}</span>
                        <div>
                            <div>{{ recipient.label }}</div>
                            <small class="text-muted">{{ recipient.sublabel }}</small>
                        </div>
                    </button>
                </template>

                <!-- Chat Channels group -->
                <template v-if="groupedRecipients.channels.length > 0">
                    <div v-if="groupedRecipients.users.length > 0 || groupedRecipients.contacts.length > 0" class="dropdown-divider"></div>
                    <h6 class="dropdown-header">Chat Channels</h6>
                    <button
                        v-for="(recipient, idx) in groupedRecipients.channels"
                        :key="recipient.uri"
                        type="button"
                        class="dropdown-item d-flex align-items-center"
                        :class="{ active: highlightedIndex === getGlobalIndex('channels', idx) }"
                        @click="selectRecipient(recipient)"
                        @mouseenter="highlightedIndex = getGlobalIndex('channels', idx)"
                    >
                        <span class="me-2">{{ getIcon(recipient) }}</span>
                        <div>
                            <div>{{ recipient.label }}</div>
                            <small class="text-muted">{{ recipient.sublabel }}</small>
                        </div>
                    </button>
                </template>

                <!-- Manual entry option -->
                <template v-if="canAddManualEntry">
                    <div v-if="filteredRecipients.length > 0" class="dropdown-divider"></div>
                    <button
                        type="button"
                        class="dropdown-item d-flex align-items-center"
                        :class="{ active: highlightedIndex === manualEntryIndex }"
                        @click="addManualEntry"
                        @mouseenter="highlightedIndex = manualEntryIndex"
                    >
                        <span class="me-2">+</span>
                        <div>
                            <div>Add "{{ searchQuery }}"</div>
                            <small class="text-muted">{{ isEmail(searchQuery) ? 'Email' : 'Phone' }}</small>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <!-- Helper text -->
        <div v-if="helperText" class="form-text">{{ helperText }}</div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'UnifiedRecipientSelector',
    props: {
        modelValue: {
            type: Array,
            default: () => [],
        },
        label: {
            type: String,
            default: 'Recipients',
        },
        placeholder: {
            type: String,
            default: 'Search contacts, channels, or enter email/phone...',
        },
        helperText: {
            type: String,
            default: '',
        },
        allowManualEntry: {
            type: Boolean,
            default: true,
        },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            recipients: [],
            selected: [],
            searchQuery: '',
            showDropdown: false,
            highlightedIndex: 0,
            loading: false,
        };
    },
    computed: {
        filteredRecipients() {
            const query = this.searchQuery.toLowerCase().trim();
            const selectedUris = this.selected.map(r => r.uri);

            return this.recipients.filter(r => {
                // Exclude already selected
                if (selectedUris.includes(r.uri)) return false;

                // Filter by search query
                if (!query) return true;
                return (
                    r.label.toLowerCase().includes(query) ||
                    (r.sublabel && r.sublabel.toLowerCase().includes(query))
                );
            });
        },
        groupedRecipients() {
            const users = this.filteredRecipients.filter(r => r.type === 'user');
            const contacts = this.filteredRecipients.filter(r => r.type === 'contact');
            const channels = this.filteredRecipients.filter(r => r.type === 'channel');
            return { users, contacts, channels };
        },
        canAddManualEntry() {
            if (!this.allowManualEntry || !this.searchQuery.trim()) return false;

            const query = this.searchQuery.trim();

            // Check if it's a valid email or phone
            if (!this.isEmail(query) && !this.isPhone(query)) return false;

            // Check if already selected
            const uri = this.isEmail(query) ? `email:${query}` : `phone:${query}`;
            if (this.selected.some(r => r.uri === uri)) return false;

            // Check if it matches an existing recipient exactly
            const matchesExisting = this.recipients.some(r =>
                r.sublabel && r.sublabel.toLowerCase() === query.toLowerCase()
            );
            if (matchesExisting) return false;

            return true;
        },
        manualEntryIndex() {
            return this.filteredRecipients.length;
        },
        totalItems() {
            return this.filteredRecipients.length + (this.canAddManualEntry ? 1 : 0);
        },
    },
    watch: {
        modelValue: {
            immediate: true,
            handler(newVal) {
                // Sync with external value
                if (Array.isArray(newVal)) {
                    this.selected = newVal;
                }
            },
        },
        selected: {
            deep: true,
            handler(newVal) {
                this.$emit('update:modelValue', newVal);
            },
        },
    },
    mounted() {
        this.loadRecipients();

        // Close dropdown when clicking outside
        document.addEventListener('click', this.handleClickOutside);
    },
    beforeUnmount() {
        document.removeEventListener('click', this.handleClickOutside);
    },
    methods: {
        async loadRecipients() {
            this.loading = true;
            try {
                const response = await axios.get('/api/v1/recipients');
                this.recipients = response.data.data || [];
            } catch (err) {
                console.error('Failed to load recipients:', err);
            } finally {
                this.loading = false;
            }
        },
        selectRecipient(recipient) {
            if (!this.selected.some(r => r.uri === recipient.uri)) {
                this.selected.push(recipient);
            }
            this.searchQuery = '';
            this.showDropdown = false;
            this.$refs.searchInput?.focus();
        },
        removeRecipient(recipient) {
            this.selected = this.selected.filter(r => r.uri !== recipient.uri);
        },
        addManualEntry() {
            const query = this.searchQuery.trim();
            if (!query) return;

            const isEmailEntry = this.isEmail(query);
            const recipient = {
                uri: isEmailEntry ? `email:${query}` : `phone:${query}`,
                label: query,
                sublabel: isEmailEntry ? 'Email' : 'Phone',
                type: 'manual',
                contact_type: isEmailEntry ? 'email' : 'phone',
            };

            this.selected.push(recipient);
            this.searchQuery = '';
            this.showDropdown = false;
            this.$refs.searchInput?.focus();
        },
        onSearchInput() {
            this.showDropdown = true;
            this.highlightedIndex = 0;
        },
        onEnter() {
            if (this.highlightedIndex < this.filteredRecipients.length) {
                // Select from list
                const globalIdx = this.highlightedIndex;
                let count = 0;
                for (const recipient of this.groupedRecipients.users) {
                    if (count === globalIdx) {
                        this.selectRecipient(recipient);
                        return;
                    }
                    count++;
                }
                for (const recipient of this.groupedRecipients.contacts) {
                    if (count === globalIdx) {
                        this.selectRecipient(recipient);
                        return;
                    }
                    count++;
                }
                for (const recipient of this.groupedRecipients.channels) {
                    if (count === globalIdx) {
                        this.selectRecipient(recipient);
                        return;
                    }
                    count++;
                }
            } else if (this.canAddManualEntry) {
                this.addManualEntry();
            }
        },
        navigateDown() {
            if (this.highlightedIndex < this.totalItems - 1) {
                this.highlightedIndex++;
            }
        },
        navigateUp() {
            if (this.highlightedIndex > 0) {
                this.highlightedIndex--;
            }
        },
        getGlobalIndex(group, localIndex) {
            if (group === 'users') {
                return localIndex;
            }
            if (group === 'contacts') {
                return this.groupedRecipients.users.length + localIndex;
            }
            // channels
            return this.groupedRecipients.users.length + this.groupedRecipients.contacts.length + localIndex;
        },
        handleClickOutside(event) {
            if (this.$refs.dropdownContainer && !this.$refs.dropdownContainer.contains(event.target)) {
                this.showDropdown = false;
            }
        },
        isEmail(str) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(str);
        },
        isPhone(str) {
            // Basic phone number validation
            const digits = str.replace(/\D/g, '');
            return digits.length >= 10 && /^\+?[\d\s\-()]+$/.test(str);
        },
        getIcon(recipient) {
            if (recipient.type === 'channel') {
                return recipient.provider === 'slack' ? '#' : '//';
            }
            if (recipient.type === 'manual' || recipient.contact_type === 'email') {
                return '@';
            }
            if (recipient.contact_type === 'phone') {
                return '#';
            }
            return '@';
        },
        getBadgeClass(recipient) {
            if (recipient.type === 'channel') {
                return recipient.provider === 'slack' ? 'bg-success' : 'bg-primary';
            }
            if (recipient.contact_type === 'phone') {
                return 'bg-info';
            }
            return 'bg-secondary';
        },
    },
};
</script>

<style scoped>
.unified-recipient-selector .dropdown-menu {
    position: absolute;
    z-index: 1050;
}

.unified-recipient-selector .dropdown-item {
    cursor: pointer;
}

.unified-recipient-selector .dropdown-item.active {
    background-color: #e9ecef;
    color: inherit;
}

.unified-recipient-selector .dropdown-item:hover {
    background-color: #e9ecef;
}

.selected-recipients .badge {
    padding: 0.4em 0.65em;
}
</style>
