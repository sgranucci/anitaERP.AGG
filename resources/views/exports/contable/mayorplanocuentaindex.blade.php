<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="16">&nbsp;</td></tr>
    @endif
    <tr>
        <td colspan="16" style="font-weight: bold; font-size: 14px;">{{ $titulo ?? 'Mayor analítico por cuenta contable' }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr><td colspan="16">{{ $subtitulo }}</td></tr>
    @endif
    <tr>
        <th>Fecha</th>
        <th>N.Asi.</th>
        <th>Tip</th>
        <th>Comprobante</th>
        <th>Emisor</th>
        <th>CUIT</th>
        <th>Descripción mov.</th>
        <th>O.Compra</th>
        <th>Mon</th>
        <th>Cotiz.</th>
        <th>Mon.Referencia</th>
        <th>Debe</th>
        <th>Haber</th>
        <th>Saldo del mes</th>
        <th>Saldo ejerc.</th>
        <th>Empr.</th>
    </tr>
    @foreach ($filas as $f)
        @php $fila = is_array($f) ? $f : (array) $f; $tipo = $fila['tipo_fila'] ?? 'detalle'; @endphp
        @if ($tipo === 'header_cuenta')
            <tr>
                <td colspan="16">Cuenta: {{ $fila['cuenta_codigo'] ?? '' }} {{ $fila['cuenta_nombre'] ?? '' }}</td>
            </tr>
        @elseif ($tipo === 'saldo_inicial')
            <tr>
                <td>Saldo Inicial</td>
                <td colspan="13"></td>
                <td>{{ $fila['saldo_ejercicio'] ?? '' }}</td>
                <td></td>
            </tr>
        @elseif ($tipo === 'total_cuenta')
            <tr>
                <td colspan="11">Total cuenta {{ $fila['cuenta_codigo'] ?? '' }}</td>
                <td>{{ $fila['debe'] ?? '' }}</td>
                <td>{{ $fila['haber'] ?? '' }}</td>
                <td colspan="2"></td>
            </tr>
        @else
            <tr>
                <td>{{ $fila['fecha_fmt'] ?? '' }}</td>
                <td>{{ $fila['nro_asiento_fmt'] ?? $fila['nro_asiento'] ?? '' }}</td>
                <td>{{ $fila['tipo_comp'] ?? '' }}</td>
                <td>{{ $fila['comprobante'] ?? '' }}</td>
                <td>{{ $fila['emisor'] ?? '' }}</td>
                <td>{{ $fila['cuit'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ (int) ($fila['nro_oc'] ?? 0) > 0 ? $fila['nro_oc'] : '' }}</td>
                <td>{{ $fila['moneda_abrev'] ?? '' }}</td>
                <td>{{ $fila['cotizacion'] ?? '' }}</td>
                <td>{{ $fila['mon_referencia'] ?? '' }}</td>
                <td>{{ $fila['debe'] ?? '' }}</td>
                <td>{{ $fila['haber'] ?? '' }}</td>
                <td>{{ $fila['saldo_mes'] ?? '' }}</td>
                <td>{{ $fila['saldo_ejercicio'] ?? '' }}</td>
                <td>{{ $fila['empresa_id'] ?? '' }}</td>
            </tr>
        @endif
    @endforeach
</table>
