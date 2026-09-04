@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $cliente = $reporte['cliente'] ?? null;
    $periodos = $reporte['periodos'] ?? [];
    $generado = $reporte['generado'] ?? date('d/m/Y H:i');
    $nombreCliente = $cliente->nombre ?? '';
    $docCliente = $cliente->numerodocumento ?? '';
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion([]);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Explicación matriz de riesgo UIF</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px 4px;
            vertical-align: top;
        }
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
        }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        h3 { font-size: 10px; color: #1B4F72; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 30%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 45%; text-align: center;">
                <h2 style="margin: 0; font-size: 16px; font-weight: bold;">Explicaci&oacute;n matriz de riesgo UIF</h2>
                @if ($nombreCliente !== '')
                    <div class="meta">{{ $nombreCliente }}@if ($docCliente !== '') — Doc. {{ $docCliente }}@endif</div>
                @endif
                <div class="meta">ID cliente {{ $cliente->id ?? '' }} — Generado {{ $generado }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                Per&iacute;odos: {{ count($periodos) }}
            </td>
        </tr>
    </table>

    @include('uif.cliente_uif.partials.matriz_riesgo_explicacion_contenido', [
        'reporte' => $reporte,
        'esExcel' => false,
    ])
</body>
</html>
