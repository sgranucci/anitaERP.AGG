@php
    use App\Support\Compras\Tracking\TrackingFacturaFila;
    use App\Support\Compras\Tracking\TrackingFacturasListadoFiltros;
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;

    $segmentos = TrackingFacturasListadoFiltros::segmentos();
    $segmentoActivo = $filtros['segmento'] ?? TrackingFacturasListadoFiltros::SEGMENTO_TODOS;
    $ejeActivo = $filtros['eje_fecha'] ?? TrackingFacturasListadoFiltros::EJE_FECHA_COMPROBANTE;

    $criterio = $segmentos[$segmentoActivo]['label'] ?? 'Todos';
    $ejeLabel = TrackingFacturasListadoFiltros::ejesFecha()[$ejeActivo] ?? '';

    $rango = '';
    if (($filtros['fecha_desde'] ?? '') !== '' || ($filtros['fecha_hasta'] ?? '') !== '') {
        $rango = trim(sprintf(
            '%s a %s',
            ($filtros['fecha_desde'] ?? '') !== '' ? \Carbon\Carbon::parse($filtros['fecha_desde'])->format('d/m/Y') : 'inicio',
            ($filtros['fecha_hasta'] ?? '') !== '' ? \Carbon\Carbon::parse($filtros['fecha_hasta'])->format('d/m/Y') : 'hoy',
        ));
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tracking de facturas</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
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
        table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
        td.num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        .resumen { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .resumen td { border: 1px solid #dddddd; padding: 5px 7px; font-size: 8px; }
        .resumen .l { color: #555; text-transform: uppercase; font-size: 7px; }
        .resumen .v { font-weight: bold; font-size: 10px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 30%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}"
                         style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 45%; text-align: center;">
                <h2 style="margin: 0; font-size: 18px; font-weight: bold;">Tracking de facturas</h2>
                <div class="meta">
                    {{ $criterio }}
                    @if ($rango !== '')
                        &mdash; {{ $ejeLabel }}: {{ $rango }}
                    @endif
                </div>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                Registros: {{ number_format($totalFilas, 0, ',', '.') }}
            </td>
        </tr>
    </table>

    @if (! empty($resumen))
        <table class="resumen">
            <tr>
                <td>
                    <div class="l">Importe total</div>
                    <div class="v">$ {{ number_format((float) $resumen['total'], 2, ',', '.') }}</div>
                </td>
                <td>
                    <div class="l">Saldo pendiente</div>
                    <div class="v">$ {{ number_format((float) $resumen['saldo'], 2, ',', '.') }}</div>
                </td>
                <td>
                    <div class="l">Sin pagar</div>
                    <div class="v">{{ number_format((int) $resumen['con_deuda'], 0, ',', '.') }}</div>
                </td>
                <td>
                    <div class="l">Sin contabilizar</div>
                    <div class="v">{{ number_format((int) $resumen['sin_contabilizar'], 0, ',', '.') }}</div>
                </td>
                <td>
                    <div class="l">Sin PDF</div>
                    <div class="v">{{ number_format((int) $resumen['sin_pdf'], 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th style="width: 4%;">Tipo</th>
                <th style="width: 4%;">Abrev.</th>
                <th style="width: 9%;">N&uacute;mero</th>
                <th style="width: 6%;">F. comprobante</th>
                <th style="width: 6%;">F. carga</th>
                <th style="width: 6%;">F. contab.</th>
                <th style="width: 8%;">Empresa</th>
                <th style="width: 11%;">Proveedor</th>
                <th style="width: 8%;">Importe</th>
                <th style="width: 8%;">Saldo</th>
                <th style="width: 6%;">Estado</th>
                <th style="width: 5%;">OC</th>
                <th style="width: 6%;">Pago</th>
                <th style="width: 8%;">Orden de pago</th>
                <th style="width: 5%;">PDF</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datas as $data)
                @php
                    $fila = TrackingFacturaFila::de($data);
                    $estado = $fila->estadoContable();
                    $pago = $fila->estadoPago();
                @endphp
                <tr>
                    <td>{{ $fila->familia() }}</td>
                    <td>{{ $fila->tipoAbreviatura() }}</td>
                    <td>{{ $fila->numero() }}</td>
                    <td>{{ $fila->fechaComprobante() }}</td>
                    <td>{{ $fila->fechaCarga() }}</td>
                    <td>{{ $fila->fechaContabilizacion() }}</td>
                    <td>{{ $fila->empresa() }}</td>
                    <td>{{ $fila->proveedor() }}</td>
                    <td class="num">{{ number_format($fila->total(), 2, ',', '.') }}</td>
                    <td class="num">{{ $fila->saldo() == 0 ? '' : number_format($fila->saldo(), 2, ',', '.') }}</td>
                    <td>{{ $estado['etiqueta'] }}</td>
                    <td>{{ $fila->numeroOrdencompra() }}</td>
                    <td>{{ $pago['etiqueta'] }}</td>
                    <td>{{ $fila->ordenPago() }}{{ $fila->ordenesPagoExtra() > 0 ? ' (+'.$fila->ordenesPagoExtra().')' : '' }}</td>
                    <td>{{ $fila->puedeVerPdf() ? $fila->pdfOrigen() : ($fila->indexado() ? 'Falta' : 'Sin resolver') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
