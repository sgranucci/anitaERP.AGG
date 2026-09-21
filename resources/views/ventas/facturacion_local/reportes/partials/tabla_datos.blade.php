@php
    $abiertoTalle = $abierto_talle ?? ($resultado['abierto_talle'] ?? true);
    $incluirCosto = $incluir_costo ?? ($resultado['incluir_costo'] ?? false);
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
    /** Muestra 0,00 (no vacío) para columnas de costo cuando se pidió valorizar. */
    $fmtCosto = static function ($v) {
        return number_format((float) $v, 2, ',', '.');
    };
    $colspan = 5 + ($abiertoTalle ? 1 : 0) + ($incluirCosto ? 3 : 0);
@endphp
<thead style="background:#85C1E9;color:#17202A;">
    <tr>
        <th>Artículo</th>
        <th>Descripción</th>
        <th>Combinación / color</th>
        @if ($abiertoTalle)
            <th>Talle</th>
        @endif
        <th class="text-right">Cantidad</th>
        @if ($incluirCosto)
            <th class="text-right">P.Vta.</th>
            <th class="text-right">P.Costo</th>
        @endif
        <th class="text-right">Importe venta</th>
        @if ($incluirCosto)
            <th class="text-right">Importe costo</th>
        @endif
    </tr>
</thead>
<tbody>
    @forelse ($filas ?? [] as $fila)
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
            <td>{{ $fila['combinacion_color'] ?? '—' }}</td>
            @if ($abiertoTalle)
                <td>{{ $fila['talle'] ?? '—' }}</td>
            @endif
            <td class="text-right">{{ $fmtCant($fila['cantidad'] ?? 0) }}</td>
            @if ($incluirCosto)
                <td class="text-right">{{ $fmtImp($fila['precio_venta'] ?? 0) }}</td>
                <td class="text-right @if (! empty($fila['sin_precio_fabrica'])) text-muted @endif"
                    @if (! empty($fila['sin_precio_fabrica'])) title="Sin precio en listas fábrica (tiponumeración)" @endif>
                    {{ $fmtCosto($fila['precio_costo'] ?? 0) }}
                </td>
            @endif
            <td class="text-right">{{ $fmtImp($fila['importe'] ?? 0) }}</td>
            @if ($incluirCosto)
                <td class="text-right @if (! empty($fila['sin_precio_fabrica'])) text-muted @endif">
                    {{ $fmtCosto($fila['importe_costo'] ?? 0) }}
                </td>
            @endif
        </tr>
    @empty
        <tr>
            <td colspan="{{ $colspan }}" class="text-center text-muted py-4">Sin ventas para los filtros indicados.</td>
        </tr>
    @endforelse
</tbody>
@if (! empty($totales ?? []) && count($filas ?? []) > 0)
    @php
        $colsLabel = 3 + ($abiertoTalle ? 1 : 0); // art + desc + comb + [talle]
    @endphp
    <tfoot>
        <tr class="table-active font-weight-bold">
            <td colspan="{{ $colsLabel }}" class="text-right">Totales</td>
            <td class="text-right">{{ $fmtCant($totales['cantidad'] ?? 0) }}</td>
            @if ($incluirCosto)
                <td></td>
                <td></td>
            @endif
            <td class="text-right">{{ $fmtImp($totales['importe'] ?? 0) }}</td>
            @if ($incluirCosto)
                <td class="text-right">{{ $fmtCosto($totales['importe_costo'] ?? 0) }}</td>
            @endif
        </tr>
    </tfoot>
@endif
