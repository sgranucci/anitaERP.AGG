@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $coleccionLogo = ! empty($empresa_nombre)
        ? collect([(object) ['nombreempresa' => $empresa_nombre]])
        : collect();
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogo);
    $tituloReporte = $titulo ?? 'Reporte analítico gastronomía';
    $subtituloReporte = $subtitulo ?? '';
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $tot = $resultado['totales'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 6.5px; color: #1a1a1a; line-height: 1.2; }
        table.data { border-collapse: collapse; width: 100%; table-layout: auto; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: top;
            font-size: 6px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        table.data td.text-right, table.data th.text-right { text-align: right; white-space: nowrap; }
        .text-right { text-align: right; white-space: nowrap; }
        .listado-header { width: 100%; margin-bottom: 6px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 7px; color: #444; margin-top: 2px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 30%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 44px; max-width: 130px; margin-right: 6px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <h2 style="margin: 0; font-size: 13px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if ($subtituloReporte !== '')
                    <div class="meta">{{ $subtituloReporte }}</div>
                @endif
            </td>
            <td style="width: 20%; text-align: right; font-size: 7px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
                @if (! empty($tot))
                    <div>Cant. {{ number_format((float) ($tot['cantidad_total'] ?? 0), 2, ',', '.') }}</div>
                    <div>Imp. ${{ number_format((float) ($tot['total_importe'] ?? 0), 2, ',', '.') }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="data">
        @include('ventas.gastronomia.analitico_reporte.partials.tabla_datos', [
            'filas' => $filas,
            'con_links' => false,
        ])
    </table>
</body>
</html>
