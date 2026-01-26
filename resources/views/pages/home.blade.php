@extends('layouts.marketing')

@section('title', 'CallMeLater - Schedule Future Actions You Can Rely On')
@section('description', 'Trigger HTTP webhooks or send human confirmations at any time in the future. Durable, retryable, and transparent. No cron jobs. No infrastructure.')

@section('styles')
<style>
    /* Hero Animation Component - exact copy from HeroAnimation.vue */
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

    .timeline-bg {
        stroke: #e5e7eb;
        stroke-width: 3;
        stroke-linecap: round;
    }

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

    .dot {
        fill: url(#dotGradient);
        animation: move-dot 14s infinite ease-in-out;
    }

    .dot-pulse {
        fill: url(#pulseGradient);
        animation: move-dot 14s infinite ease-in-out, pulse 1.2s infinite ease-out;
    }

    .sand {
        animation: sand-fall 14s infinite;
    }

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

    @media (max-width: 768px) {
        .animation-section {
            display: none;
        }
    }

    /* Animation Section */
    .animation-section {
        padding: 2rem 0 3rem;
        background: #fff;
        border-bottom: 1px solid #f3f4f6;
    }

    /* Hero Section - exact copy from Home.vue */
    .hero-section {
        padding: 4rem 0 5rem;
        background: linear-gradient(180deg, #f9fafb 0%, #fff 100%);
    }

    .hero-title {
        font-size: 2.75rem;
        font-weight: 700;
        line-height: 1.2;
        color: #111827;
        margin-bottom: 1.5rem;
    }

    .text-green {
        color: #22C55E;
    }

    .hero-subtitle {
        font-size: 1.125rem;
        color: #6b7280;
        line-height: 1.7;
        margin-bottom: 2rem;
    }

    .hero-cta {
        margin-bottom: 1rem;
    }

    .hero-note {
        font-size: 0.875rem;
        color: #9ca3af;
    }

    .hero-code-wrapper {
        margin-top: 1rem;
    }

    .code-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.75rem;
    }

    /* Code Block (for JSON examples) */
    .code-block {
        background: #1f2937;
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .code-block pre {
        margin: 0;
        padding: 1.25rem;
        overflow-x: auto;
    }

    .code-block code {
        color: #e5e7eb;
        font-family: 'Fira Code', Monaco, 'Cascadia Code', Consolas, monospace;
        font-size: 0.8125rem;
        line-height: 1.6;
    }

    /* Sections */
    .section-light {
        padding: 5rem 0;
        background: #f9fafb;
    }

    .section-white {
        padding: 5rem 0;
        background: #fff;
    }

    .section-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 1rem;
    }

    .section-subtitle {
        font-size: 1.125rem;
        color: #6b7280;
        max-width: 600px;
        margin: 0 auto;
    }

    /* Feature Cards */
    .feature-card {
        padding: 1.5rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        height: 100%;
        transition: box-shadow 0.2s;
    }

    .feature-card:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .feature-icon {
        width: 48px;
        height: 48px;
        background: #ecfdf5;
        color: #22c55e;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }

    .feature-card h5 {
        font-size: 1rem;
        font-weight: 600;
        color: #111827;
        margin-bottom: 0.5rem;
    }

    .feature-card p {
        font-size: 0.875rem;
        color: #6b7280;
        margin-bottom: 0;
        line-height: 1.6;
    }

    /* Feature Sections */
    .feature-label {
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 600;
        color: #22c55e;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.75rem;
    }

    .feature-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 1rem;
    }

    .feature-description {
        font-size: 1rem;
        color: #6b7280;
        line-height: 1.7;
        margin-bottom: 1.5rem;
    }

    .feature-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .feature-list li {
        position: relative;
        padding-left: 1.5rem;
        margin-bottom: 0.5rem;
        font-size: 0.9375rem;
        color: #374151;
    }

    .feature-list li::before {
        content: '\2713';
        position: absolute;
        left: 0;
        color: #22c55e;
        font-weight: 600;
    }

    /* Use Case Cards */
    .use-case-card {
        padding: 1.25rem;
        background: #f9fafb;
        border-radius: 0.5rem;
        height: 100%;
    }

    .use-case-card h5 {
        font-size: 1rem;
        font-weight: 600;
        color: #111827;
        margin-bottom: 0.5rem;
    }

    .use-case-card p {
        font-size: 0.875rem;
        color: #6b7280;
        margin-bottom: 0;
    }

    /* CTA Section */
    .cta-section {
        padding: 5rem 0;
        background: #22c55e;
    }

    .cta-title {
        font-size: 2rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 1rem;
    }

    .cta-subtitle {
        font-size: 1.125rem;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 2rem;
    }

    .cta-buttons .btn-light {
        background: #fff;
        color: #22c55e;
        border: none;
    }

    .cta-buttons .btn-light:hover {
        background: #f9fafb;
    }

    .cta-buttons .btn-outline-light {
        border-color: rgba(255, 255, 255, 0.5);
    }

    .cta-buttons .btn-outline-light:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: #fff;
    }

    /* Responsive */
    @media (max-width: 991px) {
        .hero-title {
            font-size: 2rem;
        }

        .code-block {
            margin-top: 2rem;
        }
    }
</style>
@endsection

@section('content')
<div class="home-page">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title">
                        Schedule future actions<br>
                        <span class="text-green">you can actually rely on</span>
                    </h1>
                    <p class="hero-subtitle">
                        Trigger HTTP webhooks or send human confirmations at any time in the future.
                        Durable, retryable, and transparent. No cron jobs. No infrastructure.
                    </p>
                    <div class="hero-cta">
                        <a href="/register" class="btn btn-cml-primary btn-lg me-3">
                            Start Free
                        </a>
                        <a href="https://docs.{{ config('app.domain', 'callmelater.io') }}" class="btn btn-outline-secondary btn-lg">
                            Read the Docs
                        </a>
                    </div>
                    <p class="hero-note">
                        Free tier includes 100 actions/month. No credit card required.
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="hero-code-wrapper">
                        <div class="code-label">Create a scheduled action</div>
                        @include('components.code-tabs', ['examples' => 'createHttpAction'])
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Animation -->
    <section class="animation-section">
        <div class="container">
            @include('components.hero-animation')
        </div>
    </section>

    <!-- Problem Statement -->
    <section class="section-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h2 class="section-title">"Do this later" is where things break</h2>
                    <p class="section-subtitle">
                        You need to call an API in three days, clean up data after a trial ends,
                        or remind someone to approve a deployment. And then: the server restarts,
                        a job fails silently, the reminder is ignored. No one knows what happened.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Grid -->
    <section class="section-white">
        <div class="container">
            <h2 class="section-title text-center mb-5">Built for reliability</h2>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <h5>Automatic Retries</h5>
                        <p>Exponential backoff when things fail. Configure attempts and timing to match your needs.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                            </svg>
                        </div>
                        <h5>Durable Storage</h5>
                        <p>Actions survive restarts and crashes. Your scheduled work is never lost.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                        </div>
                        <h5>Full Audit Trail</h5>
                        <p>See every attempt, response code, and timing. Debug with confidence.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h5>Secure by Default</h5>
                        <p>HMAC signatures, SSRF protection, and encrypted storage. Ship with confidence.</p>
                    </div>
                </div>
            </div>
            <div class="row g-4 mt-2">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                        </div>
                        <h5>Coordination Keys</h5>
                        <p>Group related actions. Replace old deployments, skip duplicates, or chain workflows.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <h5>Team Reminders</h5>
                        <p>Send to multiple recipients. First response or all required. Built-in escalation.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h5>Timezone Aware</h5>
                        <p>Schedule in any timezone. Presets like "tomorrow" or "next Monday" just work.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h5>Manual Retry</h5>
                        <p>Failed actions can be retried with one click. No data loss, no re-creation needed.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                            </svg>
                        </div>
                        <h5>Action Templates</h5>
                        <p>Create reusable configurations with unique URLs. Trigger from CI/CD without API keys.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Two Action Types -->
    <section class="section-light">
        <div class="container">
            <div class="row align-items-center mb-5 pb-4">
                <div class="col-lg-5">
                    <span class="feature-label">HTTP Webhooks</span>
                    <h3 class="feature-title">Trigger any API at the right time</h3>
                    <p class="feature-description">
                        Schedule a POST, GET, or any HTTP method to fire at a specific time.
                        Perfect for trial expirations, follow-ups, cleanup jobs, or any delayed workflow.
                    </p>
                    <ul class="feature-list">
                        <li>Configurable retry strategy</li>
                        <li>Custom headers and body</li>
                        <li>Webhook signature verification</li>
                        <li>Idempotency key support</li>
                    </ul>
                </div>
                <div class="col-lg-7">
                    <div class="code-block">
                        <pre><code>{
  "name": "Expire trial subscription",
  "intent": { "delay": "14d" },
  "request": {
    "method": "POST",
    "url": "https://api.example.com/subscriptions/expire",
    "headers": { "X-API-Key": "..." },
    "body": { "user_id": 12345 }
  },
  "max_attempts": 5,
  "retry_strategy": "exponential"
}</code></pre>
                    </div>
                </div>
            </div>

            <div class="row align-items-center flex-lg-row-reverse">
                <div class="col-lg-5">
                    <span class="feature-label">Human Reminders</span>
                    <h3 class="feature-title">Get confirmation before acting</h3>
                    <p class="feature-description">
                        Sometimes a human needs to approve first. Send reminders via email or SMS
                        with one-click Yes/No/Snooze buttons. No account needed to respond.
                    </p>
                    <ul class="feature-list">
                        <li>Email and SMS delivery</li>
                        <li>One-click response buttons</li>
                        <li>Configurable snooze limits</li>
                        <li>Automatic escalation</li>
                    </ul>
                </div>
                <div class="col-lg-7">
                    <div class="code-block">
                        <pre><code>{
  "mode": "gated",
  "name": "Approve production deploy",
  "intent": { "delay": "1h" },
  "gate": {
    "message": "Ready to deploy v2.1 to production?",
    "recipients": ["ops@example.com", "+1234567890"],
    "channels": ["email", "sms"],
    "confirmation_mode": "first_response",
    "max_snoozes": 3
  }
}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Integrations / Use Cases -->
    <section class="section-white">
        <div class="container">
            <h2 class="section-title text-center mb-2">Common use cases</h2>
            <p class="section-subtitle text-center mb-5">Replace fragile cron jobs and manual reminders</p>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="use-case-card">
                        <h5>Trial Expirations</h5>
                        <p>Automatically trigger downgrade logic when free trials end.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="use-case-card">
                        <h5>Follow-up Sequences</h5>
                        <p>Send onboarding emails or check-ins at the right intervals.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="use-case-card">
                        <h5>Deployment Approvals</h5>
                        <p>Get human sign-off before releases go live.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="use-case-card">
                        <h5>Data Cleanup</h5>
                        <p>Schedule deletion of temporary data after retention periods.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="use-case-card">
                        <h5>Invoice Reminders</h5>
                        <p>Ping customers about upcoming or overdue payments.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="use-case-card">
                        <h5>Maintenance Windows</h5>
                        <p>Trigger system maintenance at off-peak hours.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container text-center">
            <h2 class="cta-title">Ready to make "later" reliable?</h2>
            <p class="cta-subtitle">Start scheduling actions in under a minute. Free tier included.</p>
            <div class="cta-buttons">
                <a href="/register" class="btn btn-light btn-lg me-3">
                    Get Started Free
                </a>
                <a href="/pricing" class="btn btn-outline-light btn-lg">
                    View Pricing
                </a>
            </div>
        </div>
    </section>
</div>
@endsection
