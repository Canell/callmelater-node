<template>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><router-link to="/">Home</router-link></li>
                        <li class="breadcrumb-item active">Webhook Security</li>
                    </ol>
                </nav>

                <h1 class="mb-4">Webhook Security</h1>
                <p class="lead text-muted mb-5">
                    Learn how to securely receive webhook requests from CallMeLater.
                </p>

                <!-- HMAC Signature Section -->
                <section class="mb-5">
                    <h2 id="signature-verification">Signature Verification (Recommended)</h2>
                    <p>
                        Every HTTP request from CallMeLater includes an HMAC-SHA256 signature that you can use to verify the request authenticity. This is the <strong>recommended</strong> approach for securing your webhook endpoints.
                    </p>

                    <h5 class="mt-4">Request Headers</h5>
                    <p>CallMeLater includes these headers with every webhook request:</p>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Header</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>X-CallMeLater-Signature</code></td>
                                    <td>HMAC-SHA256 signature of the request body</td>
                                </tr>
                                <tr>
                                    <td><code>X-CallMeLater-Action-Id</code></td>
                                    <td>Unique identifier of the scheduled action</td>
                                </tr>
                                <tr>
                                    <td><code>X-CallMeLater-Timestamp</code></td>
                                    <td>Unix timestamp when the request was sent</td>
                                </tr>
                                <tr>
                                    <td><code>User-Agent</code></td>
                                    <td><code>CallMeLater/1.0</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h5 class="mt-4">Verifying the Signature</h5>
                    <p>
                        To verify the signature, compute the HMAC-SHA256 of the request body using your webhook secret, then compare it to the <code>X-CallMeLater-Signature</code> header.
                    </p>

                    <div class="card bg-light mb-4">
                        <div class="card-header">
                            <strong>PHP Example</strong>
                        </div>
                        <div class="card-body">
                            <pre class="mb-0"><code>$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_CALLMELATER_SIGNATURE'] ?? '';
$secret = getenv('CALLMELATER_WEBHOOK_SECRET');

$expected = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}</code></pre>
                        </div>
                    </div>

                    <div class="card bg-light mb-4">
                        <div class="card-header">
                            <strong>Node.js Example</strong>
                        </div>
                        <div class="card-body">
                            <pre class="mb-0"><code>const crypto = require('crypto');

function verifySignature(payload, signature, secret) {
    const expected = crypto
        .createHmac('sha256', secret)
        .update(payload)
        .digest('hex');

    return crypto.timingSafeEqual(
        Buffer.from(expected),
        Buffer.from(signature)
    );
}</code></pre>
                        </div>
                    </div>

                    <div class="card bg-light mb-4">
                        <div class="card-header">
                            <strong>Python Example</strong>
                        </div>
                        <div class="card-body">
                            <pre class="mb-0"><code>import hmac
import hashlib

def verify_signature(payload: bytes, signature: str, secret: str) -> bool:
    expected = hmac.new(
        secret.encode(),
        payload,
        hashlib.sha256
    ).hexdigest()

    return hmac.compare_digest(expected, signature)</code></pre>
                        </div>
                    </div>
                </section>

                <!-- IP Allowlisting Section -->
                <section class="mb-5">
                    <h2 id="ip-allowlisting">IP Allowlisting (Optional)</h2>
                    <p>
                        If your endpoint is protected by a firewall, you may need to allow incoming requests from CallMeLater's outbound IP address. This is <strong>optional</strong> — if you don't use IP-based firewall rules, no configuration is required.
                    </p>

                    <div class="alert alert-secondary">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <strong>Outbound IP Address</strong>
                                <p class="mb-0 mt-1">All webhook requests originate from this static IP:</p>
                            </div>
                        </div>
                        <div class="mt-3 d-flex align-items-center gap-3">
                            <template v-if="loading">
                                <span class="text-muted">Loading...</span>
                            </template>
                            <template v-else-if="outboundIp">
                                <code class="fs-5 bg-white px-3 py-2 rounded border">{{ outboundIp }}</code>
                                <button class="btn btn-outline-secondary btn-sm" @click="copyIp">
                                    {{ ipCopied ? 'Copied!' : 'Copy IP' }}
                                </button>
                            </template>
                            <template v-else>
                                <span class="text-muted">Not configured</span>
                            </template>
                        </div>
                    </div>

                    <h5 class="mt-4">Important Notes</h5>
                    <ul>
                        <li>This IP address is <strong>static</strong> and will not change without advance notice</li>
                        <li>We recommend using <a href="#signature-verification">signature verification</a> as your primary security measure</li>
                        <li>IP allowlisting provides an additional layer of defense but should not be your only security control</li>
                    </ul>
                </section>

                <!-- Best Practices Section -->
                <section class="mb-5">
                    <h2 id="best-practices">Best Practices</h2>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title text-success">Do</h5>
                            <ul class="mb-0">
                                <li>Always verify the HMAC signature before processing requests</li>
                                <li>Use constant-time comparison functions to prevent timing attacks</li>
                                <li>Return a 2xx status code quickly, then process asynchronously if needed</li>
                                <li>Log webhook receipts for debugging and auditing</li>
                                <li>Store your webhook secret securely (environment variable, secrets manager)</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title text-danger">Don't</h5>
                            <ul class="mb-0">
                                <li>Don't hardcode your webhook secret in source code</li>
                                <li>Don't rely solely on IP allowlisting for security</li>
                                <li>Don't process webhooks synchronously if they take more than a few seconds</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Troubleshooting Section -->
                <section class="mb-5">
                    <h2 id="troubleshooting">Troubleshooting</h2>

                    <div class="accordion" id="troubleshootingAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                    Webhooks are timing out
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <p>If your webhook endpoint is behind a firewall, ensure you've allowed incoming requests from <code>{{ outboundIp }}</code>.</p>
                                    <p class="mb-0">Also check that your endpoint responds within 30 seconds. For long-running tasks, return a 202 Accepted immediately and process asynchronously.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                    Signature verification fails
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li>Ensure you're using the raw request body (not parsed JSON)</li>
                                        <li>Check that your secret matches the one configured in your CallMeLater settings</li>
                                        <li>Verify you're using SHA256 (not SHA1 or MD5)</li>
                                        <li>Make sure the signature is compared as hex strings</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                    Connection refused errors
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li>Verify your endpoint URL is publicly accessible</li>
                                        <li>Check that your server is running and listening on the correct port</li>
                                        <li>Ensure SSL/TLS is properly configured if using HTTPS</li>
                                        <li>Review firewall rules to allow traffic from <code>{{ outboundIp }}</code></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Need Help -->
                <section class="text-center py-4 border-top">
                    <h5>Need Help?</h5>
                    <p class="text-muted mb-3">
                        If you're still having trouble, check your action's delivery attempts for detailed error messages.
                    </p>
                    <router-link to="/dashboard" class="btn btn-outline-primary">
                        View Dashboard
                    </router-link>
                </section>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'DocsWebhookSecurity',
    data() {
        return {
            outboundIp: null,
            webhookHeaders: [],
            userAgent: null,
            ipCopied: false,
            loading: true,
        };
    },
    mounted() {
        this.loadServerInfo();
    },
    methods: {
        async loadServerInfo() {
            try {
                const response = await axios.get('/api/public/server-info');
                this.outboundIp = response.data.outbound_ip;
                this.webhookHeaders = response.data.webhook_headers;
                this.userAgent = response.data.user_agent;
            } catch (err) {
                console.error('Failed to load server info:', err);
            } finally {
                this.loading = false;
            }
        },
        copyIp() {
            navigator.clipboard.writeText(this.outboundIp);
            this.ipCopied = true;
            setTimeout(() => { this.ipCopied = false; }, 2000);
        },
    },
};
</script>

<style scoped>
pre {
    background: #f8f9fa;
    border-radius: 4px;
    overflow-x: auto;
}

code {
    color: #e83e8c;
}

pre code {
    color: #212529;
}

.accordion-button:not(.collapsed) {
    background-color: #f8f9fa;
    color: inherit;
}
</style>
