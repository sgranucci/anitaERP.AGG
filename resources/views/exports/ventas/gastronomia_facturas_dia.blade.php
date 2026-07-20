@php
    $registros = $registros ?? collect();
    $esExcel = ! empty($esExcel);
    $subtitulo = 'Generado '.date('d/m/Y H:i').' — '.(is_countable($registros) ? count($registros) : 0).' registro(s)';
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
            <td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Facturas gastronomía del día</h2></td>
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
            <th>Cliente</th>
            <th>Mozo</th>
            <th>Punto de venta</th>
            <th>Total</th>
            <th>Cuenta gastro.</th>
            <th>PC emisión</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($registros as $r)
            @php
                $v = $r->venta;
                $pvTxt = $v ? trim(($v->puntoventas->codigo ?? '').' '.($v->puntoventas->nombre ?? '')) : '';
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
                <td>{{ $v ? \App\Support\Ventas\GastronomiaVentaDisplaySupport::nombreReceptorFactura($v) : '—' }}</td>
                <td>{{ $r->cuenta?->mozo?->nombre ?? '—' }}</td>
                <td>{{ $pvTxt !== '' ? $pvTxt : '—' }}</td>
                <td>{{ $fmtMonto($v?->total ?? 0) }}</td>
                <td>{{ $r->cuenta_gastronomia_id ?? '—' }}</td>
                <td>{{ $r->identificador_pc ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
