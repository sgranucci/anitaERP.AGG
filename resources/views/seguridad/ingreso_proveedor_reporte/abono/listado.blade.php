@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $tituloReporte = $titulo ?? 'Abono mensual sin ingresos';
    $k = $kpis ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 3px 4px; vertical-align: top; }
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 3px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 48px; max-width: 140px; margin-right: 6px;">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (!empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right;">
                Contratos: {{ $k['contratos'] ?? 0 }} · OK: {{ $k['ok'] ?? 0 }}<br>
                Sin ingresos: {{ $k['revisar'] ?? 0 }} · Registros: {{ $totalFilas ?? 0 }}
            </td>
        </tr>
    </table>
    <table class="data">
        @include('seguridad.ingreso_proveedor_reporte.abono.partials.tabla_datos', [
            'filas' => $filas,
            'en_pantalla' => false,
            'puede_ver_oc' => false,
            'puede_ver_proveedor' => false,
        ])
    </table>
</body>
</html>
