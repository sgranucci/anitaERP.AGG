@php
    $empresaId = $fila->empresa_id ?? optional($fila->empresas)->id ?? null;
    $cuenta = $fila->cuentacontables ?? null;
    $ccostoId = $fila->centrocosto_id ?? optional($fila->centrocostos)->id ?? null;
    $dh = strtoupper((string) ($fila->debe_haber ?? 'D'));
    $monto = $fila->monto ?? 0;
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
        <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;" id="cuenta">
            <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{ $fila->cuentacontable_id ?? optional($cuenta)->id }}">
            <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="{{ $fila->cuentacontable_id ?? optional($cuenta)->id }}">
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
        <select name="debe_haberes[]" class="form-control debe_haber">
            <option value="D" @selected($dh === 'D')>Debe</option>
            <option value="H" @selected($dh === 'H')>Haber</option>
        </select>
    </td>
    <td>
        <input type="number" step="0.01" name="montos_cuenta[]" class="form-control" value="{{ $monto }}">
    </td>
    <td class="text-center">
        <button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla eliminar_sp_cuenta tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
