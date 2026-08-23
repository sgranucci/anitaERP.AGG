@php
    $reglaObj = is_array($regla ?? null) ? (object) $regla : ($regla ?? null);
    $clave = is_object($reglaObj) ? ($reglaObj->clave ?? 'DEFAULT') : 'DEFAULT';
    $valorId = is_object($reglaObj) ? (int) ($reglaObj->valor_id ?? 0) : 0;
    $reglaId = is_object($reglaObj) ? ($reglaObj->id ?? '') : '';
    $empresaSel = ($clave === 'EMPRESA' && $valorId) ? collect($empresas ?? [])->firstWhere('id', $valorId) : null;
    $transporteSel = ($clave === 'TRANSPORTE' && $valorId) ? collect($transportes ?? [])->firstWhere('id', $valorId) : null;
    $provinciaSel = ($clave === 'PROVINCIA_ENTREGA' && $valorId) ? collect($provincias ?? [])->firstWhere('id', $valorId) : null;
@endphp
<tr class="fila-regla" data-ri="{{ $ri }}">
    <td style="width: 220px;">
        <input type="hidden" name="reglas[{{ $ri }}][id]" value="{{ $reglaId }}">
        <select name="reglas[{{ $ri }}][clave]" class="form-control form-control-sm regla-clave">
            @foreach($reglasEnum as $codigo => $etiqueta)
                <option value="{{ $codigo }}" {{ $clave === $codigo ? 'selected' : '' }}>{{ $etiqueta }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="hidden" name="reglas[{{ $ri }}][valor_id]" class="regla-valor-id" value="{{ $valorId ?: '' }}">
        <span class="regla-valor-vacio text-muted small" @if ($clave !== 'DEFAULT') style="display:none;" @endif>
            Sin valor: cubre el resto de casos de la empresa del programa.
        </span>
        <div class="tm-empresa-campo regla-valor-box regla-valor-empresa" @if ($clave !== 'EMPRESA') style="display:none;" @endif>
            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                <input type="hidden" class="empresa_id" value="{{ $empresaSel->id ?? '' }}">
                <button type="button" title="Consulta empresas (F1)" class="btn-accion-tabla consultaempresa-programa tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigoempresa" value="{{ $empresaSel->codigo ?? '' }}"
                       placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;" title="C&oacute;digo. F1 = consulta, Enter = resolver">
                <input type="text" class="form-control form-control-sm nombreempresa text-truncate" value="{{ $empresaSel->nombre ?? '' }}"
                       placeholder="Empresa" readonly>
            </div>
        </div>
        <div class="tm-transporte-campo regla-valor-box regla-valor-transporte" @if ($clave !== 'TRANSPORTE') style="display:none;" @endif>
            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                <input type="hidden" class="transporte_id" value="{{ $transporteSel->id ?? '' }}">
                <button type="button" title="Consulta {{ config('app.empresa') == 'EL BIERZO' ? 'repartos' : 'transportes' }} (F1)" class="btn-accion-tabla consultatransporte tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigotransporte" value="{{ $transporteSel->codigo ?? '' }}"
                       placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;" title="C&oacute;digo. F1 = consulta, Enter = resolver">
                <input type="text" class="form-control form-control-sm nombretransporte text-truncate" value="{{ $transporteSel->nombre ?? '' }}"
                       placeholder="{{ config('app.empresa') == 'EL BIERZO' ? 'Reparto' : 'Transporte' }}" readonly>
            </div>
        </div>
        <div class="tm-provincia-campo regla-valor-box regla-valor-provincia" @if ($clave !== 'PROVINCIA_ENTREGA') style="display:none;" @endif>
            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                <input type="hidden" class="provincia_id" value="{{ $provinciaSel->id ?? '' }}">
                <button type="button" title="Consulta provincias (F1)" class="btn-accion-tabla consultaprovincia tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigoprovincia" value="{{ $provinciaSel->codigo ?? '' }}"
                       placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;" title="C&oacute;digo. F1 = consulta, Enter = resolver">
                <input type="text" class="form-control form-control-sm nombreprovincia text-truncate" value="{{ $provinciaSel->nombre ?? '' }}"
                       placeholder="Provincia" readonly>
            </div>
        </div>
    </td>
    <td class="width80">
        <button type="button" class="btn-accion-tabla quita-regla" title="Quitar regla">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
