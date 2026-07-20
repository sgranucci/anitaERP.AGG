@php
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<table>
    @if ($reservarFilaLogoExcel)
        <tr><td></td></tr>
    @endif
    <tr><td colspan="8">Entregas de indumentaria — Generado {{ now()->format('d/m/Y H:i') }}</td></tr>
    <tr>
        <th>Legajo</th>
        <th>Empleado</th>
        <th>Fecha</th>
        <th>Prenda</th>
        <th>Color</th>
        <th>Talle</th>
        <th>SKU</th>
        <th>Cantidad</th>
    </tr>
    @foreach ($datos as $r)
        <tr>
            <td>{{ $r->legajo }}</td>
            <td>{{ $r->empleado_nombre }}</td>
            <td>{{ \Carbon\Carbon::parse($r->fecha)->format('d/m/Y') }}</td>
            <td>{{ $r->prenda_codigo }} - {{ $r->prenda }}</td>
            <td>{{ $r->color }}</td>
            <td>{{ $r->talle }}</td>
            <td>{{ $r->sku }}</td>
            <td>{{ $fmt($r->cantidad) }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="7" style="text-align:right"><strong>Total unidades</strong></td>
        <td><strong>{{ $fmt($totalCantidad) }}</strong></td>
    </tr>
</table>
