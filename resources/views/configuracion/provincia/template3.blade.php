@php
    $puedeAbrirAbmCuentaTpl = can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false);
@endphp
<template id="template-renglon-cuentacontableiibb">
    <tr class="item-cuentacontableiibb">
        <td>
            @include('includes.form-empresa-asignada-control', [
                'empresa_query' => $empresa_query,
                'name' => 'empresa_ids[]',
                'select_class' => 'empresa',
                'required' => true,
                'permite_vacio' => true,
                'opcion_vacia' => '-- Seleccionar --',
            ])
        </td>
        <td>
            <div class="tm-cuentacontable-campo d-flex flex-nowrap align-items-center" style="gap:4px;" id="cuenta">
                <input type="hidden" name="cuenta[]" class="form-control iicuenta" readonly value="1" />
                <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="">
                <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="">
                <button type="button" title="Consulta cuentas" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($puedeAbrirAbmCuentaTpl)
                    <a href="#" target="_blank" rel="noopener"
                       class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 d-none"
                       title="Abrir cuenta en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="codigocuentacontable interno form-control form-control-sm" style="width:5.5rem;flex-shrink:0;"
                       name="codigos[]" value="" placeholder="Cód." autocomplete="off">
                <input type="text" class="nombrecuentacontable form-control form-control-sm text-truncate" name="nombres[]"
                       value="" placeholder="Descripción" readonly style="min-width:0;flex:1 1 auto;">
                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="">
            </div>
        </td>
        <td class="text-center">
            <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_cuentacontableiibb tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
            <input type="hidden" name="creousuario_cuentacontable_ids[]" class="form-control creousuario_cuentacontable_id" value="{{ auth()->id() }}"/>
        </td>
    </tr>
</template>
