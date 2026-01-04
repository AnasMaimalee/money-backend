{{-- resources/views/emails/service_completed.blade.php --}}
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>JAMB Upload Status Completed</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f5f5f5; padding: 20px;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
    <tr style="background-color: #4CAF50; color: white; text-align: center;">
        <td style="padding: 20px; font-size: 22px;">
            JAMB Result Service
        </td>
    </tr>
    <tr>
        <td style="padding: 20px; color: #333333; font-size: 16px; line-height: 1.5;">
            Hello {{ $job->user->name ?? 'Student' }},<br><br>

            Your request for <strong>{{ $job->service->name }}</strong> has been completed successfully.<br>
            The administrator has processed your request and your payment has been confirmed.<br><br>

            <strong>Details:</strong><br>
            Email: {{ $job->email }}<br>
            Registration Number: {{ $job->registration_number ?? 'N/A' }}<br>
            Amount Paid: ₦{{ number_format($job->customer_price, 2) }}<br><br>

            You can download your result using the button below:
        </td>
    </tr>
    <tr>
        <td style="padding: 20px; text-align: center;">
            <a href="{{ asset('storage/' . $job->result_file) }}" target="_blank"
               style="display: inline-block; background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Download Result
            </a>
        </td>
    </tr>
    <tr>
        <td style="padding: 20px; color: #777777; font-size: 12px; text-align: center;">
            © {{ date('Y') }} JAMB Services. All rights reserved.
        </td>
    </tr>
</table>
</body>
</html>
