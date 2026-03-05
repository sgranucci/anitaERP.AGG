<template id="template-renglon-total-item-factura">
	<tr class="item-factura">
    	<td style="margin : 0; padding : 0; height: 17px;">
       		<input type="hidden" name="items[]" class="form-control item" value="1" readonly>
            <input type="hidden" name="listasprecios_id_fac[]" class="form-control listaprecio_id_fac" readonly value="" />
            <input type="hidden" name="monedas_id_fac[]" class="form-control moneda_id_fac" readonly value="" />
            <input type="hidden" name="incluyeimpuestos_fac[]" class="form-control incluyeimpuesto_fac" readonly value="" />
            <input type="hidden" name="descuentos_fac[]" class="form-control descuento_fac" readonly value="0" />
			<input type="hidden" name="ids_fac[]" class="form-control id_fac" value="0" />
			<input type="hidden" name="loteids_fac[]" class="form-control lote_id_fac" value="" />
			<div class="form-group row" id="articulo">
				<input type="hidden" class="articulo_id_fac" name="articulo_ids_fac[]" value="" >
				<input type="hidden" class="categoria_id_fac" name="categoria_ids_fac[]" value="" >
				<input type="hidden" class="subcategoria_id_fac" name="subcategoria_ids_fac[]" value="" >
				<input type="text" style="WIDTH: 120px;HEIGHT: 38px" class="codigoarticulo_fac codigoarticulolocal_fac form-control" name="codigoarticulos_fac[]" value=""  readonly >
			</div>
		</td>		
		<td style="margin : 0; padding : 0; height: 17px;">
			<input type="text" style="WIDTH: 280px; HEIGHT: 38px" class="descripcionarticulo_fac form-control" name="descripcionarticulos_fac[]" value="" readonly>
		</td>	
		<td style="margin : 0; padding : 0; height: 17px;">
			<input type="text" name="unidadmedidas_fac[]" class="form-control unidadmedida_fac" value="" readonly/>	
			<input type="hidden" name="unidadmedid_ids_fac[]" class="form-control unidadmedida_id_fac" value="" readonly/>							
		</td>			
		<td style="margin : 0; padding : 0; height: 17px;">
			<input type="text" id="icaja" name="cajas_fac[]" class="form-control caja_fac" value="" readonly/>
		</td>
		<td style="margin : 0; padding : 0; height: 17px;">
			<input type="text" id="ipieza" name="piezas_fac[]" class="form-control pieza_fac" value="" readonly/>
		</td>
		<td style="margin : 0; padding : 0; height: 17px;" colspan="2">
			<input type="text" name="pesadas_fac[]" style="text-align:center;" class="form-control pesada_fac" value="" readonly/>
		</td>			
        <td style="margin : 0; padding : 0; height: 17px;">
        	<input type="text" style="text-align: right;" name="precios_fac[]" class="form-control precio_fac" value="" readonly/>
        </td>
	</tr>
</template>
