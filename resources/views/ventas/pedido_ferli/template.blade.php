<template id="template-renglon">
	<tr class="item-pedido">
    	<td>
       		<input type="text" name="items[]" class="form-control item" value="1" readonly>
            <input type="hidden" name="medidas[]" class="form-control medidas" readonly value="" />
            <input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="" />
            <input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="" />
            <input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="" />
            <input type="hidden" name="descuentos[]" class="form-control descuento" readonly value="0" />
			<input type="hidden" name="ids[]" class="form-control ids" value="0" />
			<input type="hidden" name="loteids[]" class="form-control ids" value="0" />
        </td>
        <td>
			<div class="form-group row" id="articulo">
            	<select name="articulos_id[]" class="form-control articulo">
            		<option value="">-- Elija art&iacute;culo --</option>
               			@foreach ($articulo_query as $articulo)
                			<option value="{{ $articulo['id'] }}">{{$articulo['descripcion']}}-{{$articulo['sku']}}</option>
                		@endforeach
            	</select>
				<button type="button" title="Consulta por SKU" style="padding:0;" class="btn-accion-tabla consultaSKU tooltipsC">
                	<i class="fa fa-search text-primary"></i>
				</button>
			</div>
        </td>
        <td>
        	<select name="combinaciones_id[]" data-placeholder="Combinaciones" class="form-control combinacion" data-fouc>
        	</select>
        	<input type="hidden" class="combinacion_id_previa" name="combinacion_id_previa[]" value="{{old('combinacion_id') ?? ''}}" >
        	<input type="hidden" class="desc_combinacion" name="desc_combinacion[]" value="{{old('desc_combinacion[]') ?? ''}}" >
        </td>
        <td>
        	<select name="modulos_id[]" data-placeholder="Modulos" class="form-control modulo" data-fouc>
        </select>
        	<input type="hidden" class="modulo_id_previa" name="modulo_id_previa[]" value="{{old('modulo_id') ?? ''}}" >
        	<input type="hidden" class="desc_modulo" name="desc_modulo[]" class="desc_modulo" value="{{old('desc_modulo[]') ?? ''}}" >
        </td>
        <td>
        	<input type="text" id="icantidad" name="cantidades[]" class="form-control cantidad" readonly value="" />
        </td>
        <td>
        	<input type="text" style="text-align: right;" id="iprecio" name="precios[]" class="form-control precio" readonly value="" />
        </td>
        <td>
			<input type="hidden" name="ot_ids[]" class="form-control ot" 
        		value="{{ old('ot_ids[]') ?? '-1' }}">
        	<input type="text" name="ot_codigos[]" class="form-control otcodigo" 
        		value="{{ old('ot_codigos[]') ?? '-1' }}" readonly> 
        </td>
        <td>
        	<input type="text" id="iobservacion" name="observaciones[]" class="form-control observacion" value="" />
        </td>
        <td>
			<input name="checkssinfiltro[]" class="checkSinFiltro" title="Todos los art&iacute;culos" type="checkbox" autocomplete="off"> 
        </td>
        <td>
			<input name="checkscomb[]" class="checkCombinacion" title="Todas las combinaciones" type="checkbox" autocomplete="off"> 
        </td>
        <td>
			<button type="button" title="Genera OT" style="padding:0;" class="btn-accion-tabla generaot tooltipsC">
        		<i class="fa fa-industry text-success"></i>
			</button>
			<button type="button" title="Imprime OT" style="padding:0;" class="btn-accion-tabla imprimeot tooltipsC">
        		<i class="fa fa-print text-success"></i>
			</button>
			<button type="button" title="Anula Item" style="padding:0;" class="btn-accion-tabla anulaitem tooltipsC">
                <i class="fa fa-window-close text-success"></i>
			</button>
			<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminar tooltipsC">
        		<i class="fa fa-trash text-danger"></i>
			</button>
			<input name="checks[]" style="display:none;" class="checkImpresion" type="checkbox" autocomplete="off"> 
        </td>
	</tr>
</template>
