<!DOCTYPE html>
<html>
<head>
    <title>Task Due Tomorrow</title>
</head>
<body>
    <h1>Task Due Tomorrow</h1>
    <p>The task "{{ $task->pt_task_name }}" is due tomorrow.</p>
    <p>Completion Date: {{ $task->pt_completion_date }}</p>
</body>
</html>