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
    <div class="code-tabs-content" ref="codeContent" @scroll="checkScroll">
      <pre><code :class="'language-' + activeLanguage.syntax">{{ activeCode }}</code></pre>
    </div>
    <!-- Scroll indicator -->
    <transition name="fade">
      <div v-if="canScrollDown" class="scroll-indicator" @click="scrollDown">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
        <span>Scroll for more</span>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';

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
const codeContent = ref(null);
const canScrollDown = ref(false);

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
  // Reset scroll and check if new content is scrollable
  nextTick(() => {
    if (codeContent.value) {
      codeContent.value.scrollTop = 0;
      checkScroll();
    }
  });
}

function copyCode() {
  navigator.clipboard.writeText(activeCode.value).then(() => {
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  });
}

function checkScroll() {
  if (codeContent.value) {
    const el = codeContent.value;
    const threshold = 20; // Small buffer
    canScrollDown.value = el.scrollHeight > el.clientHeight &&
                          (el.scrollTop + el.clientHeight) < (el.scrollHeight - threshold);
  }
}

function scrollDown() {
  if (codeContent.value) {
    codeContent.value.scrollBy({
      top: 100,
      behavior: 'smooth'
    });
  }
}

onMounted(() => {
  // Restore preference
  const savedPreference = localStorage.getItem('preferredCodeLanguage');
  if (savedPreference && props.examples[savedPreference]) {
    activeTab.value = savedPreference;
  }

  // Initial scroll check
  nextTick(() => {
    checkScroll();
  });
});

// Watch for tab changes to recheck scroll
watch(activeTab, () => {
  nextTick(() => {
    checkScroll();
  });
});
</script>

<style scoped>
.code-tabs {
  position: relative;
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
  flex-shrink: 0;
}

.copy-btn:hover {
  color: #e0e0e0;
  background: rgba(255, 255, 255, 0.1);
}

.code-tabs-content {
  padding: 16px;
  height: 280px;
  overflow-y: auto;
  overflow-x: auto;
  scrollbar-width: thin;
  scrollbar-color: #404040 transparent;
}

.code-tabs-content::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.code-tabs-content::-webkit-scrollbar-track {
  background: transparent;
}

.code-tabs-content::-webkit-scrollbar-thumb {
  background: #404040;
  border-radius: 4px;
}

.code-tabs-content::-webkit-scrollbar-thumb:hover {
  background: #505050;
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

/* Scroll indicator */
.scroll-indicator {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px;
  background: linear-gradient(to top, rgba(30, 30, 30, 0.95) 0%, rgba(30, 30, 30, 0.8) 60%, transparent 100%);
  color: #22C55E;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: opacity 0.2s;
}

.scroll-indicator:hover {
  color: #4ade80;
}

.scroll-indicator svg {
  animation: bounce 1.5s infinite;
}

@keyframes bounce {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(3px);
  }
}

/* Fade transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Syntax highlighting (basic) */
.code-tabs-content code :deep(.keyword) { color: #c678dd; }
.code-tabs-content code :deep(.string) { color: #98c379; }
.code-tabs-content code :deep(.comment) { color: #5c6370; }
.code-tabs-content code :deep(.number) { color: #d19a66; }
</style>
