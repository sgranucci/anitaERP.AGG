<template id="cv-template-renglon-cuentacontable">
    <tr class="item-cv-cuentacontable">
        <td>
            @include('includes.form-empresa-asignada-control', [
                'empresa_query' => $empresa_query ?? [],
                'name' => 'empresa_ids[]',
                'select_class' => 'empresa',
                'permite_vacio' => true,
                'opcion_vacia' => '-- Seleccionar --',
                'required' => false,
            ])
        </td>
        <td>
            <select name="tipotransaccion_ids[]" class="form-control form-control-sm">
                <option value="">Todos (default)</option>
                @foreach ($tipo_query ?? [] as $tipo)
                    <option value="{{ $tipo->id }}">
                        {{ trim(($tipo->abreviatura ?? '').' — '.($tipo->nombre ?? '')) }}
                    </option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="date" name="vigencia_desde[]" class="form-control form-control-sm" value="">
        </td>
        <td>
            <input type="date" name="vigencia_hasta[]" class="form-control form-control-sm" value="">
        </td>
        <td>
            <div class="form-group row mb-0">
                <input type="hidden" name="cuenta[]" class="form-control cv-iicuenta" readonly value="1" />
                <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="">
                <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="">
                <button type="button" title="Consulta cuentas" class="btn-accion-tabla consultacuentacontable tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 110px;HEIGHT: 38px" class="codigocuentacontable interno form-control" name="codigos[]" value="">
                <input type="text" style="WIDTH: 180px;HEIGHT: 38px" class="nombrecuentacontable form-control" name="nombres[]" value="" readonly>
                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="">
            </div>
        </td>
        <td>
            <div class="tm-centrocosto-campo d-flex flex-nowrap align-items-center" style="gap: 4px;">
                <input type="hidden" class="centrocosto_id" name="centrocosto_ids[]" value="">
                <button type="button" title="Consulta centros de costo (F1)" class="btn-accion-tabla consultacentrocosto flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigocentrocosto" name="codigos_centrocosto[]" value="" placeholder="Cód." style="width: 4.5rem;">
                <input type="text" class="form-control form-control-sm descripcioncentrocosto" name="nombres_centrocosto[]" value="" placeholder="CC" readonly style="width: 7rem;">
            </div>
        </td>
        <td>
            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cv_cuentacontable tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
            <input type="hidden" name="creousuario_cuentacontable_ids[]" class="form-control creousuario_cuentacontable_id" value="{{ auth()->id() }}"/>
        </td>
    </tr>
</template>
