@php
    $totales = $totales ?? App\Support\Ventas\RemitoListadoSupport::totalesRemito($remito);
    $fecha = $remito['fecha'] ?? $remito->fecha ?? null;
    $fechaEntrega = $remito['fechaentrega'] ?? $remito->fechaentrega ?? null;
@endphp
<tr>
    <td>{{ $remito['id'] ?? $remito->id ?? '' }}</td>
    <td>{{ $remito['codigo'] ?? $remito->codigo ?? '' }}</td>
    <td>
        @if ($fecha)
            {{ date('d/m/Y', strtotime((string) $fecha)) }}
        @endif
    </td>
    <td>
        @if ($fechaEntrega)
            {{ date('d/m/Y', strtotime((string) $fechaEntrega)) }}
        @endif
    </td>
    <td>{{ $remito['nombrecliente'] ?? $remito->nombrecliente ?? '' }}</td>
    <td>{{ $totales['caja'] }}</td>
    <td>{{ $totales['pieza'] }}</td>
    <td>{{ $totales['kilo'] }}</td>
    <td>{{ $remito->nombretransporte ?? '' }}</td>
    <td>{{ $remito['estado'] ?? $remito->estado ?? '' }}</td>
</tr>
