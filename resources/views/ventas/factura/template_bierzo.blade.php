<template id="template-renglon">
	<tr class="item-pedido">
    	<td>
       		<input type="text" name="items[]" class="form-control item" value="1" readonly>
            <input type="hidden" name="medidas[]" class="form-control medidas" readonly value="" />
            <input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="" />
            <input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="" />
            <input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="" />
            <input type="hidden" name="descuentos[]" class="form-control descuento" readonly value="0" />
			<input type="hidden" name="ids[]" class="form-control ids" value="" />
			<input type="hidden" name="loteids[]" class="form-control loteids" value="" />
			<input type="hidden" name="cantidades[]" class="form-control cantidad" value="" />
        </td>
		<td>
			<div class="factura-sku-campo" id="articulo">
				<input type="hidden" name="articulo[]" class="form-control iiarticulo" readonly value="1" />
				<input type="hidden" class="articulo_id" name="articulo_ids[]" value="" >
				<input type="hidden" class="concepto_venta_id" name="concepto_venta_ids[]" value="" >
				<input type="hidden" class="categoria_id" name="categoria_ids[]" value="" >
				<input type="hidden" class="subcategoria_id" name="subcategoria_ids[]" value="" >
				<input type="hidden" class="articulo_id_previa" name="articulo_id_previa[]" value="" >
				<button type="button" title="Consulta articulos" class="btn-accion-tabla consultaarticulo tooltipsC" data-solo-facturable="1">
						<i class="fa fa-search text-primary"></i>
				</button>
				<button type="button" title="Concepto sin artículo (F1). Después complete el detalle y el precio." class="btn-accion-tabla consultaconceptoventa tooltipsC">
						<i class="fa fa-file-text-o text-info"></i>
				</button>
				<input type="text" class="codigoarticulo codigoarticulolocal form-control" name="codigoarticulos[]" value="" >
				<input type="hidden" class="codigo_previo_articulo" name="codigo_previo_articulos[]" value="" >
			</div>
		</td>		
		<td class="factura-col-detalle">
			<input type="text" style="WIDTH: 220px; HEIGHT: 38px" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="" readonly>
			<textarea name="leyendas_linea[]" class="d-none factura-ta-leyenda-linea" aria-hidden="true"></textarea>
			<div class="factura-leyenda-badge"></div>
		</td>
		<td class="factura-col-iva">
			@include('ventas.factura.partials.select_iva_linea')
		</td>
		<td class="factura-col-umd">
			<select name="unidadmedida_ids[]" data-placeholder="Unidad de Medida" class="unidadmedida_id form-control" data-fouc>
				@foreach($unidadmedida_query as $key => $value)
					<option value="{{ $value['id'] }}">{{ $value['abreviatura'] }}</option>    
				@endforeach
			</select>	
			<input type="hidden" name="unidadmedidas[]" class="form-control unidadmedida" value="" />								
		</td>			
		<td class="factura-col-caja">
			<input type="text" id="icaja" name="cajas[]" class="form-control caja" value="" />
		</td>
		<td class="factura-col-pieza">
			<input type="text" id="ipieza" name="piezas[]" class="form-control pieza" value="" />
		</td>
		<td class="factura-col-kilo">
			<input type="text" id="ikilo" name="kilos[]" class="form-control kilo" value="" />
		</td>
		<td class="factura-col-descuento">
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
        <td class="text-nowrap">
			<button type="button" title="Leyenda / comentario de la l&iacute;nea" class="btn-accion-tabla factura-abrir-leyenda-linea tooltipsC">
				<i class="fa fa-align-left"></i>
			</button>
			<button type="button" title="Elimina esta l&iacute;nea" class="btn-accion-tabla eliminar tooltipsC">
        		<i class="fa fa-times-circle text-danger"></i>
			</button>
        </td>
	</tr>
</template>
