@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $tituloReporte = $titulo ?? 'Reporte tickets e ingresos';
    $k = $kpis ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; text-align: left; padding: 2px 3px; vertical-align: top; }
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 7px; color: #444; margin-top: 3px; }
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
                Tickets: {{ $k['tickets'] ?? 0 }} · Personas: {{ $k['personas'] ?? 0 }}<br>
                Ingresos: {{ $k['con_ingreso'] ?? 0 }} · En planta: {{ $k['en_planta'] ?? 0 }}<br>
                Registros: {{ $totalFilas ?? 0 }}
            </td>
        </tr>
    </table>
    <table class="data">
        @include('seguridad.ingreso_proveedor_reporte.kpi.partials.tabla_datos', [
            'filas' => $filas,
            'en_pantalla' => false,
            'puede_ver_ticket' => false,
            'puede_ver_oc' => false,
            'puede_ver_proveedor' => false,
            'puede_ver_empresa' => false,
            'puede_ver_usuario' => false,
        ])
    </table>
</body>
</html>
