<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="14" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="14"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Movimientos de stock</h2></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Naturaleza</th>
            <th>Tipo de transacci&oacute;n</th>
            <th>N&uacute;mero</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Lote</th>
            <th>Empresa</th>
            <th>Cantidad</th>
            <th>&Iacute;tems</th>
            <th>Estado</th>
            <th>Mov. salida</th>
            <th>Mov. ingreso</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $fila)
            @php
                $estadoLabel = $fila->esTransferencia()
                    ? ($fila->etiquetaEstadoTransferencia() ?? '')
                    : ($estado_enum[$fila->estadoMovimiento ?? ''] ?? ($fila->estadoMovimiento ?? ''));
            @endphp
            <tr>
                <td>{{ $fila->pkId }}</td>
                <td>{{ $fila->fecha ? date('d/m/Y', strtotime($fila->fecha)) : '' }}</td>
                <td>{{ $fila->esTransferencia() ? 'Transferencia' : 'Movimiento' }}</td>
                <td>{{ $fila->tipoNombre }}</td>
                <td>{{ $fila->codigoListado }}</td>
                <td>{{ $fila->esTransferencia() ? $fila->etiquetaOrigen() : '—' }}</td>
                <td>{{ $fila->esTransferencia() ? $fila->etiquetaDestino() : ($fila->depositoNombre ?? '—') }}</td>
                <td>{{ $fila->loteListado }}</td>
                <td>{{ $fila->nombreEmpresa }}</td>
                <td>{{ number_format($fila->totalCantidad, 2, ',', '.') }}</td>
                <td>{{ $fila->itemsCount > 0 ? $fila->itemsCount : '' }}</td>
                <td>{{ $estadoLabel }}</td>
                <td>{{ $fila->movSalidaId ?? '' }}</td>
                <td>{{ $fila->movEntradaId ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
