<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Invitation</title>
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
        .button-group {
            margin: 32px 0;
            text-align: center;
        }
        .button {
            display: inline-block;
            padding: 14px 32px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
        }
        .button-primary {
            background-color: #22C55E;
            color: #ffffff !important;
        }
        .note {
            color: #6b7280;
            font-size: 14px;
            margin-top: 24px;
        }
        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>You're invited to join a team</h1>

            <p><span class="highlight">{{ $inviterName }}</span> has invited you to join <span class="highlight">{{ $teamName }}</span> on CallMeLater.</p>

            <p>Team members can collaborate on scheduled actions and share access to the team's resources.</p>

            <div class="button-group">
                <a href="{{ $acceptUrl }}" class="button button-primary">Accept Invitation</a>
            </div>

            <p class="note">This invitation will expire in {{ $expiresIn }}. If you don't have a CallMeLater account yet, you'll be able to create one when you accept.</p>

            <div class="footer">
                <p>CallMeLater</p>
            </div>
        </div>
    </div>
</body>
</html>
