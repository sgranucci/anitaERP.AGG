<template id="template-renglon">
	<tr class="item-pedido">
		<td>
			<input type="text" name="items[]" class="form-control item" value="1" readonly>
			<input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="" />
			<input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="" />
			<input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="" />
			<input type="hidden" name="descuentos[]" class="form-control descuento" readonly value="" />
			<input type="hidden" name="ids[]" class="form-control ids" value="" />
			<input type="hidden" name="loteids[]" class="form-control loteids" value="" />
		</td>
		<td>
			<div class="form-group row" id="articulo">
				<input type="hidden" name="articulo[]" class="form-control iiarticulo" readonly value="" />
				<input type="hidden" class="articulo_id" name="articulo_ids[]" value="" >
				<input type="hidden" class="articulo_id_previo" name="articulo_id_previo[]" value="" >
				<input type="hidden" class="categoria_id" name="categoria_ids[]" value="" >
				<input type="hidden" class="subcategoria_id" name="subcategoria_ids[]" value="" >
				<button type="button" title="Consulta articulos" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC">
						<i class="fa fa-search text-primary"></i>
				</button>
				<input type="text" style="WIDTH: 120px;HEIGHT: 38px" class="codigoarticulo codigoarticulolocal form-control" name="codigoarticulos[]" value="" >
				<input type="hidden" class="codigo_previo_articulo" name="codigo_previo_articulos[]" value="" >
			</div>
		</td>		
		<td>
			<input type="text" style="WIDTH: 700px; HEIGHT: 38px" class="descripcionarticulo form-control" name="descripcionarticulos[]" value="" readonly>
		</td>										
		<td>
			<input type="text" name="cantidades[]" class="form-control cantidad" value="" />
		</td>	
		<td>
			<input type="text" name="descuentos[]" class="form-control descuento" value="" />
		</td>			
		<td>
			<input type="text" style="text-align: right;" name="precios[]" class="form-control precio" readonly value="" />
		</td>	
        <td>
			<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminar tooltipsC">
        		<i class="fa fa-trash text-danger"></i>
			</button>
        </td>
	</tr>
</template>
