<!DOCTYPE html>
<html>
<head>
    <title>New Appointment Request</title>
</head>
<body>
    <p>You have a new appointment request from {{ $clientProfile->first_name }} 
        {{ $clientProfile->last_name }} on {{ $appointment->appointment_datetime }}.</p>
    <p>Description: {{ $appointment->description }}</p>
</body>
</html>