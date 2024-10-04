<!DOCTYPE html>
<html>
<head>
    <title>Appointment Rejected</title>
</head>
<body>
    <p>Dear {{ strtoupper($appointment->client->first_name) }} {{ strtoupper($appointment->client->last_name) }},</p>
    <p>We regret to inform you that your appointment has been rejected.</p>
    <p>Here are some available dates for rescheduling:</p>
    <p>{{ implode(', ', $availableDates) }}</p>
    <p>Please reschedule your appointment.</p>
    <p>Thank you.</p>
</body>
</html>