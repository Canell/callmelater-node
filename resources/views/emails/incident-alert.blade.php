<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $incident->title }}</title>
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
        .alert-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        .badge-critical {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .badge-major {
            background-color: #ffedd5;
            color: #c2410c;
        }
        .badge-minor {
            background-color: #fef3c7;
            color: #b45309;
        }
        .details {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin: 24px 0;
        }
        .details-row {
            display: flex;
            margin-bottom: 12px;
        }
        .details-row:last-child {
            margin-bottom: 0;
        }
        .details-label {
            font-weight: 600;
            width: 140px;
            flex-shrink: 0;
            color: #6b7280;
        }
        .details-value {
            color: #333;
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
        .btn-primary {
            background-color: #3b82f6;
            color: #ffffff;
        }
        .btn-secondary {
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
            .details-row {
                flex-direction: column;
            }
            .details-label {
                width: auto;
                margin-bottom: 4px;
            }
            .btn {
                display: block;
                margin: 8px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="alert-badge badge-{{ $incident->impact }}">{{ $incident->impact_label }}</span>
            <h1>{{ $incident->title }}</h1>
        </div>

        <p>An automated incident has been created that requires your attention:</p>

        <div class="details">
            <div class="details-row">
                <span class="details-label">Component:</span>
                <span class="details-value">{{ $componentName }}</span>
            </div>
            <div class="details-row">
                <span class="details-label">Reason:</span>
                <span class="details-value">{{ $reason }}</span>
            </div>
            <div class="details-row">
                <span class="details-label">Started:</span>
                <span class="details-value">{{ $incident->started_at->format('M j, Y \a\t g:i A T') }}</span>
            </div>
            <div class="details-row">
                <span class="details-label">Status:</span>
                <span class="details-value">{{ $incident->status_label }}</span>
            </div>
        </div>

        <div class="buttons">
            <a href="{{ $adminUrl }}" class="btn btn-primary">View Admin Dashboard</a>
            <a href="{{ $statusPageUrl }}" class="btn btn-secondary">View Status Page</a>
        </div>

        <p style="text-align: center; color: #6b7280; font-size: 14px;">
            This incident was auto-created by the health monitoring system.<br>
            Please investigate and update the incident status as needed.
        </p>

        <div class="footer">
            <p>
                This alert was sent by <strong>{{ config('app.name') }}</strong>.<br>
                You are receiving this because you opted into incident alerts.
            </p>
        </div>
    </div>
</body>
</html>
