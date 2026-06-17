<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Account</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f7f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f4f7f6; padding-bottom: 60px; }
        .main { background-color: #ffffff; margin: 0 auto; width: 100%; max-width: 600px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-top: 40px; }
        .header { background-color: #0f172a; padding: 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; letter-spacing: 1px; }
        .content { padding: 40px 30px; color: #333333; line-height: 1.6; }
        .content h2 { color: #0f172a; font-size: 20px; margin-top: 0; }
        .otp-box { text-align: center; margin: 30px 0; background-color: #f1f5f9; padding: 20px; border-radius: 8px; font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #3b82f6; }
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
                <p>Thank you for registering. To complete your account setup and activate your profile, please enter the 6-digit verification code below:</p>

                <div class="otp-box">
                    {{ $otp }}
                </div>

                <p>This code will expire in 10 minutes. If you did not create an account, no further action is required.</p>
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
