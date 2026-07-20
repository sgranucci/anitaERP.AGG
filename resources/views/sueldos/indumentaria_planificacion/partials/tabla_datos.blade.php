@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
@endphp
<table class="table table-sm table-bordered table-hover mb-0" id="tabla-planificacion">
    <thead>
        <tr>
            <th>Código</th>
            <th>Prenda</th>
            <th class="text-center">EPP</th>
            <th class="num">Empleados</th>
            <th class="num">Cupo</th>
            <th class="num">Entregado</th>
            <th class="num">Pendiente</th>
            <th class="num">Stock</th>
            <th class="num">% Ped.</th>
            <th class="num">Sugerido a comprar</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $f)
            <tr>
                <td>{{ $f['codigo'] }}</td>
                <td>
                    {{ $f['descripcion'] }}
                    @if (! empty($f['es_seguridad']))<span class="badge badge-warning" title="{{ $f['norma'] ?? 'EPP' }}">EPP</span>@endif
                    @if (($f['modo'] ?? 'anual') === 'vencimiento')<small class="text-muted d-block">vida útil {{ $f['vida_util_meses'] }} m</small>@endif
                </td>
                <td class="text-center">{{ ! empty($f['es_seguridad']) ? 'Sí' : '' }}</td>
                <td class="num">{{ $f['empleados'] }}</td>
                <td class="num">{{ $fmt($f['cupo']) }}</td>
                <td class="num">{{ $fmt($f['entregado']) }}</td>
                <td class="num">{{ $fmt($f['pendiente']) }}</td>
                <td class="num">{{ $fmt($f['stock']) }}</td>
                <td class="num">{{ $fmt($f['porcentaje_pedido']) }}</td>
                <td class="num">
                    @if ($f['sugerido'] > 0)
                        <strong class="text-danger">{{ $f['sugerido'] }}</strong>
                    @else
                        <span class="text-muted">0</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="text-center text-muted">Sin datos para el filtro seleccionado.</td></tr>
        @endforelse
    </tbody>
    @if (! empty($filas))
        <tfoot>
            <tr style="font-weight:600; background:#f0f3f4;">
                <td colspan="4" class="text-right">Totales ({{ $totales['prendas'] }} prendas)</td>
                <td class="num">{{ $fmt($totales['cupo']) }}</td>
                <td class="num">{{ $fmt($totales['entregado']) }}</td>
                <td class="num">{{ $fmt($totales['pendiente']) }}</td>
                <td class="num">{{ $fmt($totales['stock']) }}</td>
                <td></td>
                <td class="num">{{ $totales['sugerido'] }}</td>
            </tr>
        </tfoot>
    @endif
</table>
