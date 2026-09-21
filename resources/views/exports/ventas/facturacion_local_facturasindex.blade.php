@php
    $esExcel = ! empty($esExcel);
    $registros = $registros ?? collect();
    $parts = ['Generado '.date('d/m/Y H:i')];
    if (! empty($desde) || ! empty($hasta)) {
        $parts[] = 'Desde '.($desde ?? '').' hasta '.($hasta ?? '');
    }
    if (! empty($localNombre)) {
        $parts[] = 'Local: '.$localNombre;
    }
    $parts[] = (is_countable($registros) ? count($registros) : 0).' registro(s)';
    $subtitulo = implode(' — ', $parts);
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
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="9" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Facturas Local</h2></td>
        </tr>
        <tr>
            <td colspan="9"><strong>{{ $subtitulo }}</strong></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>Venta ID</th>
            <th>Fecha</th>
            <th>Comprobante</th>
            <th>Local</th>
            <th>Cliente</th>
            <th>Punto de venta</th>
            <th>Total</th>
            <th>NC</th>
            <th>CAE</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($registros as $r)
            @php
                $v = $r->venta;
                $pvTxt = $v ? trim(($v->puntoventas->codigo ?? '').' '.($v->puntoventas->nombre ?? '')) : '';
                $clienteTxt = $v?->nombre ?: ($v?->clientes?->nombre ?? '—');
            @endphp
            <tr>
                <td>{{ $r->venta_id }}</td>
                <td>
                    @if ($v?->fecha)
                        {{ \Illuminate\Support\Carbon::parse($v->fecha)->format('d-m-Y') }}
                        @if ($v->created_at)
                            {{ ' '.$v->created_at->format('H:i:s') }}
                        @endif
                    @else
                        —
                    @endif
                </td>
                <td>{{ $v?->codigo ?? '—' }}</td>
                <td>{{ trim(($r->localVenta->codigo ?? '').' '.($r->localVenta->nombre ?? '')) ?: '—' }}</td>
                <td>{{ $clienteTxt }}</td>
                <td>{{ $pvTxt !== '' ? $pvTxt : '—' }}</td>
                <td>{{ $fmtMonto($v?->total ?? 0) }}</td>
                <td>{{ $r->ventaNc?->codigo ?? ($r->venta_nc_id ?? '—') }}</td>
                <td>{{ $v?->cae ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
