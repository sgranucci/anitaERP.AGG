@php
    $esDefault = ! empty($forzar_default) || ($fila && $fila->centrocosto_id === null);
    $pctDefault = \App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport::PCT_DEFAULT;
    $pct = old('tolerancias.'.$indice.'.tolerancia_importe_pct', $fila->tolerancia_importe_pct ?? ($esDefault ? $pctDefault : 0));

    // Sin fila guardada, el default replica el comportamiento simétrico histórico.
    $crudo = $fila ? $fila->getAttributes() : [];
    $limite = function (string $campo, $porDefecto) use ($indice, $crudo) {
        $valor = old('tolerancias.'.$indice.'.'.$campo, $crudo[$campo] ?? $porDefecto);

        return $valor === null ? '' : $valor;
    };
    $excesoPct = $limite('tolerancia_exceso_pct', $esDefault ? $pctDefault : null);
    $defectoPct = $limite('tolerancia_defecto_pct', $esDefault ? $pctDefault : null);
    $excesoAbs = $limite('tolerancia_exceso_abs', null);
    $defectoAbs = $limite('tolerancia_defecto_abs', null);
    $accion = old(
        'tolerancias.'.$indice.'.accion_fuera_tolerancia',
        $crudo['accion_fuera_tolerancia'] ?? \App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport::ACCION_DEVOLVER_COMPRAS
    );
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
        <input type="hidden" name="tolerancias[{{ $indice }}][tolerancia_importe_pct]" value="{{ $pct }}">
        <input type="number" step="0.01" min="0" max="100"
            name="tolerancias[{{ $indice }}][tolerancia_exceso_pct]"
            class="form-control form-control-sm text-right"
            placeholder="no verificar"
            value="{{ $excesoPct }}">
    </td>
    <td>
        <input type="number" step="0.01" min="0"
            name="tolerancias[{{ $indice }}][tolerancia_exceso_abs]"
            class="form-control form-control-sm text-right"
            placeholder="no verificar"
            value="{{ $excesoAbs }}">
    </td>
    <td>
        <input type="number" step="0.01" min="0" max="100"
            name="tolerancias[{{ $indice }}][tolerancia_defecto_pct]"
            class="form-control form-control-sm text-right"
            placeholder="no verificar"
            value="{{ $defectoPct }}">
    </td>
    <td>
        <input type="number" step="0.01" min="0"
            name="tolerancias[{{ $indice }}][tolerancia_defecto_abs]"
            class="form-control form-control-sm text-right"
            placeholder="no verificar"
            value="{{ $defectoAbs }}">
    </td>
    <td>
        <select name="tolerancias[{{ $indice }}][accion_fuera_tolerancia]" class="form-control form-control-sm">
            <option value="DEVOLVER_COMPRAS" @selected($accion === 'DEVOLVER_COMPRAS')>
                No dejar cargar y devolver a Compras
            </option>
            <option value="BLOQUEAR_PAGO" @selected($accion === 'BLOQUEAR_PAGO')>
                Cargar y bloquear para pago
            </option>
        </select>
    </td>
    <td class="text-center">
        @if (! $esDefault)
            <button type="button" class="btn-accion-tabla tooltipsC js-quitar-tolerancia-cp" title="Quitar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        @endif
    </td>
</tr>
