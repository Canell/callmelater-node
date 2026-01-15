<template>
    <div class="legal-page">
        <section class="legal-hero">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8 text-center">
                        <h1 class="display-5 fw-bold mb-3">Cookie Notice</h1>
                        <p class="text-muted">Last updated: January 2026</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="legal-content">
                            <p class="lead">CallMeLater uses cookies and similar technologies <strong>only when strictly necessary</strong> to operate the service. We do <strong>not</strong> use cookies for advertising, tracking, or analytics purposes.</p>

                            <hr>

                            <h2>1. What are cookies?</h2>
                            <p>Cookies are small text files stored on your device when you visit a website. They are commonly used to ensure websites function correctly and securely.</p>

                            <hr>

                            <h2>2. Cookies used by CallMeLater</h2>
                            <p>CallMeLater uses <strong>strictly necessary cookies only</strong>, which are required for:</p>
                            <ul>
                                <li>User authentication and login sessions</li>
                                <li>Security (CSRF protection, session integrity)</li>
                                <li>Remembering your preferences during an active session</li>
                            </ul>
                            <p>These cookies are essential for the operation of the service and <strong>cannot be disabled</strong>.</p>

                            <hr>

                            <h2>3. Cookies we do NOT use</h2>
                            <p>CallMeLater does <strong>not</strong> use:</p>
                            <ul>
                                <li>Advertising or marketing cookies</li>
                                <li>Third-party tracking cookies</li>
                                <li>Behavioral profiling</li>
                                <li>Cross-site tracking</li>
                                <li>Social media pixels</li>
                            </ul>
                            <p>We do not sell or share cookie data with advertisers or analytics providers.</p>

                            <hr>

                            <h2>4. Legal basis (GDPR)</h2>
                            <p>Under the General Data Protection Regulation (GDPR), strictly necessary cookies are permitted based on <strong>legitimate interest</strong>, as they are required to provide the service explicitly requested by the user.</p>
                            <p>Because CallMeLater only uses strictly necessary cookies, <strong>explicit consent is not required</strong>.</p>

                            <hr>

                            <h2>5. Third-party services</h2>
                            <p>Some third-party services integrated into CallMeLater (such as payment or email providers) may use cookies when you interact directly with their hosted pages (for example, Stripe Checkout). These cookies are governed by the respective providers' privacy policies.</p>

                            <hr>

                            <h2>6. Managing cookies</h2>
                            <p>You can configure your browser to block or delete cookies. Please note that blocking strictly necessary cookies may prevent CallMeLater from functioning correctly.</p>

                            <hr>

                            <h2>7. Contact</h2>
                            <p>If you have any questions about this Cookie Notice, please contact:</p>
                            <p><strong>Email:</strong> <a href="mailto:privacy@callmelater.io">privacy@callmelater.io</a></p>

                            <hr>

                            <p class="text-muted">This Cookie Notice may be updated from time to time. Changes will be posted on this page.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
</script>

<style scoped>
.legal-hero {
    padding: 4rem 0 2rem;
    background-color: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}

.legal-content {
    line-height: 1.8;
    color: #374151;
}

.legal-content h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
    margin-top: 2rem;
    margin-bottom: 1rem;
}

.legal-content p {
    margin-bottom: 1rem;
}

.legal-content ul {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.legal-content li {
    margin-bottom: 0.5rem;
}

.legal-content hr {
    border-color: #e5e7eb;
    margin: 2rem 0;
}

.legal-content a {
    color: #22C55E;
    text-decoration: none;
}

.legal-content a:hover {
    text-decoration: underline;
}
</style>
