<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admission Letter Ready</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6;">

<h2>Hello {{ $job->user->name }},</h2>

<p>
    Your <strong>JAMB Admission Status</strong> request has been successfully completed.
</p>

<p><strong>Details:</strong></p>
<ul>
    <li><strong>Profile Code:</strong> {{ $job->profile_code }}</li>
    <li><strong>Registration Number:</strong> {{ $job->registration_number ?? 'N/A' }}</li>
    <li><strong>Service:</strong> {{ $job->service->name }}</li>
    <li><strong>Status:</strong> Completed (Awaiting Approval)</li>
</ul>

<p>
    You can download your admission letter using the link below:
</p>

<p>
    <a href="{{ asset('storage/' . $job->result_file) }}"
       style="display:inline-block;padding:10px 15px;background:#2563eb;color:#fff;text-decoration:none;border-radius:4px;">
        Download Admission Letter
    </a>
</p>

<p>
    If you have any questions, feel free to contact support.
</p>

<p>
    Regards,<br>
    <strong>{{ config('app.name') }}</strong>
</p>

</body>
</html>
