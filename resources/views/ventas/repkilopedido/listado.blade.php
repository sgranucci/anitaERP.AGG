@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Ventas\KiloPedidoListadoFiltros;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
    $totalFilas = is_countable($filas) ? count($filas) : 0;
    $esTotal = ($filtros['tipolistado'] ?? 'TOTAL') === 'TOTAL';
    $tituloReporte = $esTotal ? 'Kilos pedidos totalizado por pedido' : 'Kilos pedidos abierto por ítem';
    $subtitulo = 'Reparto: '.KiloPedidoListadoFiltros::formatearRepartoTexto($filtros)
        .' · Período: '.KiloPedidoListadoFiltros::formatearPeriodoTexto($filtros);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; color: #1a1a1a; line-height: 1.35; }
        table.data {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 4px 5px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 9px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 8px; font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 9px; color: #444; margin-top: 4px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 46%; text-align: center;">
                <h2 style="margin: 0; font-size: 18px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo }}</div>
            </td>
            <td style="width: 22%; text-align: right; font-size: 9px;">
                @if ($totalFilas > 0)
                    Filas: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>
    <table class="data">
        @include('ventas.repkilopedido.partials.tabla_datos', [
            'filas' => $filas,
            'filtros' => $filtros,
            'para_pdf' => true,
        ])
    </table>
</body>
</html>
