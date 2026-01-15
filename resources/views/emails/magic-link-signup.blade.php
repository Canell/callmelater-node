<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete your CallMeLater signup</title>
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
            <h1>Welcome to CallMeLater!</h1>

            <p>Click the button below to complete your signup and access your new account:</p>

            <div class="button-group">
                <a href="{{ $signupUrl }}" class="button button-primary">Complete Signup</a>
            </div>

            <p class="note">This link will expire in {{ $expiresIn }}. If you didn't sign up for CallMeLater, you can safely ignore this email.</p>

            <div class="footer">
                <p>CallMeLater</p>
            </div>
        </div>
    </div>
</body>
</html>
