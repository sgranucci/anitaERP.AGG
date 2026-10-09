@php
    $fila = $fila ?? ['id' => '', 'codigo' => '', 'nombre' => ''];
    $conceptoId = (int) ($fila['id'] ?? 0);
    $puedeConcepto = can('editar-concepto-iva-compra', false) || can('listar-concepto-iva-compra', false);
    $editConcepto = $conceptoId > 0 && $puedeConcepto
        ? route('editar_concepto_ivacompra', ['id' => $conceptoId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<tr class="item-concepto_ivacompra">
    <td>
        <input type="text" name="concepto_ivacompras[]" class="form-control form-control-sm iiconcepto_ivacompra" readonly value="{{ $nro ?? 1 }}">
    </td>
    <td>
        <div class="d-flex flex-nowrap align-items-center tm-concepto-ivacompra-campo" style="gap: 4px;">
            <input type="hidden" name="concepto_ivacompra_ids[]" class="concepto_ivacompra_id" value="{{ $conceptoId > 0 ? $conceptoId : '' }}">
            <button type="button" title="Consulta conceptos de IVA compras (F1)" class="btn-accion-tabla consultaconcepto-tipo flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeConcepto)
                <a href="{{ $editConcepto }}" target="_blank" rel="noopener"
                    class="btn-accion-tabla btn-link-editar-concepto-tipo tooltipsC flex-shrink-0 {{ $conceptoId > 0 ? '' : 'd-none' }}"
                    title="Abrir concepto de IVA compras">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control form-control-sm codigo_concepto_ivacompra"
                value="{{ $fila['codigo'] ?? '' }}"
                placeholder="C&oacute;d." title="C&oacute;digo. Enter valida. F1 consulta." autocomplete="off"
                style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control form-control-sm nombre_concepto_ivacompra text-truncate"
                value="{{ $fila['nombre'] ?? '' }}"
                placeholder="Descripci&oacute;n" readonly
                style="min-width: 0; flex: 1 1 auto;">
        </div>
    </td>
    <td class="text-center">
        <button type="button" title="Quitar este rengl&oacute;n" class="btn-accion-tabla eliminar_concepto_ivacompra tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
