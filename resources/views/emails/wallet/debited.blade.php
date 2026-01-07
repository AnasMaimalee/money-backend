<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Wallet Debited</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f8; font-family: Arial, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="padding:30px 0;">
    <tr>
        <td align="center">
            <table width="100%" max-width="600" style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">

                <!-- HEADER -->
                <tr>
                    <td style="background:#dc2626; padding:20px; text-align:center; color:#ffffff;">
                        <h2 style="margin:0;">Wallet Debited</h2>
                    </td>
                </tr>

                <!-- BODY -->
                <tr>
                    <td style="padding:30px; color:#333;">
                        <p style="font-size:16px;">Hello <strong>{{ $user->name }}</strong>,</p>

                        <p>Your wallet has been debited for a service request.</p>

                        <table width="100%" style="margin:20px 0; border-collapse:collapse;">
                            <tr>
                                <td style="padding:10px; background:#f9fafb; font-weight:bold;">Amount</td>
                                <td style="padding:10px; background:#f9fafb;">₦{{ number_format($amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px; background:#f9fafb; font-weight:bold;">Remaining Balance</td>
                                <td style="padding:10px; background:#f9fafb;">₦{{ number_format($balance, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px; background:#f9fafb; font-weight:bold;">Reason</td>
                                <td style="padding:10px; background:#f9fafb;">{{ $reason }}</td>
                            </tr>

                        </table>

                        <p style="margin-top:20px;">
                            This debit was made for a JAMB-related operation.
                        </p>

                        <p style="margin-top:30px;">
                            Regards,<br>
                            <strong>{{ config('app.name') }} Team</strong>
                        </p>
                    </td>
                </tr>

                <!-- FOOTER -->
                <tr>
                    <td style="background:#f1f5f9; padding:15px; text-align:center; font-size:12px; color:#666;">
                        If you did not authorize this transaction, please contact support immediately.
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
