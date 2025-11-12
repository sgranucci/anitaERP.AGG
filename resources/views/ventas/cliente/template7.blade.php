<template id="template-renglon-articulo-suspendido">
    <tr class="item-articulo-suspendido">
        <td>
            <div class="form-group row" id="articulo">
                <input type="hidden" name="articulo[]" class="form-control iiarticulo" readonly value="1" />
                <input type="hidden" class="articulo_id" name="articulo_ids[]" value="" >
                <input type="hidden" class="articulo_id_previa" name="articulo_id_previa[]" value="" >
                <button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 150px;HEIGHT: 38px" class="codigoarticulo form-control" name="codigoarticulos[]" value="" >
                <input type="hidden" class="codigo_previo_articulo" name="codigo_previo_articulos[]" value="" >
            </div>
        </td>							
        <td>
            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="" readonly>
        </td>
        <td>
            <input type="datetime" class="fechasuspension form-control" name="fechasuspensiones[]" value="{{date('d-m-Y H:i:s')}}" readonly>
        </td>
		<td>
			<input type="hidden" name="creousuario_articulo_suspendido_ids[]" class="form-control creousuario_articulo_suspendido_id" value="{{ auth()->id() }}"/>
			<input type="text" name="creousuario_articulo_suspendidos[]" class="form-control creousuario_articulo_suspendido" value="{{ auth()->user()->name }}" readonly/>
		</td>        
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_ticket_articulo tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
            <input type="hidden" name="creousuarioarticulo_ids[]" class="form-control creousuarioarticulo_id" value="{{ auth()->id() }}" />
        </td>
    </tr>
</template>