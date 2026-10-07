<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify your email</title>
</head>
<body style="margin:0;background:#f8fafc;color:#1e293b;font-family:Arial,sans-serif">
    <main style="max-width:560px;margin:32px auto;padding:32px;background:#ffffff;border:1px solid #e2e8f0;border-radius:20px">
        <p style="font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#0f766e">DentalCare patient portal</p>
        <h1 style="font-size:24px;margin:20px 0 12px">Hello {{ $user->name }},</h1>
        <p style="font-size:16px;line-height:1.6">Use this verification code to finish setting up your patient account:</p>
        <p style="margin:28px 0;padding:18px;border-radius:12px;background:#f0fdfa;color:#0f766e;font-size:32px;font-weight:bold;letter-spacing:8px;text-align:center">
            {{ $verificationCode }}
        </p>
        <p style="font-size:14px;line-height:1.6;color:#475569">This single-use code expires in 15 minutes. If you did not create this account, you can ignore this email.</p>
        <p style="margin-top:28px;font-size:13px;color:#64748b">If you did not create this account, you can ignore this email.</p>
    </main>
</body>
</html>
