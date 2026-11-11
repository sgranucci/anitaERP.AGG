@php
    $idx = $idx ?? 0;
    $ren = $ren ?? [];
    $tiposBien = $tiposBien ?? \App\Models\Contable\BienUso::$enumTipoBien;
    $ccId = $ren['centrocosto_id'] ?? '';
    $ccCodigo = $ren['centrocosto_codigo'] ?? '';
    $ccNombre = $ren['centrocosto_nombre'] ?? '';
    $inputId = 'bu_cc_'.$idx;
@endphp
<tr class="js-renglon-bien-uso">
    <td>
        <input type="text" class="form-control form-control-sm js-bu-descripcion" maxlength="80"
            value="{{ $ren['descripcion'] ?? '' }}" placeholder="Descripci&oacute;n del bien">
    </td>
    <td>
        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm js-bu-valor text-right"
            value="{{ $ren['valor_origen'] ?? '' }}">
    </td>
    <td>
        <select class="form-control form-control-sm js-bu-tipo-bien">
            @foreach ($tiposBien as $tb)
                <option value="{{ $tb['valor'] }}"
                    {{ (string) ($ren['tipo_bien'] ?? 'I') === ($tb['valor'] ?? '') ? 'selected' : '' }}>
                    {{ $tb['nombre'] }}
                </option>
            @endforeach
        </select>
    </td>
    <td>
        <div class="tm-centrocosto-campo mb-0" id="tm_centrocosto_bu_{{ $idx }}">
            <div class="d-flex flex-nowrap align-items-center tm-centrocosto-campo-inputs w-100" style="gap:4px;">
                <input type="hidden" class="centrocosto_id js-bu-centrocosto-id" id="{{ $inputId }}"
                    value="{{ $ccId }}">
                <button type="button" title="Consulta centros de costo (F1)"
                    class="btn-accion-tabla consultacentrocosto flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigocentrocosto"
                    id="{{ $inputId }}_codigo" value="{{ $ccCodigo }}" placeholder="C&oacute;d."
                    autocomplete="off" style="width:4.5rem;flex-shrink:0;">
                <input type="text" class="form-control form-control-sm descripcioncentrocosto text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $ccNombre }}"
                    placeholder="Descripci&oacute;n" readonly style="min-width:0;flex:1 1 auto;">
            </div>
        </div>
    </td>
    <td class="text-center align-middle">
        <button type="button" class="btn-accion-tabla js-bu-quitar-renglon" title="Quitar rengl&oacute;n">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
