@php
    $centrocostoId = (int) ($tolerancia->centrocosto_id ?? 0);
    $cantidadPct = old('tolerancias.'.$indice.'.tolerancia_cantidad_pct', $tolerancia->tolerancia_cantidad_pct ?? 0);
    $precioPct = old('tolerancias.'.$indice.'.tolerancia_precio_pct', $tolerancia->tolerancia_precio_pct ?? 0);
    $precioAbs = old('tolerancias.'.$indice.'.tolerancia_precio_absoluto', $tolerancia->tolerancia_precio_absoluto ?? 0);
    $esNueva = $es_nueva ?? false;
    $cc = $tolerancia->centrocostos ?? null;
@endphp
<tr class="item-tolerancia-recepcion">
    <td class="align-middle">
        @if ($esNueva)
            <select name="tolerancias[{{ $indice }}][centrocosto_id]" class="form-control form-control-sm centrocosto-tolerancia-select" required>
                <option value="">-- Seleccionar centro de costo --</option>
                @foreach($centrocosto_query as $opcionCc)
                <option value="{{ $opcionCc->id }}" data-codigo="{{ $opcionCc->codigo }}">
                    {{ $opcionCc->codigo }} — {{ $opcionCc->nombre }}
                </option>
                @endforeach
            </select>
        @else
            <strong>{{ $cc->nombre ?? '—' }}</strong>
            <input type="hidden" name="tolerancias[{{ $indice }}][centrocosto_id]" value="{{ $centrocostoId }}">
        @endif
    </td>
    <td class="align-middle text-monospace text-muted codigo-centrocosto-celda">
        @if ($esNueva)
            <span class="codigo-centrocosto-texto">—</span>
        @else
            {{ $cc->codigo ?? '—' }}
        @endif
    </td>
    <td>
        <input type="number" step="0.0001" min="0" max="100" name="tolerancias[{{ $indice }}][tolerancia_cantidad_pct]" class="form-control form-control-sm text-right" value="{{ $cantidadPct }}">
    </td>
    <td>
        <input type="number" step="0.0001" min="0" max="100" name="tolerancias[{{ $indice }}][tolerancia_precio_pct]" class="form-control form-control-sm text-right" value="{{ $precioPct }}">
    </td>
    <td>
        <input type="number" step="0.01" min="0" name="tolerancias[{{ $indice }}][tolerancia_precio_absoluto]" class="form-control form-control-sm text-right" value="{{ $precioAbs }}">
    </td>
    <td class="text-center align-middle">
        <button type="button" title="Eliminar rengl&oacute;n" class="btn-accion-tabla eliminar-tolerancia tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
