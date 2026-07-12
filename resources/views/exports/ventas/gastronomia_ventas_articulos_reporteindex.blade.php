@php
    $filas = $resultado['filas'] ?? [];
    $totales = $resultado['totales'] ?? [];
    $fmtCant = static function ($v) {
        $v = (float) $v;
        if (abs($v) <= 0.0001) {
            return '';
        }

        return number_format($v, abs($v - round($v)) <= 0.0001 ? 0 : 2, ',', '.');
    };
    $fmtImp = static function ($v) {
        $v = (float) $v;
        if (abs($v) <= 0.0001) {
            return '';
        }

        return number_format($v, 2, ',', '.');
    };
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="12" style="height: 52px;"></td></tr>
    @endif
    <tr>
        <td colspan="12"><strong style="font-size: 16px;">{{ $titulo ?? 'Ventas de artículos' }}</strong></td>
    </tr>
    @if (! empty($subtitulo))
        <tr><td colspan="12">{{ $subtitulo }}</td></tr>
    @endif
    <tr><td colspan="12">Generado {{ date('d/m/Y H:i') }}</td></tr>
    <tr>
        <th>Artículo</th>
        <th>Descripción</th>
        <th style="text-align: right;">Costo unit.</th>
        <th style="text-align: right;">P.Vta.</th>
        <th style="text-align: right;">Cantidad vendida tot.</th>
        <th style="text-align: right;">Cantidad vta. externa</th>
        <th style="text-align: right;">Importe total vta. externa</th>
        <th style="text-align: right;">Cantidad invitaciones</th>
        <th style="text-align: right;">Cantidad vta. staff</th>
        <th style="text-align: right;">Venta interna al costo</th>
        <th style="text-align: right;">Venta interna a precio vta.</th>
        <th style="text-align: right;">Venta externa a precio costo</th>
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila['sku'] ?? '' }}</td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            <td style="text-align: right;">{{ $fmtImp($fila['costo_unitario'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtImp($fila['precio_venta'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtCant($fila['cant_total'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtCant($fila['cant_externa'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtImp($fila['importe_externa'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtCant($fila['cant_invitacion'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtCant($fila['cant_staff'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtImp($fila['venta_interna_costo'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtImp($fila['venta_interna_precio_vta'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtImp($fila['venta_externa_costo'] ?? 0) }}</td>
        </tr>
    @endforeach
    @if ($totales !== [])
        <tr>
            <td colspan="4" style="text-align: right;"><strong>Totales</strong></td>
            <td style="text-align: right;">{{ $fmtCant($totales['cant_total'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtCant($totales['cant_externa'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtImp($totales['importe_externa'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtCant($totales['cant_invitacion'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtCant($totales['cant_staff'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtImp($totales['venta_interna_costo'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtImp($totales['venta_interna_precio_vta'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtImp($totales['venta_externa_costo'] ?? 0) }}</td>
        </tr>
    @endif
</table>
