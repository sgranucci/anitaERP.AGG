@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Ventas\PedidoListadoSupport;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($pedidos);
    $totalFilas = $totalFilas ?? (is_countable($pedidos) ? count($pedidos) : 0);
    $mostrarCabecera = $mostrarCabecera ?? true;
    $mostrarTotalesGenerales = $mostrarTotalesGenerales ?? true;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Pedidos de clientes</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
        }
        table.data tfoot tr { background-color: #e8e8e8; font-weight: bold; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
    </style>
</head>
<body>
    @if ($mostrarCabecera)
        <table class="listado-header">
            <tr>
                <td style="width: 35%;">
                    @foreach ($logosCabecera as $logo)
                        <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
                    @endforeach
                </td>
                <td style="width: 40%; text-align: center;">
                    <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de pedidos de clientes</h2>
                    <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                </td>
                <td style="width: 25%; text-align: right; font-size: 8px;">
                    @if ($totalFilas > 0)
                        Registros: {{ $totalFilas }}
                    @endif
                </td>
            </tr>
        </table>
    @endif
    <table class="data">
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th style="width: 8%;">Fecha</th>
                <th style="width: 9%;">Fecha entrega</th>
                <th style="width: 28%;">Cliente</th>
                <th style="width: 7%;">Cajas</th>
                <th style="width: 7%;">Piezas</th>
                <th style="width: 7%;">Kilos</th>
                <th style="width: 7%;">Pesada</th>
                <th style="width: 14%;">Reparto</th>
                <th style="width: 8%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pedidos as $pedido)
                @php $totales = PedidoListadoSupport::totalesPedido($pedido); @endphp
                @include('ventas.pedido.partials.export_listado_filas', compact('pedido', 'totales'))
            @endforeach
        </tbody>
        @if ($mostrarTotalesGenerales && $totalFilas > 0)
            @php
                $totalesFinales = $totalesGenerales ?? ['caja' => 0, 'pieza' => 0, 'kilo' => 0, 'pesada' => 0];
                if (! isset($totalesGenerales)) {
                    foreach ($pedidos as $pedido) {
                        $t = PedidoListadoSupport::totalesPedido($pedido);
                        $totalesFinales['caja'] += $t['caja'];
                        $totalesFinales['pieza'] += $t['pieza'];
                        $totalesFinales['kilo'] += $t['kilo'];
                        $totalesFinales['pesada'] += $t['pesada'];
                    }
                }
            @endphp
            <tfoot>
                <tr>
                    <td colspan="4">Totales</td>
                    <td>{{ $totalesFinales['caja'] }}</td>
                    <td>{{ $totalesFinales['pieza'] }}</td>
                    <td>{{ $totalesFinales['kilo'] }}</td>
                    <td>{{ $totalesFinales['pesada'] }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
