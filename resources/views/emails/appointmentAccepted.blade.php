<!DOCTYPE html>
<html>
<head>
    <title>Appointment Accepted</title>
</head>
<body>
    <h1>Appointment Accepted</h1>
    <p>Your appointment has been accepted.</p>
    <p>Details:</p>
    <ul>
        <li>Description: {{ $appointment->description }}</li>
        <li>Date and Time: {{ $appointment->appointment_datetime }}</li>
    </ul>
</body>
</html>