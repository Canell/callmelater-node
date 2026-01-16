<template>
    <div class="legal-page">
        <section class="legal-hero">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8 text-center">
                        <h1 class="display-5 fw-bold mb-3">Terms of Service</h1>
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
                            <p class="lead">These Terms of Service ("Terms") govern your access to and use of the CallMeLater service ("Service"). By creating an account or using the Service, you agree to be bound by these Terms.</p>

                            <hr>

                            <h2>1. About CallMeLater</h2>
                            <p>CallMeLater is a software-as-a-service platform that allows users to schedule future actions, including automated HTTP requests and reminder notifications, for execution at a later time.</p>
                            <p>The Service is operated by <strong>Canell SRL</strong>, a company registered in Belgium (VAT: BE0778.716.691), referred to as "we", "us", or "our" throughout these Terms. For more information about our company, visit <a href="https://canell.be" target="_blank" rel="noopener">canell.be</a>.</p>

                            <hr>

                            <h2>2. Eligibility</h2>
                            <p>You must be at least 18 years old to use the Service. By using CallMeLater, you confirm that you have the legal authority to enter into these Terms.</p>

                            <hr>

                            <h2>3. Description of the Service</h2>
                            <p>CallMeLater enables users to:</p>
                            <ul>
                                <li>Schedule delayed or time-based HTTP requests ("webhooks")</li>
                                <li>Send reminder notifications by email (and optionally other channels)</li>
                                <li>Collect responses to reminders (e.g. confirm, decline, snooze)</li>
                                <li>Trigger follow-up actions based on user responses</li>
                            </ul>
                            <p>The Service operates on a best-effort basis and is not intended for life-critical, emergency, or safety-related use cases.</p>

                            <hr>

                            <h2>4. User Responsibilities</h2>
                            <p>You are solely responsible for:</p>
                            <ul>
                                <li>The content, payloads, and metadata you submit</li>
                                <li>Ensuring you have lawful authority to contact reminder recipients</li>
                                <li>The security and correctness of webhook endpoints you configure</li>
                                <li>Any consequences resulting from actions executed by the Service</li>
                            </ul>
                            <p>You must ensure that your use of the Service complies with all applicable laws and regulations.</p>

                            <hr>

                            <h2>5. Acceptable Use</h2>
                            <p>You may not use CallMeLater to:</p>
                            <ul>
                                <li>Send spam, unsolicited messages, or phishing content</li>
                                <li>Distribute malware or harmful payloads</li>
                                <li>Abuse or disrupt third-party systems</li>
                                <li>Violate privacy, data protection, or anti-spam laws</li>
                                <li>Bypass security or usage limits</li>
                            </ul>
                            <p>We reserve the right to suspend or terminate accounts that violate these rules.</p>

                            <hr>

                            <h2>6. Availability and Reliability</h2>
                            <p>While we strive to provide reliable execution, CallMeLater does not guarantee:</p>
                            <ul>
                                <li>Exact execution timing</li>
                                <li>Successful delivery of HTTP requests</li>
                                <li>Availability without interruption</li>
                            </ul>
                            <p>Delays or failures may occur due to network issues, third-party systems, maintenance, or outages.</p>

                            <hr>

                            <h2>7. Billing and Subscriptions</h2>
                            <p>Paid plans are billed on a recurring basis according to the selected plan. Subscriptions are tied to an account and may include usage limits.</p>
                            <p>Failure to pay may result in service suspension.</p>

                            <hr>

                            <h2>8. Limitation of Liability</h2>
                            <p>To the maximum extent permitted by law:</p>
                            <ul>
                                <li>The Service is provided "as is" and "as available"</li>
                                <li>We are not liable for missed actions, failed executions, data loss, or indirect damages</li>
                                <li>We are not responsible for damages arising from user-configured actions or third-party systems</li>
                            </ul>

                            <hr>

                            <h2>9. Termination</h2>
                            <p>You may stop using the Service at any time. We may suspend or terminate access if these Terms are violated or if required by law.</p>

                            <hr>

                            <h2>10. Changes to These Terms</h2>
                            <p>We may update these Terms from time to time. Continued use of the Service after changes constitutes acceptance of the updated Terms.</p>

                            <hr>

                            <h2>11. Governing Law</h2>
                            <p>These Terms are governed by the laws of Belgium. Any disputes arising from or relating to these Terms shall be subject to the exclusive jurisdiction of the courts of Belgium.</p>

                            <hr>

                            <h2>12. Contact</h2>
                            <p>For questions regarding these Terms, please <router-link to="/contact">contact us</router-link>.</p>
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
