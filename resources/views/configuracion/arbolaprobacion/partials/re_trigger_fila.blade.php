@php
    use App\Support\Configuracion\ReArbolTriggerCatalog;
    $tr = $tr ?? null;
    $evalSel = (string) ($tr->evaluador ?? '');
    $usaAllowlist = ReArbolTriggerCatalog::usaAllowlist($evalSel);
    $usaMonto = ReArbolTriggerCatalog::usaMonto($evalSel);
    $usaCuenta = ReArbolTriggerCatalog::usaCuenta($evalSel);
    $hint = ReArbolTriggerCatalog::hintEvaluador($evalSel);
    $cuentaCodigo = $tr->cuentacontables->codigo ?? ($tr->param_cuenta_codigo ?? '');
    $cuentaNombre = $tr->cuentacontables->nombre ?? ($tr->param_cuenta_nombre ?? '');
    $vigDesde = $tr->vigencia_desde ?? null;
    $vigHasta = $tr->vigencia_hasta ?? null;
    if (is_object($vigDesde)) {
        $vigDesde = $vigDesde->format('Y-m-d');
    }
    if (is_object($vigHasta)) {
        $vigHasta = $vigHasta->format('Y-m-d');
    }
    $activoSel = strtoupper((string) ($tr->activo ?? 'S')) === 'N' ? 'N' : 'S';
    $filaEstadoClass = $activoSel === 'S' ? 'is-activo' : 'is-inactivo';
@endphp
<tr class="fila-re-trigger {{ $filaEstadoClass }}">
    <td>
        <input type="hidden" name="re_trigger_ids[]" value="{{ $tr->id ?? '' }}">
        <div class="d-flex align-items-center flex-wrap" style="gap:0.35rem;">
            <span class="re-trigger-estado-badge">{{ $activoSel === 'S' ? 'Activo' : 'Inactivo' }}</span>
        </div>
        <input type="text" name="re_trigger_nombres[]" class="form-control form-control-sm mt-1" value="{{ $tr->nombre ?? '' }}" placeholder="Nombre / política">
        <input type="text" name="re_trigger_observaciones[]" class="form-control form-control-sm mt-1" value="{{ $tr->observacion ?? '' }}" placeholder="Observación (opc.)">
    </td>
    <td>
        <select name="re_trigger_evaluadores[]" class="form-control form-control-sm re-trigger-evaluador" required>
            <option value="">—</option>
            @foreach(ReArbolTriggerCatalog::evaluadoresPorGrupo() as $grupo => $evals)
                <optgroup label="{{ $grupo }}">
                    @foreach($evals as $ev)
                        <option value="{{ $ev }}" {{ $evalSel === $ev ? 'selected' : '' }}
                            data-usa-allowlist="{{ ReArbolTriggerCatalog::usaAllowlist($ev) ? '1' : '0' }}"
                            data-usa-monto="{{ ReArbolTriggerCatalog::usaMonto($ev) ? '1' : '0' }}"
                            data-usa-cuenta="{{ ReArbolTriggerCatalog::usaCuenta($ev) ? '1' : '0' }}"
                            data-hint="{{ e(ReArbolTriggerCatalog::hintEvaluador($ev)) }}">
                            {{ ReArbolTriggerCatalog::etiquetaEvaluador($ev) }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <div class="re-trigger-hint small text-muted mt-1" style="{{ $hint !== '' ? '' : 'display:none;' }}">{{ $hint }}</div>
        <div class="re-trigger-allowlist-hint small text-muted mt-1" style="{{ $usaAllowlist ? '' : 'display:none;' }}">
            <button type="button" class="btn btn-link btn-sm p-0 align-baseline ir-a-allowlist">ver allowlist</button>
        </div>
        <div class="re-trigger-params-monto mt-1" style="{{ $usaMonto ? '' : 'display:none;' }}">
            <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                <input type="number" step="0.01" min="0" name="re_trigger_param_montos[]" class="form-control form-control-sm re-trigger-param-monto"
                       value="{{ $tr->param_monto ?? '' }}" placeholder="Umbral" style="width:7rem;">
                <select name="re_trigger_param_moneda_ids[]" class="form-control form-control-sm re-trigger-param-moneda" style="min-width:5rem;">
                    <option value="">Moneda</option>
                    @foreach($moneda_query as $mon)
                        <option value="{{ $mon->id }}" {{ (int) ($tr->param_moneda_id ?? 0) === (int) $mon->id ? 'selected' : '' }}>
                            {{ $mon->abreviatura }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="re-trigger-params-cuenta mt-1" style="{{ $usaCuenta ? '' : 'display:none;' }}">
            <div class="tm-cuentacontable-campo d-flex flex-nowrap align-items-center" style="gap:4px;" data-cuentacontable-campo="1">
                <input type="hidden" class="cuentacontable_id" name="re_trigger_param_cuentacontable_ids[]" value="{{ $tr->param_cuentacontable_id ?? '' }}">
                <input type="hidden" class="cuentacontable_id_previa" value="{{ $tr->param_cuentacontable_id ?? '' }}">
                <input type="hidden" class="codigo_previo" value="{{ $cuentaCodigo }}">
                <button type="button" title="Consulta cuentas (F1)" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigocuentacontable" name="re_trigger_param_cuenta_codigos[]"
                       value="{{ $cuentaCodigo }}" placeholder="Cód." autocomplete="off"
                       title="Código + Enter; F1 o lupa" style="width:6.5rem; flex-shrink:0;">
                <input type="text" class="form-control form-control-sm nombrecuentacontable text-truncate" name="re_trigger_param_cuenta_nombres[]"
                       value="{{ $cuentaNombre }}" placeholder="Cuenta" readonly style="min-width:0; flex:1 1 auto;">
            </div>
        </div>
    </td>
    <td>
        <select name="re_trigger_centrocosto_ids[]" class="form-control form-control-sm re-trigger-cc">
            <option value="">Todos</option>
            @foreach($centrocosto_query as $value)
                <option value="{{ $value->id }}" {{ (int) ($tr->centrocosto_id ?? 0) === (int) $value->id ? 'selected' : '' }}>
                    {{ $value->codigo }} — {{ $value->nombre }}
                </option>
            @endforeach
        </select>
        <div class="d-flex flex-nowrap align-items-center mt-1" style="gap:4px;">
            <input type="date" name="re_trigger_vigencia_desdes[]" class="form-control form-control-sm" value="{{ $vigDesde ?? '' }}" title="Vigencia desde" style="min-width:7.5rem;">
            <input type="date" name="re_trigger_vigencia_hastas[]" class="form-control form-control-sm" value="{{ $vigHasta ?? '' }}" title="Vigencia hasta" style="min-width:7.5rem;">
        </div>
    </td>
    <td>
        <select name="re_trigger_acciones[]" class="form-control form-control-sm">
            @foreach(ReArbolTriggerCatalog::accionesRama() as $acc)
                <option value="{{ $acc }}" {{ ReArbolTriggerCatalog::normalizarAccionRama($tr->accion_rama ?? null) === $acc ? 'selected' : '' }}>
                    {{ ReArbolTriggerCatalog::etiquetaAccionRama($acc) }}
                </option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="number" name="re_trigger_prioridades[]" class="form-control form-control-sm" min="1" value="{{ $tr->prioridad ?? 100 }}" style="width:70px;">
    </td>
    <td>
        <select name="re_trigger_activos[]" class="form-control form-control-sm re-trigger-activo">
            <option value="S" {{ $activoSel === 'S' ? 'selected' : '' }}>S</option>
            <option value="N" {{ $activoSel === 'N' ? 'selected' : '' }}>N</option>
        </select>
    </td>
    <td>
        <button type="button" class="btn-accion-tabla eliminar_re_trigger tooltipsC" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
