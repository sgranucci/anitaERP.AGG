<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h2, h3 { margin: 0 0 6px 0; }
        .data { width: 100%; border-collapse: collapse; }
        .data th { background-color: #85C1E9; color: #17202A; padding: 4px; border: 1px solid #cccccc; }
        .data td { padding: 3px; border: 1px solid #cccccc; vertical-align: top; }
        .data tr:nth-child(even) { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <h2>{{ $titulo ?? 'Histórico COT ARBA' }}</h2>
    @if (!empty($subtitulo))
        <h3>{{ $subtitulo }}</h3>
    @endif
    <p>Generado {{ now()->format('d/m/Y H:i') }} — {{ count($filas) }} registro(s)</p>

    <table class="data">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Letra</th>
                <th>Suc.</th>
                <th>N&deg; remito</th>
                <th>Fecha remito</th>
                <th>Fecha env&iacute;o</th>
                <th>Cliente</th>
                <th>N&deg; COT</th>
                <th>N&deg; &uacute;nico</th>
                <th>Proc.</th>
                <th>Observaci&oacute;n ARBA</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $remito)
                @php
                    $fechaEnvio = $remito->cotSesionEnvio->fecha_envio ?? ($sesion->fecha_envio ?? null);
                @endphp
                <tr>
                    <td>{{ $remito->tipo }}</td>
                    <td>{{ $remito->letra }}</td>
                    <td>{{ $remito->sucursal }}</td>
                    <td>{{ $remito->numero_remito }}</td>
                    <td>{{ optional($remito->fecha_remito)->format('d/m/Y') }}</td>
                    <td>{{ optional($fechaEnvio)->format('d/m/Y H:i') }}</td>
                    <td>{{ $remito->cliente_nombre ?: optional($remito->clientes)->nombre }}</td>
                    <td>{{ $remito->cot }}</td>
                    <td>{{ $remito->nro_unico }}</td>
                    <td>{{ $remito->procesado }}</td>
                    <td>{{ $remito->error }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
