<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name') }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f7f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f4f7f6; padding-bottom: 60px; }
        .main { background-color: #ffffff; margin: 0 auto; width: 100%; max-width: 600px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-top: 40px; }
        .header { background-color: #0f172a; padding: 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; letter-spacing: 1px; }
        .content { padding: 40px 30px; color: #333333; line-height: 1.6; }
        .content h2 { color: #0f172a; font-size: 20px; margin-top: 0; }
        .btn-container { text-align: center; margin: 30px 0; }
        .btn { background-color: #3b82f6; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 16px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
<center class="wrapper">
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="header">
                <h1>{{ config('app.name') }}</h1>
            </td>
        </tr>

        <tr>
            <td class="content">
                <h2>Hello {{ $user->name }},</h2>
                <p>Welcome aboard! We are thrilled to have you join our platform. Your account has been successfully created and you are fully registered as a <strong>{{ str_replace('_', ' ', Str::title($user->system_role->value)) }}</strong>.</p>

                <p>To get the most out of your experience, you can log in to your dashboard and complete your profile setup.</p>

                <div class="btn-container">
                    <a href="{{ config('app.url') }}/login" class="btn">Go to Dashboard</a>
                </div>

                <p>If you have any questions or need assistance, simply reply to this email. We are here to help!</p>

                <p>Best regards,<br>The {{ config('app.name') }} Team</p>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="footer">
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
                Secure communication from our API platform.
            </td>
        </tr>
    </table>
</center>
</body>
</html>
