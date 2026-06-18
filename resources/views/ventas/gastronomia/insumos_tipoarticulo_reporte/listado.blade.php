@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $coleccionLogo = ! empty($empresa_nombre)
        ? collect([(object) ['nombreempresa' => $empresa_nombre]])
        : collect();
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogo);
    $tituloReporte = $titulo ?? 'Ventas insumos gastronomía por día';
    $subtituloReporte = $subtitulo ?? '';
    $totalFilas = (int) ($resultado['cantidad_articulos'] ?? 0);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; line-height: 1.25; }
        table.data {
            border-collapse: collapse;
            width: 100%;
            table-layout: auto;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px 4px;
            vertical-align: top;
            font-size: 7px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        table.data tfoot tr { background-color: #e8e8e8; font-weight: bold; }
        .text-right { text-align: right; white-space: nowrap; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 3px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 48px; max-width: 140px; margin-right: 6px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 46%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if ($subtituloReporte !== '')
                    <div class="meta">{{ $subtituloReporte }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0)
                    Artículos: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>
    <table class="data">
        @include('ventas.gastronomia.insumos_tipoarticulo_reporte.partials.tabla_datos', [
            'resultado' => $resultado,
            'filas' => $resultado['filas'] ?? [],
            'puede_ver_articulo' => false,
            'mostrar_totales' => true,
        ])
    </table>
</body>
</html>
