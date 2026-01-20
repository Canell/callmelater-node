<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escalation: {{ $action->name }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .escalation-banner {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 24px;
            text-align: center;
        }
        .escalation-banner span {
            color: #dc2626;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid #eee;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        .message {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin: 24px 0;
            white-space: pre-wrap;
        }
        .buttons {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            margin: 8px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
        }
        .btn-confirm {
            background-color: #22c55e;
            color: #ffffff;
        }
        .btn-decline {
            background-color: #ef4444;
            color: #ffffff;
        }
        .btn-snooze {
            background-color: #6b7280;
            color: #ffffff;
        }
        .footer {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #6b7280;
        }
        @media (max-width: 480px) {
            .btn {
                display: block;
                margin: 8px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="escalation-banner">
            <span>Escalated - No response received</span>
        </div>

        <div class="header">
            <h1>{{ $action->name }}</h1>
        </div>

        <p>This reminder has been escalated to you because the original recipient(s) did not respond in time.</p>

        <div class="message">{{ $action->message }}</div>

        <div class="buttons">
            <a href="{{ $confirmUrl }}" class="btn btn-confirm">Yes, Confirm</a>
            <a href="{{ $declineUrl }}" class="btn btn-decline">No, Decline</a>
            @if($action->canSnooze())
            <a href="{{ $snoozeUrl }}&preset=1h" class="btn btn-snooze">Snooze 1h</a>
            @endif
        </div>

        <p style="text-align: center; color: #6b7280; font-size: 14px;">
            Click one of the buttons above to respond.<br>
            No login required.
        </p>

        <div class="footer">
            <p>
                This email was sent via <strong>{{ config('app.name') }}</strong> on behalf of <strong>{{ $action->account->name ?? 'Unknown' }}</strong> as an escalated reminder.
                @if($action->token_expires_at)
                <br>This link expires on {{ $action->token_expires_at->format('M j, Y \a\t g:i A T') }}.
                @endif
            </p>
            <p style="margin-top: 12px; font-size: 13px;">
                If you believe this message was sent in error, please contact the sender.
            </p>
        </div>
    </div>
</body>
</html>
