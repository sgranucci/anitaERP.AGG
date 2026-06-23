<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'Resultado' }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
</head>
<body class="bg-light py-5">
<div class="container" style="max-width:560px;">
    <div class="card">
        <div class="card-body text-center py-5">
            @if (($tipo ?? '') === 'ok')
                <div class="text-success mb-3" style="font-size:48px;"><i class="fa fa-check-circle"></i></div>
            @else
                <div class="text-danger mb-3" style="font-size:48px;">&#9888;</div>
            @endif
            <h3>{{ $titulo ?? '' }}</h3>
            <p class="text-muted">{{ $detalle ?? '' }}</p>
        </div>
    </div>
</div>
</body>
</html>
