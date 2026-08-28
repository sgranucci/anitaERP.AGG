@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Ventas\FacturaListadoFiltros;
    use App\Support\Ventas\FacturaListadoSupport;
    use App\Support\Ventas\PedidoListadoSupport;

    $ventas = $ventas ?? collect();
    foreach ($ventas as $c) {
        $c->nombreempresa = $c->nombreempresa ?? ($c->puntoventas->empresas->nombre ?? '');
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($ventas);
    $periodoTexto = FacturaListadoFiltros::formatearPeriodoTexto($filtros ?? []);
    $totalGeneral = 0;
    foreach ($ventas as $c) {
        $totalGeneral += (float) ($c->total ?? 0);
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobantes de Ventas</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 3px 5px; text-align: left; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data tbody tr.factura-subtotal-reparto,
        table.data tbody tr.factura-subtotal-reparto td {
            background-color: #F9E79F;
            font-weight: bold;
            color: #17202A;
        }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        .num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { border: none; vertical-align: middle; }
        .meta { font-size: 8px; color: #444; margin-top: 2px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 47%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">Comprobantes de Ventas</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if ($periodoTexto !== '')
                    <div class="meta">Per&iacute;odo: {{ $periodoTexto }}</div>
                @endif
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                Registros: {{ is_countable($ventas) ? count($ventas) : 0 }}
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Comprobante</th>
                <th>Cliente</th>
                <th>Empresa</th>
                <th class="num">Cajas</th>
                <th class="num">Unidades</th>
                <th class="num">Kilos</th>
                <th>Reparto</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ventas as $comprobante)
                @php $totales = FacturaListadoSupport::totalesFactura($comprobante); @endphp
                <tr>
                    <td>{{ $comprobante->id ?? '' }}</td>
                    <td>{{ $comprobante->fecha ? date('d/m/Y', strtotime($comprobante->fecha)) : '' }}</td>
                    <td>
                        {{ $comprobante->tipotransacciones->nombre ?? '' }}
                        {{ $comprobante->clientes->condicionivas->letra ?? '' }}
                        {{ $comprobante->puntoventas->codigo ?? '' }}-{{ $comprobante->numerocomprobante }}
                    </td>
                    <td>{{ $comprobante->clientes->nombre ?? '' }}</td>
                    <td>{{ $comprobante->nombreempresa }}</td>
                    <td class="num">{{ PedidoListadoSupport::formatearTotal($totales['caja']) }}</td>
                    <td class="num">{{ PedidoListadoSupport::formatearTotal($totales['pieza']) }}</td>
                    <td class="num">{{ PedidoListadoSupport::formatearTotal($totales['kilo']) }}</td>
                    <td>{{ FacturaListadoSupport::etiquetaReparto($comprobante) }}</td>
                    <td class="num">{{ number_format((float) $comprobante->total, 2, ',', '.') }}</td>
                </tr>
                @if (FacturaListadoSupport::esCierreReparto($comprobante, $totalesPorReparto ?? []))
                    @include('ventas.factura.partials.fila_subtotal_reparto', [
                        'metaReparto' => FacturaListadoSupport::metaReparto($comprobante, $totalesPorReparto ?? []),
                    ])
                @endif
            @empty
                <tr>
                    <td colspan="10" style="text-align:center;">Sin registros</td>
                </tr>
            @endforelse
        </tbody>
        @if (is_countable($ventas) && count($ventas) > 0)
            <tfoot>
                <tr>
                    <th colspan="9" class="num">Total general</th>
                    <th class="num">{{ number_format($totalGeneral, 2, ',', '.') }}</th>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
