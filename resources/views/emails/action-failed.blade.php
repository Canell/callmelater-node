<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Action Failed</title>
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
        .alert-banner {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 24px;
            text-align: center;
        }
        .alert-banner span {
            color: #dc2626;
            font-weight: 600;
            font-size: 14px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 20px;
            color: #111827;
        }
        .reason {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 14px;
            color: #dc2626;
        }
        .details {
            font-size: 14px;
            color: #6b7280;
            margin: 16px 0;
        }
        .details dt {
            font-weight: 600;
            color: #374151;
        }
        .details dd {
            margin: 0 0 12px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #111827;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            margin-top: 16px;
        }
        .footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="alert-banner">
            <span>Action Failed</span>
        </div>

        <h1>{{ $action->name }}</h1>

        @if($action->failure_reason)
        <div class="reason">{{ $action->failure_reason }}</div>
        @endif

        <dl class="details">
            <dt>Type</dt>
            <dd>{{ $action->type === 'http' ? 'HTTP Webhook' : 'Reminder' }}</dd>

            @if($action->type === 'http' && $action->http_request)
            <dt>URL</dt>
            <dd>{{ $action->http_request['method'] ?? 'POST' }} {{ $action->http_request['url'] ?? 'N/A' }}</dd>
            @endif

            <dt>Attempts</dt>
            <dd>{{ $action->attempt_count }} / {{ $action->max_attempts }}</dd>

            <dt>Scheduled for</dt>
            <dd>{{ $action->execute_at_utc?->format('M j, Y \a\t g:i A') }} UTC</dd>
        </dl>

        <a href="{{ $actionUrl }}" class="btn">View Details</a>

        <div class="footer">
            <p>This notification was sent by {{ config('app.name') }}.</p>
        </div>
    </div>
</body>
</html>
