@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $coleccionLogo = ! empty($empresa_nombre)
        ? collect([(object) ['nombreempresa' => $empresa_nombre]])
        : collect();
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogo);
    $tituloReporte = $titulo ?? 'Venta hora por hora';
    $subtituloReporte = $subtitulo ?? '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 6px; color: #1a1a1a; }
        .listado-header { width: 100%; margin-bottom: 7px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 7px; color: #444; margin-top: 2px; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data th, table.data td {
            border: 1px solid #cccccc;
            padding: 2px 1px;
            font-size: 4.8px;
            overflow: hidden;
            white-space: nowrap;
        }
        table.data thead tr { background-color: #85C1E9; color: #17202A; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data tfoot tr { background-color: #e8e8e8; font-weight: bold; }
        table.data th:nth-child(1), table.data td:nth-child(1) { width: 18px; }
        table.data th:nth-child(2), table.data td:nth-child(2) { width: 31px; }
        table.data .text-right { text-align: right; }
        .text-capitalize { text-transform: capitalize; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 44px; max-width: 130px; margin-right: 6px;">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <h2 style="margin: 0; font-size: 13px;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if ($subtituloReporte !== '')
                    <div class="meta">{{ $subtituloReporte }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 7px;">
                Jornadas: {{ $resultado['cantidad_dias'] ?? 0 }}<br>
                Comprobantes: {{ $resultado['cantidad_comprobantes'] ?? 0 }}<br>
                Horas: {{ $resultado['rango_horas_texto'] ?? '' }}
            </td>
        </tr>
    </table>

    <table class="data">
        @include('ventas.gastronomia.venta_hora_reporte.partials.tabla_datos', [
            'filas' => $resultado['filas'] ?? [],
            'horas' => $resultado['horas'] ?? [],
            'totales_hora' => $resultado['totales_hora'] ?? [],
            'total_general' => $resultado['total_general'] ?? 0,
            'promedio_hora' => $resultado['promedio_hora'] ?? 0,
            'mostrar_totales' => true,
        ])
    </table>
</body>
</html>
