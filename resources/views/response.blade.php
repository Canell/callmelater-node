<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reminder Response - {{ config('app.name') }}</title>
    <style>
        * {
            box-sizing: border-box;
        }
        html, body {
            height: 100%;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 24px 16px;
            background-color: #f5f5f5;
        }
        @media (min-height: 500px) {
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100%;
            }
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
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .logo img {
            width: 32px;
            height: 32px;
        }
        .icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
        }
        .icon-success {
            background-color: #dcfce7;
            color: #22c55e;
        }
        .icon-error {
            background-color: #fee2e2;
            color: #ef4444;
        }
        h1 {
            font-size: 24px;
            margin: 0 0 16px;
        }
        p {
            color: #6b7280;
            margin: 0 0 24px;
        }
        .action-name {
            background-color: #f3f4f6;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 16px 0;
            font-weight: 500;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            margin-top: 16px;
            background-color: #22c55e;
            color: white;
        }
        .btn:hover {
            background-color: #16a34a;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="/images/callmelater-logo.svg" alt="">
            CallMe<span style="color: #22C55E;">Later</span>
        </div>

        @if($error)
            <div class="icon icon-error">✕</div>
            <h1>Oops!</h1>
            <p>{{ $error }}</p>
        @elseif($success)
            <div class="icon icon-success">✓</div>
            <h1>
                @if($response === 'confirm')
                    Confirmed!
                @elseif($response === 'decline')
                    Declined
                @elseif($response === 'snooze')
                    Snoozed
                @endif
            </h1>
            <p>{{ $success }}</p>

            @if($action)
                <div class="action-name">{{ $action->name }}</div>
            @endif
        @endif

        <a href="{{ config('app.url') }}" class="btn">Go to Dashboard</a>
    </div>
</body>
</html>
