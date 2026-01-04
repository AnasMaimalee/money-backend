<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Service Rejected</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f9fafb; padding:20px;">
<div style="max-width:600px; margin:auto; background:#ffffff; padding:20px; border-radius:6px;">

    <h2 style="color:#dc2626;">Service Rejected</h2>

    <p>Hello <strong>{{ $job->user->name }}</strong>,</p>

    <p>
        Unfortunately, your request for the service
        <strong>{{ $job->service->name }}</strong>
        has been <strong>rejected</strong>.
    </p>

    <p><strong>Reason:</strong></p>
    <p style="background:#fee2e2; padding:10px; border-left:4px solid #dc2626;">
        {{ $job->rejection_reason }}
    </p>

    <p>
        The amount of <strong>₦{{ number_format($job->customer_price, 2) }}</strong>
        has been fully refunded to your wallet.
    </p>

    <hr>

    <p style="font-size:14px; color:#6b7280;">
        If you have any questions, please contact support.
    </p>

    <p style="margin-top:30px;">
        Regards,<br>
        <strong>{{ config('app.name') }} Team</strong>
    </p>
</div>
</body>
</html>
