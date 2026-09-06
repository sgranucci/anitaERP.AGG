@php
    use App\Support\Compras\Tracking\TrackingFacturaFila;
    use App\Support\Compras\Tracking\TrackingFacturasListadoFiltros;

    $segmentoActivo = $filtros['segmento'] ?? TrackingFacturasListadoFiltros::SEGMENTO_TODOS;
    $criterio = TrackingFacturasListadoFiltros::segmentos()[$segmentoActivo]['label'] ?? 'Todos';

    $rango = '';
    if (($filtros['fecha_desde'] ?? '') !== '' || ($filtros['fecha_hasta'] ?? '') !== '') {
        $eje = TrackingFacturasListadoFiltros::ejesFecha()[$filtros['eje_fecha'] ?? ''] ?? '';
        $rango = sprintf(
            ' — %s: %s a %s',
            $eje,
            ($filtros['fecha_desde'] ?? '') !== '' ? \Carbon\Carbon::parse($filtros['fecha_desde'])->format('d/m/Y') : 'inicio',
            ($filtros['fecha_hasta'] ?? '') !== '' ? \Carbon\Carbon::parse($filtros['fecha_hasta'])->format('d/m/Y') : 'hoy',
        );
    }
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        {{-- Fila reservada: el export inserta los logos de las empresas acá. --}}
        <tr><td></td></tr>
    @endif
    <tr>
        <td>Tracking de facturas — {{ $criterio }}{{ $rango }} (generado {{ date('d/m/Y H:i') }})</td>
    </tr>
    <tr>
        <th>ID</th>
        <th>Empresa</th>
        <th>Proveedor</th>
        <th>CUIT</th>
        <th>Tipo</th>
        <th>Comprobante</th>
        <th>F. comprobante</th>
        <th>F. carga</th>
        <th>Origen de la fecha</th>
        <th>F. contabilización</th>
        <th>Asiento</th>
        <th>OC</th>
        <th>Importe</th>
        <th>Saldo</th>
        <th>Estado</th>
        <th>Pago</th>
        <th>Orden de pago</th>
        <th>PDF</th>
    </tr>
    @foreach ($datas as $data)
        @php
            $fila = TrackingFacturaFila::de($data);
            $estado = $fila->estadoContable();
            $pago = $fila->estadoPago();
        @endphp
        <tr>
            <td>{{ $fila->id() }}</td>
            <td>{{ $fila->empresa() }}</td>
            <td>{{ $fila->proveedor() }}</td>
            <td>{{ $fila->cuit() }}</td>
            <td>{{ $fila->familia() }} / {{ $fila->tipoAbreviatura() }}</td>
            <td>{{ $fila->numero() }}</td>
            <td>{{ $fila->fechaComprobante() }}</td>
            <td>{{ $fila->fechaCarga() }}</td>
            <td>{{ $fila->fechaCargaOrigen() }}</td>
            <td>{{ $fila->fechaContabilizacion() }}</td>
            <td>{{ $fila->numeroAsiento() ?: '' }}</td>
            <td>{{ $fila->numeroOrdencompra() }}</td>
            <td>{{ number_format($fila->total(), 2, ',', '.') }}</td>
            <td>{{ $fila->saldo() == 0 ? '' : number_format($fila->saldo(), 2, ',', '.') }}</td>
            <td>{{ $estado['etiqueta'] }}</td>
            <td>{{ $pago['etiqueta'] }}</td>
            <td>{{ $fila->ordenPago() }}{{ $fila->ordenesPagoExtra() > 0 ? ' (+'.$fila->ordenesPagoExtra().')' : '' }}</td>
            <td>{{ $fila->puedeVerPdf() ? $fila->pdfOrigen() : ($fila->indexado() ? 'Falta' : 'Sin resolver') }}</td>
        </tr>
    @endforeach
</table>
