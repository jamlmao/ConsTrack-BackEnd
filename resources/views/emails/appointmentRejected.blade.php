<!DOCTYPE html>
<html>
<head>
    <title>Appointment Rejected</title>
</head>
<body>
    <h1>Appointment Rejected</h1>
    <p>Your appointment has been rejected due to an unforeseen issue.</p>
    <p>Details:</p>
    <ul>
        <li>Description: {{ $appointment->description }}</li>
        <li>Date and Time: {{ $appointment->appointment_datetime }}</li>
    </ul>
</body>
</html>