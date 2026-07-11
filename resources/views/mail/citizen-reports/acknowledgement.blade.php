<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Citizen report acknowledgement</title>
</head>
<body>
    <p>We received your report.</p>
    <p>Thank you for submitting “{{ $report->title }}”. It has been recorded for review.</p>
    <p>
        <strong>Reference:</strong> {{ $report->public_uuid }}<br>
        <strong>Submitted:</strong> {{ $report->submitted_at?->toDayDateTimeString() }}
    </p>
    <p>You can securely track this report from your CivicLens account.</p>
    <p><a href="{{ route('citizen.reports.show', $report) }}">View report</a></p>
    <p>Thanks,<br>{{ config('app.name') }}</p>
</body>
</html>
