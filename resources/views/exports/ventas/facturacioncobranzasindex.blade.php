@php
    $colspan = 15;
@endphp
<table>
@if (! empty($reservarFilaLogoExcel))
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
        </tr>
    </tbody>
@endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}"><strong style="font-size: 16px;">{{ $titulo ?? 'Facturación y cobranzas' }}</strong></td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">{{ $subtitulo ?? '' }}</td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">Movimientos: {{ (int) ($total_lineas ?? 0) }}</td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>Tip</th>
            <th>Número</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Nombre</th>
            <th>Kilos</th>
            <th>Gravado</th>
            <th>Varios</th>
            <th>Total comp.</th>
            <th>Efectivo</th>
            <th>Cheques</th>
            <th>Valores</th>
            <th>Varios cobro</th>
            <th>Total cobro</th>
            <th>Fuente</th>
        </tr>
    </thead>
    @include('ventas.facturacion_cobranzas.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'para_excel' => true,
        'para_pdf' => true,
        'solo_tbody' => true,
        'puede_ver_venta' => false,
        'puede_ver_cliente' => false,
        'puede_ver_cobranza' => false,
    ])
</table>
