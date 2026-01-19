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
