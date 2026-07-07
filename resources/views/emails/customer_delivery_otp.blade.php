<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your Delivery Verification Code</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f5f7;
            color: #333333;
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            background-color: #ffffff;
            margin: 0 auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #eef2f5;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb; /* Tailored primary accent color */
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .otp-box {
            background-color: #f0f7ff;
            border: 2px dashed #bfdbfe;
            border-radius: 6px;
            text-align: center;
            font-size: 32px;
            font-weight: bold;
            color: #1e40af;
            letter-spacing: 6px;
            padding: 15px;
            margin: 25px 0;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #eef2f5;
            padding-top: 20px;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="logo">Logistics Engine</div>
    </div>

    <p>Hello,</p>

    <p>Your items are officially <strong>In Transit</strong>! Your courier is on their way to your delivery address.</p>

    <p>To complete the delivery safely and verify your identity, please provide the following 6-digit confirmation code to your driver upon arrival:</p>

    <!-- The generated 6-digit OTP stored in Redis -->
    <div class="otp-box">
        {{ $otp }}
    </div>

    <blockquote>
        <strong>Important Security Notice:</strong> This code is strictly valid for the next 2 hours. Do not share this code via text or call; only give it to the driver face-to-face when your package arrives.
    </blockquote>

    <p>Thank you for choosing our platform!</p>

    <div class="footer">
        <p>This is an automated operational message regarding Order #{{ $order->id }}.<br>
            If you did not request this delivery, please ignore this email or contact support immediately.</p>
    </div>
</div>

</body>
</html>
