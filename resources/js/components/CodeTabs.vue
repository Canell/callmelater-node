<template>
  <div class="code-tabs">
    <div class="code-tabs-header">
      <div class="code-tabs-nav">
        <button
          v-for="lang in languages"
          :key="lang.id"
          :class="['code-tab', { active: activeTab === lang.id }]"
          @click="setActiveTab(lang.id)"
        >
          <component :is="lang.icon" v-if="lang.icon" class="tab-icon" />
          {{ lang.label }}
        </button>
      </div>
      <button class="copy-btn" @click="copyCode" :title="copied ? 'Copied!' : 'Copy code'">
        <svg v-if="!copied" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
        </svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </button>
    </div>
    <div class="code-tabs-content">
      <pre><code :class="'language-' + activeLanguage.syntax">{{ activeCode }}</code></pre>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  examples: {
    type: Object,
    required: true,
    // { curl: '...', php: '...', python: '...', javascript: '...', java: '...' }
  },
  defaultTab: {
    type: String,
    default: 'curl'
  }
});

const LANGUAGE_CONFIG = {
  curl: { id: 'curl', label: 'cURL', syntax: 'bash' },
  php: { id: 'php', label: 'PHP', syntax: 'php' },
  python: { id: 'python', label: 'Python', syntax: 'python' },
  javascript: { id: 'javascript', label: 'JavaScript', syntax: 'javascript' },
  node: { id: 'node', label: 'Node.js', syntax: 'javascript' },
  java: { id: 'java', label: 'Java', syntax: 'java' },
  go: { id: 'go', label: 'Go', syntax: 'go' },
  ruby: { id: 'ruby', label: 'Ruby', syntax: 'ruby' },
};

const activeTab = ref(props.defaultTab);
const copied = ref(false);

// Get available languages based on provided examples
const languages = computed(() => {
  return Object.keys(props.examples)
    .filter(key => LANGUAGE_CONFIG[key])
    .map(key => LANGUAGE_CONFIG[key]);
});

const activeLanguage = computed(() => {
  return LANGUAGE_CONFIG[activeTab.value] || LANGUAGE_CONFIG.curl;
});

const activeCode = computed(() => {
  return props.examples[activeTab.value] || '';
});

function setActiveTab(tabId) {
  activeTab.value = tabId;
  // Save preference
  localStorage.setItem('preferredCodeLanguage', tabId);
}

function copyCode() {
  navigator.clipboard.writeText(activeCode.value).then(() => {
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  });
}

onMounted(() => {
  // Restore preference
  const savedPreference = localStorage.getItem('preferredCodeLanguage');
  if (savedPreference && props.examples[savedPreference]) {
    activeTab.value = savedPreference;
  }
});
</script>

<style scoped>
.code-tabs {
  border-radius: 8px;
  overflow: hidden;
  background: #1e1e1e;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.code-tabs-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #2d2d2d;
  border-bottom: 1px solid #404040;
  padding: 0 8px;
}

.code-tabs-nav {
  display: flex;
  gap: 4px;
  overflow-x: auto;
  padding: 8px 0;
}

.code-tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: transparent;
  border: none;
  border-radius: 4px;
  color: #a0a0a0;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.code-tab:hover {
  color: #e0e0e0;
  background: rgba(255, 255, 255, 0.05);
}

.code-tab.active {
  color: #22C55E;
  background: rgba(34, 197, 94, 0.1);
}

.tab-icon {
  width: 16px;
  height: 16px;
}

.copy-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: transparent;
  border: none;
  border-radius: 4px;
  color: #a0a0a0;
  cursor: pointer;
  transition: all 0.15s ease;
}

.copy-btn:hover {
  color: #e0e0e0;
  background: rgba(255, 255, 255, 0.1);
}

.code-tabs-content {
  padding: 16px;
  overflow-x: auto;
}

.code-tabs-content pre {
  margin: 0;
  padding: 0;
  background: transparent;
}

.code-tabs-content code {
  font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
  font-size: 13px;
  line-height: 1.6;
  color: #e0e0e0;
  white-space: pre;
}

/* Syntax highlighting (basic) */
.code-tabs-content code :deep(.keyword) { color: #c678dd; }
.code-tabs-content code :deep(.string) { color: #98c379; }
.code-tabs-content code :deep(.comment) { color: #5c6370; }
.code-tabs-content code :deep(.number) { color: #d19a66; }
</style>
