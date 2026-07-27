@php
    $empresaId = $fila->empresa_id ?? optional($fila->empresas)->id ?? null;
    $cuenta = $fila->cuentacontables ?? null;
    $ccostoId = $fila->centrocosto_id ?? optional($fila->centrocostos)->id ?? null;
    $dh = strtoupper((string) ($fila->debe_haber ?? 'D')) === 'H' ? 'H' : 'D';
    $monto = (float) ($fila->monto ?? 0);
    $montoDebe = $fila->monto_debe ?? ($dh === 'D' ? $monto : 0);
    $montoHaber = $fila->monto_haber ?? ($dh === 'H' ? $monto : 0);
@endphp
<tr class="item-sp-cuenta">
    <td>
        @include('includes.form-empresa-asignada-control', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $empresaId,
            'name' => 'empresa_ids[]',
            'select_class' => 'empresa',
            'permite_vacio' => true,
            'opcion_vacia' => '-- Empresa --',
            'required' => false,
        ])
    </td>
    <td>
        <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
            <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{ $fila->cuentacontable_id ?? optional($cuenta)->id }}">
            <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="{{ $fila->cuentacontable_id ?? optional($cuenta)->id }}">
            <input type="hidden" class="monto_cuenta" name="montos_cuenta[]" value="{{ $monto }}">
            <button type="button" title="Consulta cuentas" style="padding:1; flex: 0 0 auto;"
                    class="btn-accion-tabla consultacuentacontable tooltipsC">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" style="flex: 0 0 100px; width: 100px; height: 38px;"
                   class="codigocuentacontable form-control" name="codigos[]"
                   value="{{ optional($cuenta)->codigo ?? '' }}">
            <input type="text" style="flex: 1 1 auto; min-width: 0; height: 38px;"
                   class="nombrecuentacontable form-control" name="nombres[]"
                   value="{{ optional($cuenta)->nombre ?? '' }}">
            <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="{{ optional($cuenta)->codigo ?? '' }}">
        </div>
    </td>
    <td>
        <select name="centrocosto_ids[]" class="form-control centrocosto">
            <option value="">-- Sin CC --</option>
            @foreach ($centrocosto_query as $cc)
                <option value="{{ $cc->id }}" @selected((int) $ccostoId === (int) $cc->id)>
                    {{ $cc->codigo }} — {{ $cc->nombre }}
                </option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="debe_haberes[]" class="form-control debe_haber text-center" title="Debe / Haber">
            <option value="D" @selected($dh === 'D')>D</option>
            <option value="H" @selected($dh === 'H')>H</option>
        </select>
    </td>
    <td>
        <input type="number" step="0.01" min="0" name="montos_debe[]"
               class="form-control text-right monto-debe{{ $dh !== 'D' ? ' bg-light' : '' }}"
               value="{{ $dh === 'D' && $montoDebe > 0 ? $montoDebe : '' }}"
               placeholder="0"
               @if($dh !== 'D') readonly @endif>
    </td>
    <td>
        <input type="number" step="0.01" min="0" name="montos_haber[]"
               class="form-control text-right monto-haber{{ $dh !== 'H' ? ' bg-light' : '' }}"
               value="{{ $dh === 'H' && $montoHaber > 0 ? $montoHaber : '' }}"
               placeholder="0"
               @if($dh !== 'H') readonly @endif>
    </td>
    <td class="text-center">
        <button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla eliminar_sp_cuenta tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
