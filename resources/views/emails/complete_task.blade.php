<!DOCTYPE html>
<html>
<head>
    <title>Task Completed</title>
</head>
<body>
    <h1>Task Completed</h1>
    <p>The task "{{ $task->pt_task_name }}" has been completed.</p>
    <p>Completion Date: {{ $task->pt_completion_date }}</p>
</body>
</html>