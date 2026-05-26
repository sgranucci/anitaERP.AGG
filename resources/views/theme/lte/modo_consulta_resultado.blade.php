<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>[Consulta] Operación realizada | Anita ERP</title>
    <link rel="stylesheet" href="{{ asset('assets/lte/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/lte/dist/css/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Source Sans Pro', sans-serif;
        }
        .panel-resultado {
            max-width: 520px;
            margin: 80px auto;
            background: #fff;
            padding: 36px 32px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            text-align: center;
            border-top: 4px solid #28a745;
        }
        .panel-resultado .icono {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 18px;
        }
        .panel-resultado .acciones {
            margin-top: 24px;
        }
        .panel-resultado .acciones .btn {
            margin: 4px;
        }
    </style>
</head>
<body>
    <div class="panel-resultado">
        <div class="icono"><i class="fas fa-check-circle"></i></div>
        <h4 class="mb-3">{{ $mensaje }}</h4>
        <p class="text-muted mb-0">Esta solapa fue abierta como consulta desde otra pantalla del sistema.</p>
        <p class="text-muted mb-0">Cer&aacute;la para volver a tu pantalla principal.</p>
        <div class="acciones">
            <button type="button" class="btn btn-primary" onclick="window.close();">
                <i class="fa fa-times"></i> Cerrar solapa
            </button>
            @if (!empty($urlContinuar))
                <a href="{{ $urlContinuar }}" class="btn btn-outline-secondary" data-modo-consulta-omitir="1">
                    <i class="fa fa-arrow-left"></i> Seguir consultando
                </a>
            @endif
        </div>
        <p class="small text-muted mt-3 mb-0">
            Si el navegador no cierra la solapa autom&aacute;ticamente, cerrala manualmente.
        </p>
    </div>
</body>
</html>
