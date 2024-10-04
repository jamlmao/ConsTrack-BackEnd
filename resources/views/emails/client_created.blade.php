<!DOCTYPE html>
<html>
<head>
    <title>Your Account Has Been Created</title>
</head>
<body>
  
     
    <h1>Welcome, <span style="text-transform:uppercase;">{{ $clientProfile->first_name }} {{ $clientProfile->last_name }}!</span></h1>
    <p>Your account has been created successfully. Here are your login details:</p>
    <hr>
    <p><strong>Email:</strong> {{ $user->email }} or you can use <strong>Username:</strong> {{ $user->name }}</p>
    <p><strong>Password:</strong> {{ $password }}</p>
</body>
</html>