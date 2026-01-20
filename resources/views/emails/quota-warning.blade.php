<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quota Warning</title>
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
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 24px;
            text-align: center;
        }
        .alert-banner span {
            color: #b45309;
            font-weight: 600;
            font-size: 14px;
        }
        h1 {
            margin: 0 0 16px;
            font-size: 20px;
            color: #111827;
        }
        p {
            margin: 0 0 16px;
            color: #4b5563;
        }
        .usage-card {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
        }
        .usage-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .usage-item:last-child {
            border-bottom: none;
        }
        .usage-label {
            font-weight: 500;
            color: #374151;
        }
        .usage-value {
            font-weight: 600;
        }
        .usage-value.warning {
            color: #b45309;
        }
        .usage-value.ok {
            color: #059669;
        }
        .progress-bar {
            height: 8px;
            background-color: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
        }
        .progress-bar-fill.warning {
            background-color: #f59e0b;
        }
        .progress-bar-fill.ok {
            background-color: #10b981;
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
            <span>Approaching Plan Limits</span>
        </div>

        <h1>Hi there,</h1>

        <p>Your {{ config('app.name') }} account <strong>{{ $account->name }}</strong> is approaching its plan limits. Consider upgrading to avoid service interruptions.</p>

        <div class="usage-card">
            @if($usage['actions']['limit'] > 0)
            <div class="usage-item">
                <div>
                    <div class="usage-label">Actions This Month</div>
                    <div class="progress-bar">
                        <div class="progress-bar-fill {{ $usage['actions']['percentage'] >= 80 ? 'warning' : 'ok' }}" style="width: {{ min(100, $usage['actions']['percentage']) }}%"></div>
                    </div>
                </div>
                <div class="usage-value {{ $usage['actions']['percentage'] >= 80 ? 'warning' : 'ok' }}">
                    {{ number_format($usage['actions']['used']) }} / {{ number_format($usage['actions']['limit']) }}
                </div>
            </div>
            @endif

            @if($usage['sms']['limit'] > 0)
            <div class="usage-item">
                <div>
                    <div class="usage-label">SMS Sent</div>
                    <div class="progress-bar">
                        <div class="progress-bar-fill {{ $usage['sms']['percentage'] >= 80 ? 'warning' : 'ok' }}" style="width: {{ min(100, $usage['sms']['percentage']) }}%"></div>
                    </div>
                </div>
                <div class="usage-value {{ $usage['sms']['percentage'] >= 80 ? 'warning' : 'ok' }}">
                    {{ $usage['sms']['used'] }} / {{ $usage['sms']['limit'] }}
                </div>
            </div>
            @endif
        </div>

        <p>When you hit your limit, new action creation will be blocked until the next billing period or until you upgrade your plan.</p>

        <a href="{{ $settingsUrl }}" class="btn">View Usage & Upgrade</a>

        <div class="footer">
            <p>This notification was sent by {{ config('app.name') }}.</p>
            <p>You'll only receive this warning once per month.</p>
        </div>
    </div>
</body>
</html>
