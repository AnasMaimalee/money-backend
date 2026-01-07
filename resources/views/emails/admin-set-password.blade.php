<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set Up Your Password</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f5f6fa; margin:0; padding:0;">

<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td align="center" style="padding: 20px 0;">
            <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 0 10px rgba(0,0,0,0.1);">

                <!-- Header -->
                <tr>
                    <td align="center" style="background-color: #4f46e5; padding: 30px;">
                        <h1 style="color: #ffffff; font-size: 24px; margin: 0;">{{ config('app.name') }}</h1>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding: 30px; color: #333333;">
                        <h2 style="font-size: 20px;">Hello {{ $user->name }},</h2>
                        <p style="font-size: 16px; line-height: 1.5;">
                            Your account has been created by the superadmin. To access your dashboard, you need to set your password.
                        </p>

                        <p style="font-size: 16px; line-height: 1.5;">
                            <strong>Email:</strong> {{ $user->email }}
                        </p>

                        <!-- Button -->
                        <p style="text-align: center; margin: 30px 0;">
                            <a href="{{ $resetUrl }}" style="background-color: #4f46e5; color: #ffffff; text-decoration: none; padding: 15px 25px; border-radius: 5px; display: inline-block; font-weight: bold;">
                                Set Your Password
                            </a>
                        </p>

                        <p style="font-size: 14px; color: #555555; line-height: 1.5;">
                            If you did not expect this email, you can safely ignore it.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td align="center" style="background-color: #f5f6fa; padding: 20px; font-size: 12px; color: #999999;">
                        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
