@extends('layouts.marketing')

@section('title', 'Contact Us - CallMeLater')
@section('description', 'Have a question or need help? Contact the CallMeLater team. We\'d love to hear from you.')

@section('styles')
<style>
    .contact-hero {
        padding: 4rem 0 2rem;
        background-color: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }
</style>
@endsection

@section('content')
<div class="contact-page">
    <section class="contact-hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 text-center">
                    <h1 class="display-5 fw-bold mb-3">Contact Us</h1>
                    <p class="lead text-muted">Have a question or need help? We'd love to hear from you.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card card-cml">
                        <div class="card-body p-4">
                            <!-- Success message -->
                            <div id="contact-success" class="text-center py-4" style="display: none;">
                                <div class="mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                        <polyline points="22 4 12 14.01 9 11.01"/>
                                    </svg>
                                </div>
                                <h4>Message Sent!</h4>
                                <p class="text-muted">Thanks for reaching out. We'll get back to you as soon as possible.</p>
                                <button class="btn btn-outline-secondary" onclick="resetContactForm()">Send Another Message</button>
                            </div>

                            <!-- Contact form -->
                            <form id="contact-form" method="POST" action="/api/contact">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        name="name"
                                        required
                                        placeholder="Your name"
                                    >
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input
                                        type="email"
                                        class="form-control"
                                        name="email"
                                        required
                                        placeholder="you@example.com"
                                    >
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Subject</label>
                                    <select class="form-select" name="subject" required>
                                        <option value="">Select a topic...</option>
                                        <option value="general">General Inquiry</option>
                                        <option value="support">Technical Support</option>
                                        <option value="billing">Billing Question</option>
                                        <option value="enterprise">Enterprise Sales</option>
                                        <option value="feedback">Feedback</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Message</label>
                                    <textarea
                                        class="form-control"
                                        name="message"
                                        rows="5"
                                        required
                                        placeholder="How can we help?"
                                    ></textarea>
                                </div>

                                <div id="contact-error" class="alert alert-danger mb-3" style="display: none;"></div>

                                <button type="submit" class="btn btn-cml-primary w-100" id="contact-submit">
                                    Send Message
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('contact-form');
        const submitBtn = document.getElementById('contact-submit');
        const errorDiv = document.getElementById('contact-error');
        const successDiv = document.getElementById('contact-success');

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
            errorDiv.style.display = 'none';

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('/api/contact', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(data)
                });

                if (!response.ok) {
                    const result = await response.json();
                    throw new Error(result.message || 'Failed to send message. Please try again.');
                }

                // Success
                form.style.display = 'none';
                successDiv.style.display = 'block';

            } catch (error) {
                errorDiv.textContent = error.message;
                errorDiv.style.display = 'block';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Message';
            }
        });
    });

    function resetContactForm() {
        const form = document.getElementById('contact-form');
        const successDiv = document.getElementById('contact-success');

        form.reset();
        form.style.display = 'block';
        successDiv.style.display = 'none';
    }
</script>
@endsection
