<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respond - {{ config('app.name') }}</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .logo img {
            width: 32px;
            height: 32px;
        }
        .logo .green {
            color: #22C55E;
        }
        h1 {
            font-size: 20px;
            margin: 0 0 8px;
            color: #111827;
        }
        .description {
            color: #6b7280;
            margin: 0 0 32px;
            font-size: 14px;
        }
        .buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn {
            display: block;
            padding: 16px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.15s ease;
        }
        .btn-confirm {
            background-color: #22c55e;
            color: white;
        }
        .btn-confirm:hover {
            background-color: #16a34a;
        }
        .btn-decline {
            background-color: #ef4444;
            color: white;
        }
        .btn-decline:hover {
            background-color: #dc2626;
        }
        .btn-snooze {
            background-color: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .btn-snooze:hover {
            background-color: #e5e7eb;
        }
        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="/images/callmelater-logo.svg" alt="">
            CallMe<span class="green">Later</span>
        </div>

        <h1>{{ $action->name }}</h1>
        @if($action->description)
            <p class="description">{{ $action->description }}</p>
        @else
            <p class="description">Please respond to this reminder.</p>
        @endif

        <div class="buttons">
            <a href="/respond?token={{ $token }}&response=confirm" class="btn btn-confirm">
                Confirm
            </a>
            <a href="/respond?token={{ $token }}&response=decline" class="btn btn-decline">
                Decline
            </a>
            @if($canSnooze)
                <a href="/respond?token={{ $token }}&response=snooze&preset=1h" class="btn btn-snooze">
                    Snooze 1 hour
                </a>
            @endif
        </div>

        <div class="footer">
            Powered by CallMeLater
        </div>
    </div>
</body>
</html>
