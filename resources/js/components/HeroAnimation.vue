<template>
  <div class="animation-wrapper">
    <svg
      viewBox="0 0 800 200"
      class="callmelater-animation"
      xmlns="http://www.w3.org/2000/svg"
    >
      <!-- Background glow effects -->
      <defs>
        <filter id="glow" x="-50%" y="-50%" width="200%" height="200%">
          <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
          <feMerge>
            <feMergeNode in="coloredBlur"/>
            <feMergeNode in="SourceGraphic"/>
          </feMerge>
        </filter>
        <linearGradient id="dotGradient" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#22C55E"/>
          <stop offset="100%" stop-color="#16a34a"/>
        </linearGradient>
        <linearGradient id="pulseGradient" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#22C55E" stop-opacity="0.4"/>
          <stop offset="100%" stop-color="#22C55E" stop-opacity="0"/>
        </linearGradient>
      </defs>

      <!-- Timeline base -->
      <line x1="80" y1="110" x2="720" y2="110" class="timeline-bg" />

      <!-- Step 1: Schedule -->
      <g class="step step-1">
        <circle cx="160" cy="110" r="28" class="step-bg" />
        <circle cx="160" cy="110" r="22" class="step-circle" />
        <!-- Clock icon -->
        <g class="step-icon" transform="translate(160, 110)">
          <circle cx="0" cy="0" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/>
          <line x1="0" y1="0" x2="0" y2="-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          <line x1="0" y1="0" x2="4" y2="2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </g>
        <text x="160" y="158" class="step-label">Schedule</text>
        <text x="160" y="174" class="step-sublabel">API call received</text>
      </g>

      <!-- Connector 1-2 -->
      <line x1="188" y1="110" x2="292" y2="110" class="connector connector-1" />

      <!-- Step 2: Wait -->
      <g class="step step-2">
        <circle cx="320" cy="110" r="28" class="step-bg" />
        <circle cx="320" cy="110" r="22" class="step-circle" />
        <!-- Hourglass icon -->
        <g class="step-icon" transform="translate(320, 110)">
          <path d="M-6 -10 L6 -10 L6 -8 L2 -2 L2 2 L6 8 L6 10 L-6 10 L-6 8 L-2 2 L-2 -2 L-6 -8 Z"
                fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
          <circle cx="0" cy="6" r="2" fill="currentColor" class="sand"/>
        </g>
        <text x="320" y="158" class="step-label">Wait</text>
        <text x="320" y="174" class="step-sublabel">Safely stored</text>
      </g>

      <!-- Connector 2-3 -->
      <line x1="348" y1="110" x2="452" y2="110" class="connector connector-2" />

      <!-- Human approval branch (optional path) -->
      <g class="decision-branch">
        <!-- Curved path going up -->
        <path d="M 400 110 Q 400 50, 480 50 Q 560 50, 560 110" class="branch-path" fill="none" />
        <!-- User icon at the top -->
        <circle cx="480" cy="50" r="16" class="branch-circle" />
        <g transform="translate(480, 50)">
          <circle cx="0" cy="-3" r="4" fill="none" stroke="#6366f1" stroke-width="1.5"/>
          <path d="M-6 7 Q0 2 6 7" fill="none" stroke="#6366f1" stroke-width="1.5"/>
        </g>
        <!-- Label with better explanation -->
        <text x="480" y="22" class="branch-label">Optional</text>
        <text x="480" y="78" class="branch-sublabel">Human approval</text>
      </g>

      <!-- Step 3: Trigger -->
      <g class="step step-3">
        <circle cx="480" cy="110" r="28" class="step-bg" />
        <circle cx="480" cy="110" r="22" class="step-circle" />
        <!-- Lightning/trigger icon -->
        <g class="step-icon" transform="translate(480, 110)">
          <path d="M2 -10 L-4 0 L0 0 L-2 10 L6 -2 L2 -2 Z"
                fill="currentColor" stroke="currentColor" stroke-width="0.5"/>
        </g>
        <text x="480" y="158" class="step-label">Trigger</text>
        <text x="480" y="174" class="step-sublabel">Scheduled time reached</text>
      </g>

      <!-- Connector 3-4 -->
      <line x1="508" y1="110" x2="612" y2="110" class="connector connector-3" />

      <!-- Step 4: Execute -->
      <g class="step step-4">
        <circle cx="640" cy="110" r="28" class="step-bg step-bg-final" />
        <circle cx="640" cy="110" r="22" class="step-circle step-circle-final" />
        <!-- Arrow/send icon (before completion) -->
        <g class="step-icon step-icon-initial" transform="translate(640, 110)">
          <path d="M-6 0 L4 0 M0 -4 L4 0 L0 4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </g>
        <!-- Checkmark icon (after completion) -->
        <g class="step-icon step-icon-final" transform="translate(640, 110)">
          <path d="M-6 0 L-2 4 L6 -4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </g>
        <text x="640" y="158" class="step-label step-label-final">Execute</text>
        <text x="640" y="174" class="step-sublabel">Webhook delivered</text>
      </g>

      <!-- Moving dot with pulse -->
      <circle cx="160" cy="110" r="16" class="dot-pulse" />
      <circle cx="160" cy="110" r="8" class="dot" filter="url(#glow)" />

      <!-- Success burst (appears at the end) -->
      <g class="success-burst" transform="translate(640, 110)">
        <circle r="30" class="burst-ring" />
        <circle r="40" class="burst-ring burst-ring-2" />
      </g>
    </svg>
  </div>
</template>

<script setup>
</script>

<style scoped>
.animation-wrapper {
  width: 100%;
  max-width: 900px;
  margin: 0 auto;
  padding: 1rem 0;
}

.callmelater-animation {
  width: 100%;
  height: auto;
}

/* Timeline */
.timeline-bg {
  stroke: #e5e7eb;
  stroke-width: 3;
  stroke-linecap: round;
}

/* Steps */
.step-bg {
  fill: #f3f4f6;
  opacity: 0;
  animation: step-appear 14s infinite;
}

.step-circle {
  fill: white;
  stroke: #e5e7eb;
  stroke-width: 2;
  opacity: 0;
  animation: step-appear 14s infinite;
}

.step-circle-final {
  stroke: #bbf7d0;
}

.step-bg-final {
  fill: #dcfce7;
}

.step-icon {
  color: #9ca3af;
  opacity: 0;
  animation: step-appear 14s infinite;
}

.step-icon-initial {
  color: #9ca3af;
  animation: step-icon-initial 14s infinite;
}

@keyframes step-icon-initial {
  0%, 2% { opacity: 0; }
  5%, 66% { opacity: 1; }
  72%, 100% { opacity: 0; }
}

.step-icon-final {
  color: #22C55E;
  animation: step-appear-final 14s infinite;
}

@keyframes step-appear-final {
  0%, 66% { opacity: 0; }
  72%, 88% { opacity: 1; }
  94%, 100% { opacity: 0; }
}

/* Steps turn green when passed */
.step-1 .step-circle {
  animation: step-appear 14s infinite, step-passed-1 14s infinite;
}

.step-1 .step-icon {
  animation: step-appear 14s infinite, step-icon-passed-1 14s infinite;
}

.step-2 .step-circle {
  animation: step-appear 14s infinite, step-passed-2 14s infinite;
}

.step-2 .step-icon {
  animation: step-appear 14s infinite, step-icon-passed-2 14s infinite;
}

.step-3 .step-circle {
  animation: step-appear 14s infinite, step-passed-3 14s infinite;
}

.step-3 .step-icon {
  animation: step-appear 14s infinite, step-icon-passed-3 14s infinite;
}

@keyframes step-passed-1 {
  0%, 26% { stroke: #e5e7eb; }
  30%, 88% { stroke: #22C55E; }
  94%, 100% { stroke: #e5e7eb; }
}

@keyframes step-icon-passed-1 {
  0%, 26% { color: #9ca3af; }
  30%, 88% { color: #22C55E; }
  94%, 100% { color: #9ca3af; }
}

@keyframes step-passed-2 {
  0%, 46% { stroke: #e5e7eb; }
  50%, 88% { stroke: #22C55E; }
  94%, 100% { stroke: #e5e7eb; }
}

@keyframes step-icon-passed-2 {
  0%, 46% { color: #9ca3af; }
  50%, 88% { color: #22C55E; }
  94%, 100% { color: #9ca3af; }
}

@keyframes step-passed-3 {
  0%, 66% { stroke: #e5e7eb; }
  70%, 88% { stroke: #22C55E; }
  94%, 100% { stroke: #e5e7eb; }
}

@keyframes step-icon-passed-3 {
  0%, 66% { color: #9ca3af; }
  70%, 88% { color: #22C55E; }
  94%, 100% { color: #9ca3af; }
}

.step-label {
  font-size: 13px;
  font-weight: 600;
  fill: #374151;
  text-anchor: middle;
  opacity: 0;
  animation: step-appear 14s infinite;
}

.step-label-final {
  fill: #15803d;
}

.step-sublabel {
  font-size: 10px;
  fill: #9ca3af;
  text-anchor: middle;
  opacity: 0;
  animation: step-appear 14s infinite;
}

/* Connectors */
.connector {
  stroke: #22C55E;
  stroke-width: 3;
  stroke-linecap: round;
  stroke-dasharray: 104;
  stroke-dashoffset: 104;
}

.connector-1 {
  animation: connector-fill-1 14s infinite;
}

.connector-2 {
  animation: connector-fill-2 14s infinite;
}

.connector-3 {
  animation: connector-fill-3 14s infinite;
}

/* Decision Branch */
.branch-path {
  stroke: #a5b4fc;
  stroke-width: 2;
  stroke-dasharray: 6 4;
  opacity: 0;
  animation: branch-appear 14s infinite;
}

.branch-circle {
  fill: white;
  stroke: #a5b4fc;
  stroke-width: 2;
  opacity: 0;
  animation: branch-appear 14s infinite;
}

.branch-label {
  font-size: 11px;
  font-weight: 600;
  fill: #6366f1;
  text-anchor: middle;
  opacity: 0;
  animation: branch-appear 14s infinite;
}

.branch-sublabel {
  font-size: 10px;
  fill: #818cf8;
  text-anchor: middle;
  opacity: 0;
  animation: branch-appear 14s infinite;
}

/* Moving dot */
.dot {
  fill: url(#dotGradient);
  animation: move-dot 14s infinite ease-in-out;
}

.dot-pulse {
  fill: url(#pulseGradient);
  animation: move-dot 14s infinite ease-in-out, pulse 1.2s infinite ease-out;
}

/* Sand animation in hourglass */
.sand {
  animation: sand-fall 14s infinite;
}

/* Success burst */
.burst-ring {
  fill: none;
  stroke: #22C55E;
  stroke-width: 2;
  opacity: 0;
  animation: burst 14s infinite;
}

.burst-ring-2 {
  animation-delay: 0.15s;
}

/* Animations - Total duration 14s */
/* Timeline:
   0-3%: fade in
   3-15%: dot at step 1
   15-30%: dot travels to step 2
   30-45%: dot at step 2, branch appears
   45-55%: dot travels to step 3
   55-65%: dot at step 3
   65-72%: dot travels to step 4
   72-90%: SUCCESS! dot stays at final, burst effect
   90-100%: fade out and reset
*/

@keyframes step-appear {
  0%, 2% { opacity: 0; }
  5%, 88% { opacity: 1; }
  94%, 100% { opacity: 0; }
}

@keyframes connector-fill-1 {
  0%, 18% { stroke-dashoffset: 104; }
  28%, 88% { stroke-dashoffset: 0; }
  94%, 100% { stroke-dashoffset: 104; }
}

@keyframes connector-fill-2 {
  0%, 38% { stroke-dashoffset: 104; }
  48%, 88% { stroke-dashoffset: 0; }
  94%, 100% { stroke-dashoffset: 104; }
}

@keyframes connector-fill-3 {
  0%, 58% { stroke-dashoffset: 104; }
  68%, 88% { stroke-dashoffset: 0; }
  94%, 100% { stroke-dashoffset: 104; }
}

@keyframes branch-appear {
  0%, 28% { opacity: 0; }
  35%, 60% { opacity: 1; }
  68%, 100% { opacity: 0; }
}

@keyframes move-dot {
  0%, 3% {
    transform: translateX(0);
    opacity: 0;
  }
  6% {
    opacity: 1;
  }
  6%, 18% {
    transform: translateX(0);
  }
  28%, 38% {
    transform: translateX(160px);
  }
  48%, 58% {
    transform: translateX(320px);
  }
  68%, 88% {
    transform: translateX(480px);
  }
  92% {
    opacity: 1;
  }
  96%, 100% {
    transform: translateX(480px);
    opacity: 0;
  }
}

@keyframes pulse {
  0% {
    r: 8;
    opacity: 0.6;
  }
  100% {
    r: 24;
    opacity: 0;
  }
}

@keyframes sand-fall {
  0%, 18% { cy: -4; }
  38%, 100% { cy: 6; }
}

@keyframes burst {
  0%, 68% {
    r: 22;
    opacity: 0;
  }
  72% {
    opacity: 0.8;
  }
  85% {
    r: 50;
    opacity: 0;
  }
  100% {
    opacity: 0;
  }
}

/* Step-specific animation delays */
.step-1 .step-bg,
.step-1 .step-circle,
.step-1 .step-icon,
.step-1 .step-label,
.step-1 .step-sublabel {
  animation-delay: 0s;
}

.step-2 .step-bg,
.step-2 .step-circle,
.step-2 .step-icon,
.step-2 .step-label,
.step-2 .step-sublabel {
  animation-delay: 0.2s;
}

.step-3 .step-bg,
.step-3 .step-circle,
.step-3 .step-icon,
.step-3 .step-label,
.step-3 .step-sublabel {
  animation-delay: 0.4s;
}

.step-4 .step-bg,
.step-4 .step-circle,
.step-4 .step-icon,
.step-4 .step-label,
.step-4 .step-sublabel {
  animation-delay: 0.6s;
}

/* Responsive */
@media (max-width: 768px) {
  .animation-wrapper {
    padding: 0.5rem 0;
  }

  .step-sublabel,
  .branch-sublabel {
    display: none;
  }

  .branch-label {
    font-size: 10px;
  }
}
</style>
