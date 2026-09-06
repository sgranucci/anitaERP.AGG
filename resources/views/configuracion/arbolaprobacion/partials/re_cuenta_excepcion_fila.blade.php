@php
    $exc = $exc ?? null;
    $empresaDefault = (int) ($empresa_default_id ?? 0);
    $activoSel = strtoupper((string) ($exc->activo ?? 'S')) === 'N' ? 'N' : 'S';
    $cuentaCodigo = $exc->cuentacontables->codigo ?? ($exc->cuenta_codigo ?? '');
    $cuentaNombre = $exc->cuentacontables->nombre ?? ($exc->cuenta_nombre ?? '');
@endphp
<tr class="fila-re-exc">
    <td>
        <input type="hidden" name="re_exc_ids[]" value="{{ $exc->id ?? '' }}">
        <select name="re_exc_centrocosto_ids[]" class="form-control form-control-sm" required>
            <option value="">-- CC --</option>
            @foreach($centrocosto_query as $cc)
                <option value="{{ $cc->id }}" {{ (int) ($exc->centrocosto_id ?? 0) === (int) $cc->id ? 'selected' : '' }}>
                    {{ $cc->codigo }} - {{ $cc->nombre }}
                </option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="re_exc_empresa_ids[]" class="form-control form-control-sm empresa" required>
            <option value="">-- Empresa --</option>
            @foreach($empresa_query as $emp)
                <option value="{{ $emp->id }}" {{ (int) ($exc->empresa_id ?? $empresaDefault) === (int) $emp->id ? 'selected' : '' }}>
                    {{ $emp->nombre }}
                </option>
            @endforeach
        </select>
    </td>
    <td colspan="2">
        <div class="tm-cuentacontable-campo d-flex flex-nowrap align-items-center" style="gap: 4px;" data-cuentacontable-campo="1">
            <input type="hidden" class="cuentacontable_id" name="re_exc_cuentacontable_ids[]" value="{{ $exc->cuentacontable_id ?? '' }}">
            <input type="hidden" class="cuentacontable_id_previa" value="{{ $exc->cuentacontable_id ?? '' }}">
            <input type="hidden" class="codigo_previo" value="{{ $cuentaCodigo }}">
            <button type="button" title="Consulta cuentas (F1)" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="form-control form-control-sm codigocuentacontable" name="re_exc_cuenta_codigos[]"
                   value="{{ $cuentaCodigo }}" placeholder="Cód." autocomplete="off"
                   title="Código + Enter; F1 o lupa para buscar" style="width: 7.5rem; flex-shrink: 0;">
            <input type="text" class="form-control form-control-sm nombrecuentacontable text-truncate" name="re_exc_cuenta_nombres[]"
                   value="{{ $cuentaNombre }}" placeholder="Descripción" readonly style="min-width: 0; flex: 1 1 auto;">
        </div>
    </td>
    <td class="text-center">
        <select name="re_exc_activos[]" class="form-control form-control-sm">
            <option value="S" {{ $activoSel === 'S' ? 'selected' : '' }}>S</option>
            <option value="N" {{ $activoSel === 'N' ? 'selected' : '' }}>N</option>
        </select>
    </td>
    <td>
        <button type="button" class="btn-accion-tabla eliminar_re_exc tooltipsC" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
