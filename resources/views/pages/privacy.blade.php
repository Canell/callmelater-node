@extends('layouts.marketing')

@section('title', 'Privacy Policy - CallMeLater')
@section('description', 'Privacy Policy for CallMeLater. Learn how we collect, use, and protect your personal data in accordance with GDPR.')

@section('styles')
<style>
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
@endsection

@section('content')
<div class="legal-page">
    <section class="legal-hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h1 class="display-5 fw-bold mb-3">Privacy Policy</h1>
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
                        <p class="lead">This Privacy Policy explains how CallMeLater collects, uses, and protects personal data in accordance with the General Data Protection Regulation (GDPR).</p>

                        <hr>

                        <h2>1. Data Controller</h2>
                        <p>CallMeLater is operated by <strong>Canell SRL</strong>, a company registered in Belgium (VAT: BE0778.716.691). For more information about our company, visit <a href="https://canell.be" target="_blank" rel="noopener">canell.be</a>.</p>
                        <p>Canell SRL acts as:</p>
                        <ul>
                            <li><strong>Data Controller</strong> for account and billing data</li>
                            <li><strong>Data Processor</strong> for reminder recipient data submitted by users</li>
                        </ul>
                        <p>Users of CallMeLater are responsible for ensuring they have a lawful basis to process recipient data.</p>

                        <hr>

                        <h2>2. Personal Data We Collect</h2>
                        <p>We may collect and process the following data:</p>
                        <ul>
                            <li>Account information (email address, name)</li>
                            <li>Authentication and security data</li>
                            <li>Scheduled action metadata</li>
                            <li>Reminder recipient email addresses</li>
                            <li>Execution logs and timestamps</li>
                            <li>IP addresses for security and abuse prevention</li>
                        </ul>

                        <hr>

                        <h2>3. How We Use Personal Data</h2>
                        <p>We process personal data to:</p>
                        <ul>
                            <li>Provide and operate the Service</li>
                            <li>Execute scheduled actions and reminders</li>
                            <li>Secure the platform and prevent abuse</li>
                            <li>Manage billing and subscriptions</li>
                            <li>Communicate service-related information</li>
                        </ul>

                        <hr>

                        <h2>4. Legal Bases for Processing (GDPR Art. 6)</h2>
                        <p>We process data based on:</p>
                        <ul>
                            <li><strong>Contract performance</strong> (providing the Service)</li>
                            <li><strong>Legitimate interest</strong> (security, reliability, abuse prevention)</li>
                            <li><strong>Consent</strong>, where required by law</li>
                        </ul>

                        <hr>

                        <h2>5. Data Retention</h2>
                        <p>We retain personal data only for as long as necessary to operate the Service or comply with legal obligations. Logs and execution data are retained for a limited period and may be deleted automatically.</p>

                        <hr>

                        <h2>6. Data Sharing and Subprocessors</h2>
                        <p>We may share data with trusted subprocessors, including:</p>
                        <ul>
                            <li>Email delivery providers (e.g. Postmark)</li>
                            <li>Payment processors (e.g. Stripe)</li>
                            <li>Hosting and infrastructure providers</li>
                        </ul>
                        <p>These providers process data under contractual and GDPR-compliant safeguards.</p>

                        <hr>

                        <h2>7. International Transfers</h2>
                        <p>Where data is transferred outside the EU, appropriate safeguards such as Standard Contractual Clauses (SCCs) are applied.</p>

                        <hr>

                        <h2>8. Data Subject Rights</h2>
                        <p>You have the right to:</p>
                        <ul>
                            <li>Access your personal data</li>
                            <li>Correct inaccurate data</li>
                            <li>Request deletion ("right to be forgotten")</li>
                            <li>Restrict or object to processing</li>
                            <li>Request data portability</li>
                        </ul>
                        <p>Requests can be made by contacting us.</p>

                        <hr>

                        <h2>9. Security Measures</h2>
                        <p>We implement appropriate technical and organizational measures to protect personal data, including access controls, encryption where applicable, and monitoring.</p>

                        <hr>

                        <h2>10. Cookies</h2>
                        <p>CallMeLater uses only strictly necessary cookies required for authentication and security. See the <a href="/cookies">Cookie Notice</a> for details.</p>

                        <hr>

                        <h2>11. Changes to This Policy</h2>
                        <p>We may update this Privacy Policy from time to time. Updates will be posted on this page.</p>

                        <hr>

                        <h2>12. Governing Law</h2>
                        <p>This Privacy Policy is governed by the laws of Belgium and the General Data Protection Regulation (GDPR). You have the right to lodge a complaint with the Belgian Data Protection Authority (Gegevensbeschermingsautoriteit) or your local supervisory authority.</p>

                        <hr>

                        <h2>13. Contact</h2>
                        <p>For privacy-related questions or requests, please <a href="/contact">contact us</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
