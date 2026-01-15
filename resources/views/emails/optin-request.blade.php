<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You've been invited to receive reminders</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #374151;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 32px;
        }
        h1 {
            color: #111827;
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 24px 0;
        }
        p {
            margin: 0 0 16px 0;
        }
        .highlight {
            font-weight: 600;
            color: #111827;
        }
        .section {
            margin: 24px 0;
        }
        .section-title {
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
        }
        .button-group {
            margin: 32px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            margin-right: 12px;
            margin-bottom: 12px;
        }
        .button-primary {
            background-color: #22C55E;
            color: #ffffff !important;
        }
        .button-secondary {
            background-color: #f3f4f6;
            color: #374151 !important;
            border: 1px solid #d1d5db;
        }
        .note {
            color: #6b7280;
            margin-top: 24px;
        }
        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #9ca3af;
        }
        .footer a {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>You've been invited to receive reminders</h1>

            <p>Hi,</p>

            <p><span class="highlight">{{ $senderName }}</span> has scheduled reminders that would be sent to <span class="highlight">{{ $recipientEmail }}</span>.</p>

            <p>To respect your preferences, CallMeLater will only send reminders if you explicitly agree.</p>

            <div class="section">
                <div class="section-title">What would you receive?</div>
                <p>Short reminder emails related to tasks or approvals requested by {{ $senderName }}.</p>
            </div>

            <div class="section">
                <div class="section-title">Your choice</div>
            </div>

            <div class="button-group">
                <a href="{{ $acceptUrl }}" class="button button-primary">Accept reminders</a>
                <a href="{{ $declineUrl }}" class="button button-secondary">Decline</a>
            </div>

            <p class="note">If you decline, you won't receive any reminders from CallMeLater. If you accept now and change your mind later, you can unsubscribe using the link at the bottom of any reminder email.</p>

            <div class="footer">
                <p>You're receiving this message because it was scheduled via <strong>{{ config('app.name') }}</strong> on behalf of <strong>{{ $accountName }}</strong>.</p>
            </div>
        </div>
    </div>
</body>
</html>
