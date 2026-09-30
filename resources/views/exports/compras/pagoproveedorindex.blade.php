@php
    $esExcel = ! empty($esExcel);
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtMonto = function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if ($esExcel && $autoExcelNum) {
            return number_format($n, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
        }

        return number_format($n, 2, ',', '.');
    };
@endphp
<table>
@if (!empty($reservarFilaLogoExcel))
    <tr><td colspan="9" style="height:52px;"></td></tr>
@endif
    <tr>
        <td colspan="9"><strong style="font-size:16pt;">Órdenes de pago a proveedores</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>OP</th>
            <th>Empresa</th>
            <th>Proveedor</th>
            <th>Cuentas de caja</th>
            <th>Monto</th>
            <th>Estado</th>
            <th>Detalle</th>
            <th>Mail</th>
        </tr>
    </thead>
    <tbody>
        @foreach($datas as $fila)
            <tr>
                <td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
                <td>
                    {{ $fila->etiquetaComprobante() }}
                    @if ($fila instanceof \App\Support\Compras\PagoproveedorListadoFila && $fila->esIeOpp())
                        (IE)
                    @endif
                </td>
                <td>{{ $fila->empresas->nombre ?? '' }}</td>
                <td>{{ $fila->proveedores->nombre ?? '' }}</td>
                <td>
                    @if ($fila instanceof \App\Support\Compras\PagoproveedorListadoFila)
                        {{ implode(' | ', $fila->cuentasCajaLista()) }}
                    @endif
                </td>
                <td>{{ $fmtMonto($fila->monto) }}</td>
                <td>{{ $fila->estado }}</td>
                <td>{{ $fila instanceof \App\Support\Compras\PagoproveedorListadoFila
                    ? $fila->detalleIndicativo()
                    : $fila->detalle }}</td>
                <td>
                    @if ($fila instanceof \App\Support\Compras\PagoproveedorListadoFila && ! $fila->esIeOpp())
                        {{ $fila->mailEnviado ? 'Enviado' : 'Sin enviar' }}
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
