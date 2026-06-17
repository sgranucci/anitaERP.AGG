<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f5f5; padding:40px; color:#333; }
        .card { max-width:560px; margin:0 auto; background:#fff; border-radius:6px; padding:30px; box-shadow:0 1px 6px rgba(0,0,0,.06); }
        .ok { color:#28a745; }
        .error { color:#dc3545; }
        h1 { font-size:22px; margin-top:0; }
    </style>
</head>
<body>
    <div class="card">
        <h1 class="{{ $tipo }}">{{ $titulo }}</h1>
        <p>{{ $detalle }}</p>
    </div>
</body>
</html>
