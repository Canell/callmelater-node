<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CallMeLater - Schedule Future Actions')</title>
    <meta name="description" content="@yield('description', 'Schedule durable HTTP webhooks and human reminders. Reliable scheduled actions for developers. No cron jobs. No infrastructure.')">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --cml-green: #22C55E;
            --cml-green-dark: #16a34a;
            --cml-green-light: #dcfce7;
            --cml-gray-50: #f9fafb;
            --cml-gray-100: #f3f4f6;
            --cml-gray-200: #e5e7eb;
            --cml-gray-300: #d1d5db;
            --cml-gray-400: #9ca3af;
            --cml-gray-500: #6b7280;
            --cml-gray-600: #4b5563;
            --cml-gray-700: #374151;
            --cml-gray-800: #1f2937;
            --cml-gray-900: #111827;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--cml-gray-700);
        }

        /* Navbar - exact copy from App.vue */
        .navbar-cml {
            background-color: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 0.75rem 0;
        }

        .navbar-cml .navbar-brand {
            color: #111827;
            font-size: 1.125rem;
            text-decoration: none;
        }

        .navbar-cml .nav-link {
            color: #6b7280;
            font-size: 0.9375rem;
            padding: 0.5rem 1rem;
        }

        .navbar-cml .nav-link:hover {
            color: #111827;
        }

        .navbar-cml .nav-link.active {
            color: #22C55E;
        }

        /* Primary Button */
        .btn-cml-primary {
            background-color: #22C55E;
            border-color: #22C55E;
            color: white;
            font-weight: 500;
        }

        .btn-cml-primary:hover {
            background-color: #16a34a;
            border-color: #16a34a;
            color: white;
        }

        .btn-cml-primary:disabled {
            background-color: #86efac;
            border-color: #86efac;
        }

        /* Outline Button */
        .btn-outline-cml {
            color: #22C55E;
            border-color: #22C55E;
            font-weight: 500;
        }

        .btn-outline-cml:hover {
            background-color: #22C55E;
            border-color: #22C55E;
            color: white;
        }

        /* Footer - exact copy from App.vue */
        .footer-cml {
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 3rem 0 2rem;
            margin-top: auto;
        }

        .footer-heading {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: #6b7280;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .footer-links a:hover {
            color: #22C55E;
        }

        .footer-divider {
            border-color: #e5e7eb;
            margin: 2rem 0 1.5rem;
        }

        /* Make the page full height */
        html, body {
            height: 100%;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        main {
            flex: 1;
        }

        /* Add top padding to all pages except home */
        main.with-padding {
            padding-top: 3rem;
        }

        /* Card styles */
        .card-cml {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
        }

        /* Code Tabs Component - exact copy from CodeTabs.vue */
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

        .code-panel {
            display: none;
        }

        .code-panel.active {
            display: block;
        }

        /* Scroll indicator */
        .scroll-indicator {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            display: none;
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

        .scroll-indicator.visible {
            display: flex;
        }

        .scroll-indicator:hover {
            color: #4ade80;
        }

        .scroll-indicator svg {
            animation: bounce 1.5s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(3px); }
        }

        @yield('styles')
    </style>
</head>
<body>
    <!-- Top Navigation - exact copy from App.vue -->
    <nav class="navbar navbar-expand-lg navbar-cml">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand d-flex align-items-center" href="/">
                <span class="logo-icon me-2">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" stroke="#22C55E" stroke-width="2"/>
                        <path d="M12 6v6l4 2" stroke="#22C55E" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="fw-semibold">CallMeLater</span>
            </a>

            <!-- Mobile toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Nav links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Left side links -->
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link @if(request()->is('use-cases')) active @endif" href="/use-cases">Use Cases</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(request()->is('pricing')) active @endif" href="/pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://docs.{{ config('app.domain', 'callmelater.io') }}">Docs</a>
                    </li>
                </ul>

                <!-- Right side links - Guest (shown by default, hidden when authenticated) -->
                <ul class="navbar-nav" id="nav-guest">
                    <li class="nav-item">
                        <a class="nav-link" href="/login">Log In</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-cml-primary btn-sm ms-2" href="/register">Sign Up</a>
                    </li>
                </ul>

                <!-- Right side links - Authenticated (hidden by default, shown when authenticated) -->
                <ul class="navbar-nav" id="nav-auth" style="display: none;">
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard">Dashboard</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Account
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/settings">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" id="nav-logout">Log out</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <main @unless(request()->is('/')) class="with-padding" @endunless>
        @yield('content')
    </main>

    <!-- Footer - exact copy from App.vue -->
    <footer class="footer-cml">
        <div class="container">
            <div class="row">
                <div class="col-12 col-md-4 mb-4 mb-md-0">
                    <div class="d-flex align-items-center mb-3">
                        <span class="logo-icon me-2">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="#22C55E" stroke-width="2"/>
                                <path d="M12 6v6l4 2" stroke="#22C55E" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="fw-semibold">CallMeLater</span>
                    </div>
                    <p class="text-muted small mb-0">
                        Reliable scheduled actions for developers.<br>
                        Never miss a webhook again.
                    </p>
                </div>
                <div class="col-6 col-md-2">
                    <h6 class="footer-heading">Product</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="/use-cases">Use Cases</a></li>
                        <li><a href="/pricing">Pricing</a></li>
                        <li><a href="https://docs.{{ config('app.domain', 'callmelater.io') }}">Docs</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md-2">
                    <h6 class="footer-heading">Resources</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="/status">Status</a></li>
                        <li><a href="/contact">Contact</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md-2">
                    <h6 class="footer-heading">Legal</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="/terms">Terms</a></li>
                        <li><a href="/privacy">Privacy</a></li>
                        <li><a href="/cookies">Cookies</a></li>
                    </ul>
                </div>
            </div>
            <hr class="footer-divider">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <p class="text-muted small mb-2 mb-md-0">
                    &copy; {{ date('Y') }} CallMeLater. All rights reserved.
                </p>
                <p class="text-muted small mb-0">
                    Made with care in Rixensart, Belgium.
                </p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Auth-aware Navigation -->
    <script>
        (function() {
            // Check if user is authenticated via localStorage token
            const isAuthenticated = localStorage.getItem('token');
            const navGuest = document.getElementById('nav-guest');
            const navAuth = document.getElementById('nav-auth');

            if (isAuthenticated) {
                // Show authenticated nav, hide guest nav
                if (navGuest) navGuest.style.display = 'none';
                if (navAuth) navAuth.style.display = 'flex';
            }

            // Handle logout
            const logoutBtn = document.getElementById('nav-logout');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', async function(e) {
                    e.preventDefault();

                    try {
                        // Call logout API
                        await fetch('/api/logout', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-XSRF-TOKEN': decodeURIComponent(
                                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''
                                )
                            }
                        });
                    } catch (err) {
                        // Ignore errors - we'll clear local state anyway
                    }

                    // Clear local auth state
                    localStorage.removeItem('token');
                    localStorage.removeItem('userTimezone');

                    // Redirect to home
                    window.location.href = '/';
                });
            }
        })();
    </script>

    <!-- Code Tabs JavaScript -->
    <script>
        // Code Tabs functionality - mimics CodeTabs.vue behavior
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.code-tabs').forEach(function(container) {
                const tabs = container.querySelectorAll('.code-tab');
                const panels = container.querySelectorAll('.code-panel');
                const copyBtn = container.querySelector('.copy-btn');
                const codeContent = container.querySelector('.code-tabs-content');
                const scrollIndicator = container.querySelector('.scroll-indicator');

                // Restore preference from localStorage
                const savedPreference = localStorage.getItem('preferredCodeLanguage');
                if (savedPreference) {
                    const savedTab = container.querySelector(`.code-tab[data-lang="${savedPreference}"]`);
                    if (savedTab) {
                        tabs.forEach(t => t.classList.remove('active'));
                        panels.forEach(p => p.classList.remove('active'));
                        savedTab.classList.add('active');
                        const savedPanel = container.querySelector(`.code-panel[data-lang="${savedPreference}"]`);
                        if (savedPanel) savedPanel.classList.add('active');
                    }
                }

                // Tab click handler
                tabs.forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        const lang = this.dataset.lang;

                        // Update active states
                        tabs.forEach(t => t.classList.remove('active'));
                        panels.forEach(p => p.classList.remove('active'));

                        this.classList.add('active');
                        const panel = container.querySelector(`.code-panel[data-lang="${lang}"]`);
                        if (panel) panel.classList.add('active');

                        // Save preference
                        localStorage.setItem('preferredCodeLanguage', lang);

                        // Reset scroll
                        if (codeContent) {
                            codeContent.scrollTop = 0;
                            checkScroll();
                        }
                    });
                });

                // Copy button
                if (copyBtn) {
                    copyBtn.addEventListener('click', function() {
                        const activePanel = container.querySelector('.code-panel.active code');
                        if (activePanel) {
                            navigator.clipboard.writeText(activePanel.textContent).then(function() {
                                const icon = copyBtn.querySelector('svg');
                                const originalHTML = copyBtn.innerHTML;
                                copyBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                                setTimeout(function() {
                                    copyBtn.innerHTML = originalHTML;
                                }, 2000);
                            });
                        }
                    });
                }

                // Scroll indicator
                function checkScroll() {
                    if (codeContent && scrollIndicator) {
                        const threshold = 20;
                        const canScrollDown = codeContent.scrollHeight > codeContent.clientHeight &&
                                             (codeContent.scrollTop + codeContent.clientHeight) < (codeContent.scrollHeight - threshold);
                        scrollIndicator.classList.toggle('visible', canScrollDown);
                    }
                }

                if (codeContent) {
                    codeContent.addEventListener('scroll', checkScroll);
                    checkScroll();
                }

                if (scrollIndicator) {
                    scrollIndicator.addEventListener('click', function() {
                        if (codeContent) {
                            codeContent.scrollBy({ top: 100, behavior: 'smooth' });
                        }
                    });
                }
            });
        });
    </script>

    @yield('scripts')
</body>
</html>
