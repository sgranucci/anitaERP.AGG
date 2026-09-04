@php
    $esDefault = ! empty($forzar_default) || ($fila && $fila->centrocosto_id === null);
    $pctDefault = \App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport::PCT_DEFAULT;
    $pct = old('tolerancias.'.$indice.'.tolerancia_importe_pct', $fila->tolerancia_importe_pct ?? ($esDefault ? $pctDefault : 0));
@endphp
<tr class="item-tolerancia-cp">
    <td>
        @if ($esDefault)
            <input type="hidden" name="tolerancias[{{ $indice }}][es_default]" value="1">
            <input type="hidden" name="tolerancias[{{ $indice }}][centrocosto_id]" value="">
            <span class="badge badge-secondary">Default (resto de CC)</span>
        @else
            <select name="tolerancias[{{ $indice }}][centrocosto_id]" class="form-control form-control-sm" required>
                <option value="">Seleccione…</option>
                @foreach ($centrocosto_query as $cc)
                    <option value="{{ $cc->id }}" @selected((int) old('tolerancias.'.$indice.'.centrocosto_id', $fila->centrocosto_id ?? 0) === (int) $cc->id)>
                        {{ $cc->codigo }} — {{ $cc->nombre }}
                    </option>
                @endforeach
            </select>
        @endif
    </td>
    <td>
        <input type="number" step="0.01" min="0" max="100"
            name="tolerancias[{{ $indice }}][tolerancia_importe_pct]"
            class="form-control form-control-sm text-right"
            value="{{ $pct }}">
    </td>
    <td class="text-center">
        @if (! $esDefault)
            <button type="button" class="btn-accion-tabla tooltipsC js-quitar-tolerancia-cp" title="Quitar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        @endif
    </td>
</tr>
