<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Exam Seat Allocation</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
  @if(session('status'))
    <div class="alert alert-info">{{ session('status') }}</div>
  @endif
  @yield('content')
</div>
</body>
</html>
