<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="8" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Facturas gastronomía del día</h2></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>Venta ID</th>
            <th>Fecha</th>
            <th>Comprobante</th>
            <th>Cliente</th>
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
                <td>{{ $v?->fecha ? \Illuminate\Support\Carbon::parse($v->fecha)->format('d/m/Y H:i') : '—' }}</td>
                <td>{{ $v?->codigo ?? '—' }}</td>
                <td>{{ $v?->clientes->nombre ?? '—' }}</td>
                <td>{{ $pvTxt !== '' ? $pvTxt : '—' }}</td>
                <td>{{ number_format((float) ($v?->total ?? 0), 2, ',', '.') }}</td>
                <td>{{ $r->cuenta_gastronomia_id ?? '—' }}</td>
                <td>{{ $r->identificador_pc ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
