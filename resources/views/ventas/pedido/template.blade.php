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
			<input type="hidden" name="loteids[]" class="form-control lote_id" value="" />
        </td>
		<td>
			<div class="form-group row" id="articulo">
				<input type="hidden" name="articulo[]" class="form-control iiarticulo" readonly value="1" />
				<input type="hidden" class="articulo_id" name="articulo_ids[]" value="" >
				<input type="hidden" class="categoria_id" name="categoria_ids[]" value="" >
				<input type="hidden" class="subcategoria_id" name="subcategoria_ids[]" value="" >
				<input type="hidden" class="articulo_id_previa" name="articulo_id_previa[]" value="" >
				<button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC">
						<i class="fa fa-search text-primary"></i>
				</button>
				<input type="text" style="WIDTH: 120px;HEIGHT: 38px" class="codigoarticulo codigoarticulolocal form-control" name="codigoarticulos[]" value="" >
				<input type="hidden" class="codigo_previo_articulo" name="codigo_previo_articulos[]" value="" >
			</div>
		</td>		
		<td>
			<input type="text" style="WIDTH: 220px; HEIGHT: 38px" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="" readonly>
		</td>	
		<td>
			<select name="unidadmedida_ids[]" data-placeholder="Unidad de Medida" class="unidadmedida_id form-control" data-fouc>
				@foreach($unidadmedida_query as $key => $value)
					<option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
				@endforeach
			</select>	
			<input type="hidden" name="unidadmedidas[]" class="form-control unidadmedida" value="" />								
		</td>			
		<td>
			<input type="text" id="icaja" name="cajas[]" class="form-control caja" value="" />
		</td>
		<td>
			<input type="text" id="ipieza" name="piezas[]" class="form-control pieza" value="" />
		</td>
		<td>
			<input type="text" id="ikilo" name="kilos[]" class="form-control kilo" value="" />
		</td>
		<td>
			<input type="text" name="pesadas[]" class="form-control pesada" value="" />
		</td>			
		<td>
			<select name="descuentoventa_ids[]" data-placeholder="Descuento" class="descuentoventa_id form-control" data-fouc>
                <option value="">-Descuento-</option>
                @foreach($descuentoventa_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                @endforeach
            </select>
			<input type="hidden" name="descuentoventaanterior_ids[]" class="form-control descuentoventaanterior_id" value="" />
		</td>
        <td>
        	<input type="text" style="text-align: right;" name="precios[]" class="form-control precio" value="" readonly/>
        </td>
        <td>
			<button type="button" title="Anula Item" style="padding:0;" class="btn-accion-tabla anulaitem tooltipsC">
                <i class="fa fa-window-close text-success"></i>
			</button>
			<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminar tooltipsC">
        		<i class="fa fa-trash text-danger"></i>
			</button>
			@if (can('entregar-articulo-sin-cargo-venta', false))
				<button type="button" title="Artículo sin cargo" style="padding:0;" class="btn-accion-tabla botonsincargo tooltipsC">
					<i class="fa fa-gift text-primary"></i>
				</button>
			@endif						
			<input name="checks[]" style="display:none;" class="checkImpresion" type="checkbox" autocomplete="off"> 
			<input type="hidden" name="observaciones[]" class="form-control observacion" value="" />
			<input type="hidden" style="text-align: right;" name="sincargos[]" class="form-control sincargo" value="N" />
        </td>
	</tr>
</template>
