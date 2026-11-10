@php
    $fila = $fila ?? ['id' => '', 'codigo' => '', 'nombre' => ''];
    $ccId = (int) ($fila['id'] ?? 0);
    $soloLectura = ! empty($soloLectura);
    $puedeCc = can('editar-centro-costo', false) || can('listar-centro-costo', false);
    $editCc = $ccId > 0 && $puedeCc
        ? route('editar_centrocosto', ['id' => $ccId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<tr class="item-centrocosto">
    <td>
        <div class="d-flex flex-nowrap align-items-center tm-centrocosto-campo" style="gap: 4px;">
            <input type="hidden" name="centrocosto_ids[]" class="centrocosto_id" value="{{ $ccId > 0 ? $ccId : '' }}">
            @if (! $soloLectura)
                <button type="button" title="Consulta centros de costo (F1)" class="btn-accion-tabla consultacentrocosto flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($puedeCc)
                    <a href="{{ $editCc }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-centrocosto tooltipsC flex-shrink-0 {{ $ccId > 0 ? '' : 'd-none' }}"
                        title="Abrir centro de costo en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control form-control-sm codigocentrocosto"
                    value="{{ $fila['codigo'] ?? '' }}"
                    placeholder="C&oacute;d." title="C&oacute;digo. Enter valida. F1 consulta." autocomplete="off"
                    style="width: 5.5rem; flex-shrink: 0;">
            @else
                <input type="text" class="form-control form-control-sm codigocentrocosto"
                    value="{{ $fila['codigo'] ?? '' }}" readonly tabindex="-1"
                    style="width: 5.5rem; flex-shrink: 0;">
            @endif
            <input type="text" class="form-control form-control-sm descripcioncentrocosto text-truncate"
                value="{{ $fila['nombre'] ?? '' }}"
                placeholder="Descripci&oacute;n" readonly tabindex="-1"
                style="min-width: 0; flex: 1 1 auto;">
        </div>
    </td>
    <td class="text-center">
        @if (! $soloLectura)
            <button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla eliminar_centrocosto tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        @endif
    </td>
</tr>
