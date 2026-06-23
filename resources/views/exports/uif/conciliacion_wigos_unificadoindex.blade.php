@php
    $fmtMonto = static fn ($v) => '$ '.number_format((float) $v, 2, ',', '.');
    $totalMonto = $filas->sum(fn ($f) => (float) ($f->monto ?? 0));
@endphp
<table>
    @if (!empty($rutasLogosExcel))
        <tr>
            @foreach ($rutasLogosExcel as $logoPath)
                <td><img src="{{ $logoPath }}" height="48"></td>
            @endforeach
        </tr>
    @endif
    <tr>
        <td colspan="7" style="font-weight:bold;font-size:14pt;text-align:center;">{{ $titulo }}</td>
    </tr>
    @if (!empty($subtitulo))
        <tr>
            <td colspan="7" style="text-align:center;">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <th>Fecha pago</th>
        <th>Fecha emisión</th>
        <th>Monto</th>
        <th>Terminal</th>
        <th>Número</th>
        <th>Origen</th>
        <th>Estado</th>
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila->fecha_pago ? $fila->fecha_pago->format('d/m/Y H:i') : '' }}</td>
            <td>{{ $fila->fecha_emision ? $fila->fecha_emision->format('d/m/Y H:i') : '' }}</td>
            <td>{{ $fila->monto !== null ? $fmtMonto($fila->monto) : '' }}</td>
            <td>{{ $fila->terminal }}</td>
            <td>{{ $fila->numero }}</td>
            <td>{{ $fila->origen }}</td>
            <td>{{ $fila->estado_conciliacion }}</td>
        </tr>
    @endforeach
    @if ($totalMonto > 0)
        <tr>
            <td colspan="2" style="font-weight:bold;text-align:right;">Total premios</td>
            <td style="font-weight:bold;">{{ $fmtMonto($totalMonto) }}</td>
            <td colspan="4"></td>
        </tr>
    @endif
</table>
