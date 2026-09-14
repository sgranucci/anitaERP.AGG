@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $filasLogo = collect($filas ?? []);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filasLogo);
    $totalFilas = is_countable($filas ?? null) ? count($filas) : 0;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h2 { font-size: 16px; margin: 0; font-weight: bold; }
        h3 { font-size: 11px; margin: 4px 0 0 0; font-weight: normal; color: #444; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .data { width: 100%; border-collapse: collapse; }
        .data th { background-color: #85C1E9; color: #17202A; padding: 5px 6px; border: 1px solid #cccccc; font-size: 8px; }
        .data td { padding: 4px 5px; border: 1px solid #cccccc; vertical-align: top; font-size: 8px; }
        .data tr:nth-child(even) { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 30%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}"
                        style="max-height: 56px; max-width: 180px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <h2>{{ $titulo ?? 'Histórico COT ARBA' }}</h2>
                @if (!empty($subtitulo))
                    <h3>{{ $subtitulo }}</h3>
                @endif
                <div class="meta">Generado {{ now()->format('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 20%; text-align: right;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>

    @if (!empty($sesion))
        @php
            $repartoSesion = $sesion->etiquetaRepartos();
        @endphp
        <p class="meta"><strong>Reparto:</strong> {{ $repartoSesion !== '' ? $repartoSesion : '—' }}</p>
    @endif

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
