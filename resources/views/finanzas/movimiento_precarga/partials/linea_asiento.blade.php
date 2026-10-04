<tr class="conta-linea">
    <td>
        <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" name="cuentacontable_ids[]" class="cuentacontable_id" value="{{ (int) ($linea['cuentacontable_id'] ?? 0) > 0 ? (int) $linea['cuentacontable_id'] : '' }}">
            <input type="hidden" class="cuentacontable_id_previa" value="{{ (int) ($linea['cuentacontable_id'] ?? 0) > 0 ? (int) $linea['cuentacontable_id'] : '' }}">
            <input type="hidden" class="codigo_previo" value="{{ $linea['codigo'] ?? '' }}">
            <button type="button" class="btn-accion-tabla consultacuentacontable flex-shrink-0" title="Consulta cuentas (F1)">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="form-control form-control-sm codigocuentacontable" value="{{ $linea['codigo'] ?? '' }}" placeholder="C&oacute;d." autocomplete="off" style="width:7rem;flex-shrink:0;">
            <input type="text" class="form-control form-control-sm nombrecuentacontable" value="{{ $linea['nombre'] ?? '' }}" placeholder="Cuenta" readonly>
        </div>
    </td>
    <td>
        <input type="number" name="debeasientos[]" class="form-control form-control-sm text-right debe-linea" min="0" step="0.01" value="{{ number_format((float) ($linea['debe'] ?? 0), 2, '.', '') }}">
    </td>
    <td>
        <input type="number" name="haberasientos[]" class="form-control form-control-sm text-right haber-linea" min="0" step="0.01" value="{{ number_format((float) ($linea['haber'] ?? 0), 2, '.', '') }}">
    </td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla conta-quitar" title="Quitar renglón">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
