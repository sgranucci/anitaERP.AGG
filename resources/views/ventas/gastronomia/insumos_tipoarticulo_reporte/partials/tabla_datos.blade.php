@php
    $columnasDias = $resultado['columnas_dias'] ?? [];
    $filasTabla = $filas ?? ($resultado['filas'] ?? []);
    $totalesPorDia = $resultado['totales_por_dia'] ?? [];
    $totalGeneral = (float) ($resultado['total_general'] ?? 0);
    $mostrarTotales = $mostrar_totales ?? true;
@endphp
<thead>
    <tr>
        <th>SKU</th>
        <th>Descripción</th>
        @foreach ($columnasDias as $col)
            <th class="text-right" title="{{ \Carbon\Carbon::parse($col['ymd'])->format('d/m/Y') }}">{{ $col['label'] }}</th>
        @endforeach
        <th class="text-right">Total</th>
    </tr>
</thead>
<tbody>
    @forelse ($filasTabla as $fila)
        <tr>
            <td>
                @if (($puede_ver_articulo ?? false) && ! empty($fila['articulo_id']))
                    <a href="{{ route('editar_articulo', ['id' => $fila['articulo_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       class="text-primary"
                       target="_blank"
                       rel="noopener">{{ $fila['sku'] ?? '' }}</a>
                @else
                    {{ $fila['sku'] ?? '' }}
                @endif
            </td>
            <td><small>{{ $fila['descripcion'] ?? '—' }}</small></td>
            @foreach ($columnasDias as $col)
                @php $cant = (float) ($fila['cantidades_por_dia'][$col['ymd']] ?? 0); @endphp
                <td class="text-right">
                    <small>{{ $cant != 0. ? number_format($cant, 3, ',', '.') : '' }}</small>
                </td>
            @endforeach
            <td class="text-right"><strong>{{ number_format((float) ($fila['total'] ?? 0), 3, ',', '.') }}</strong></td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ 3 + count($columnasDias) }}" class="text-center text-muted py-4">
                Sin ventas de insumos para los filtros indicados.
            </td>
        </tr>
    @endforelse
</tbody>
@if ($mostrarTotales && count($filasTabla) > 0)
    <tfoot>
        <tr class="font-weight-bold bg-light">
            <td colspan="2" class="text-right">Totales:</td>
            @foreach ($columnasDias as $col)
                <td class="text-right">{{ number_format((float) ($totalesPorDia[$col['ymd']] ?? 0), 3, ',', '.') }}</td>
            @endforeach
            <td class="text-right">{{ number_format($totalGeneral, 3, ',', '.') }}</td>
        </tr>
    </tfoot>
@endif
