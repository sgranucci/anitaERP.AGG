<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asiento {{ $data->numeroasiento }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
</head>
<body class="bg-light py-4">
<div class="container">
    <div class="card">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0">Asiento {{ $data->numeroasiento }} — revisión</h4>
        </div>
        <div class="card-body">
            @include('contable.aprobacion_asiento.partials.detalle_asiento', ['data' => $data])
        </div>
        @if ($data->estaPendienteAprobacion())
            <div class="card-footer">
                <a href="{{ route('asiento_aprobar_publico', ['token' => $token]) }}" class="btn btn-success" onclick="return confirm('¿Aprobar este asiento?');">Aprobar</a>
                @if (! empty($tokenRechazar))
                    <a href="{{ route('asiento_rechazar_publico', ['token' => $tokenRechazar]) }}" class="btn btn-danger">Rechazar</a>
                @endif
            </div>
        @endif
    </div>
</div>
</body>
</html>
