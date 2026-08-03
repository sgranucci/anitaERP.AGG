@php
    $estadosArbol = \App\Support\Solicitudpago\SolicitudpagoEstados::opcionesArbolAprobacion();
@endphp
<template id="template-renglon-concepto-usuario">
    <tr class="item-concepto-usuario">
        <td>
            <input type="text" class="form-control form-control-sm iiconcepto_usuario" readonly value="1"/>
        </td>
        <td>
            <input type="number" min="1" class="form-control form-control-sm nivel" name="niveles[]" value="1"/>
        </td>
        <td>
            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                <input type="hidden" class="usuario_id_arbol" name="usuario_ids[]" value="">
                <input type="hidden" class="usuario_id_previa" name="usuario_id_previa[]" value="">
                <input type="text" style="flex: 0 0 110px; width: 110px; height: 38px;"
                       class="usuario_codigo_arbol form-control" value="" placeholder="C&oacute;digo" autocomplete="off">
                <button type="button" title="Consulta usuarios" style="padding:1; flex: 0 0 auto;"
                        class="btn-accion-tabla consultausuario tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="flex: 1 1 auto; min-width: 0; height: 38px;"
                       class="nombreusuario form-control" name="nombreusuarios[]" value="" placeholder="(opcional)">
            </div>
        </td>
        <td>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm desdemonto"
                   name="desdemontos[]" value="0">
        </td>
        <td>
            <select name="documento_estado_al_aprobar[]" class="form-control form-control-sm documento_estado_al_aprobar"
                    title="Estado de la SP al aprobar este nivel">
                <option value="">—</option>
                @foreach ($estadosArbol as $est)
                    <option value="{{ $est['valor'] }}" {{ $est['valor'] === 'EMITIDA' ? 'selected' : '' }}>
                        {{ $est['nombre'] }}
                    </option>
                @endforeach
            </select>
        </td>
        <td class="text-center">
            <button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla eliminar_concepto_usuario tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
