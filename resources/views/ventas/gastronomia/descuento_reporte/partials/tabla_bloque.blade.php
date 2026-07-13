@php
    use App\Support\Ventas\GastronomiaDescuentoReporteTipoArticuloSupport;

    $tot = $bloque['totales'] ?? [];
    $grupos = $bloque['grupos'] ?? null;
    if ($grupos === null) {
        $agrupado = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas($bloque['filas'] ?? []);
        $grupos = $agrupado['grupos'];
    }
@endphp
<thead>
    <tr>
        <th>Artículo</th>
        <th>Descripción</th>
        <th class="text-right">Unidades</th>
        <th class="text-right">Costo unit.</th>
        <th class="text-right">Costo total</th>
        <th class="text-right">Precio vta.</th>
        <th class="text-right">Total venta</th>
    </tr>
</thead>
<tbody>
    @forelse ($grupos as $grupo)
        <tr class="grupo-tipo table-info font-weight-bold">
            <td colspan="7">
                Tipo: {{ $grupo['tipo_nombre'] }}
                ({{ $grupo['cantidad_lineas'] }} l&iacute;nea{{ $grupo['cantidad_lineas'] === 1 ? '' : 's' }})
            </td>
        </tr>
        @foreach ($grupo['filas'] as $fila)
            <tr>
                <td class="text-nowrap">
                    @if (($puede_ver_articulo ?? false) && (int) ($fila['articulo_id'] ?? 0) > 0)
                        <a href="{{ route('editar_articulo', ['id' => $fila['articulo_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           target="_blank" rel="noopener" class="text-primary">
                            {{ $fila['sku'] ?? '—' }}
                        </a>
                    @else
                        {{ $fila['sku'] ?? '—' }}
                    @endif
                </td>
                <td>{{ $fila['descripcion'] ?? '—' }}</td>
                <td class="text-right">{{ number_format((float) ($fila['unidades'] ?? 0), 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) ($fila['costo_unitario'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) ($fila['costo_total'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) ($fila['precio_venta'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) ($fila['total_venta'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="subtotal-tipo font-weight-bold" style="background-color: #f0f0f0;">
            <td colspan="2">Total parcial {{ $grupo['tipo_nombre'] }}</td>
            <td class="text-right">{{ number_format((float) ($grupo['subtotal_unidades'] ?? 0), 0, ',', '.') }}</td>
            <td></td>
            <td class="text-right">{{ number_format((float) ($grupo['subtotal_costo_total'] ?? 0), 2, ',', '.') }}</td>
            <td></td>
            <td class="text-right">{{ number_format((float) ($grupo['subtotal_total_venta'] ?? 0), 2, ',', '.') }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="text-center text-muted">Sin ventas con este descuento en el período.</td>
        </tr>
    @endforelse
</tbody>
@if ($grupos !== [])
    <tfoot>
        <tr class="table-active font-weight-bold">
            <td colspan="2">Total final</td>
            <td class="text-right">{{ number_format((float) ($tot['unidades'] ?? 0), 0, ',', '.') }}</td>
            <td></td>
            <td class="text-right">{{ number_format((float) ($tot['costo_total'] ?? 0), 2, ',', '.') }}</td>
            <td></td>
            <td class="text-right">{{ number_format((float) ($tot['total_venta'] ?? 0), 2, ',', '.') }}</td>
        </tr>
    </tfoot>
@endif
