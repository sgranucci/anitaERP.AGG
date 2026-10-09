@php
    $fila = $fila ?? ['id' => '', 'codigo' => '', 'nombre' => ''];
    $ccId = (int) ($fila['id'] ?? 0);
    $puedeCc = can('editar-centro-costo', false) || can('listar-centro-costo', false);
    $editCc = $ccId > 0 && $puedeCc
        ? route('editar_centrocosto', ['id' => $ccId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<tr class="item-centrocosto">
    <td>
        <input type="text" name="centrocostos[]" class="form-control form-control-sm iicentrocosto" readonly value="{{ $nro ?? 1 }}">
    </td>
    <td>
        <div class="d-flex flex-nowrap align-items-center tm-centrocosto-campo" style="gap: 4px;">
            <input type="hidden" name="centrocosto_ids[]" class="centrocosto_id" value="{{ $ccId > 0 ? $ccId : '' }}">
            <button type="button" title="Consulta centros de costo (F1)" class="btn-accion-tabla consultacentrocosto flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeCc)
                <a href="{{ $editCc }}" target="_blank" rel="noopener"
                    class="btn-accion-tabla btn-link-editar-centrocosto tooltipsC flex-shrink-0 {{ $ccId > 0 ? '' : 'd-none' }}"
                    title="Abrir centro de costo">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control form-control-sm codigocentrocosto"
                value="{{ $fila['codigo'] ?? '' }}"
                placeholder="C&oacute;d." title="C&oacute;digo. Enter valida. F1 consulta." autocomplete="off"
                style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control form-control-sm descripcioncentrocosto text-truncate"
                value="{{ $fila['nombre'] ?? '' }}"
                placeholder="Descripci&oacute;n" readonly
                style="min-width: 0; flex: 1 1 auto;">
        </div>
    </td>
    <td class="text-center">
        <button type="button" title="Quitar este rengl&oacute;n" class="btn-accion-tabla eliminar_centrocosto tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
