@php
    $movimientoStockModoFerli = $movimientoStockModoFerli ?? \App\Support\Stock\MovimientoStockFerliSupport::esCalzadosFerli();
@endphp
@if($movimientoStockModoFerli)
<template id="template-renglon">
	<tr class="item-pedido">
    	<td class="align-middle">
       		<input type="text" name="items[]" class="form-control form-control-sm item text-center" value="1" readonly>
            <input type="hidden" name="medidas[]" class="form-control medidas" readonly value="" />
            <input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="" />
            <input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="" />
            <input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="" />
            <input type="hidden" name="descuentos[]" class="form-control descuento" readonly value="0" />
			<input type="hidden" name="ids[]" class="form-control ids" value="0" />
			<input type="hidden" name="loteids[]" class="form-control loteids" value="0" />
			<input type="hidden" name="articulos_id[]" class="articulo_id" value="">
			<input type="hidden" class="articulo_id_previo" name="articulo_id_previo[]" value="">
        </td>
        <td class="align-middle">
			<div class="celda-articulo-ms-wrapper">
				<div class="celda-articulo-ms d-flex align-items-center flex-nowrap mb-0">
				<button type="button" title="Consulta artículos (F1)" class="btn-accion-tabla consultaarticulo flex-shrink-0" style="padding:1px 4px;">
					<i class="fa fa-search text-primary"></i>
				</button>
				<button type="button" title="Saldos por dep&oacute;sito" class="btn-accion-tabla btn-saldos-articulo-linea d-none flex-shrink-0" style="padding:1px 4px;" disabled>
					<i class="fa fa-warehouse text-secondary"></i>
				</button>
					<a href="#" class="btn btn-xs btn-link-articulo d-none flex-shrink-0" target="_blank" rel="noopener" title="Consultar artículo">
						<i class="fa fa-external-link text-primary"></i>
					</a>
					<input type="text" class="codigoarticulo form-control form-control-sm flex-grow-1" value="" autocomplete="off" placeholder="SKU">
				</div>
				<input type="hidden" class="ms-articulo-compra-elegido" value="">
				<div class="ms-conversion-formula small text-primary mt-1 d-none" aria-live="polite"></div>
			</div>
        </td>
        <td class="align-middle col-desc-celda">
        	<input type="text" class="descripcionarticulo form-control form-control-sm" value="" readonly>
        </td>
        @include('stock.movimientostock.partials.fila_celda_npu_baja')
        @include('stock.movimientostock.partials.fila_saldo_origen')
        @include('stock.movimientostock.partials.fila_item_ferli')
        <td class="align-middle text-center">
			<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminar tooltipsC">
        		<i class="fa fa-trash text-danger"></i>
			</button>
        </td>
	</tr>
</template>
@else
<template id="template-renglon">
	<tr class="item-pedido">
    	<td class="align-middle">
       		<input type="text" name="items[]" class="form-control form-control-sm item text-center" value="1" readonly>
            <input type="hidden" name="medidas[]" class="form-control medidas" readonly value="" />
            <input type="hidden" name="listasprecios_id[]" class="form-control listaprecio_id" readonly value="" />
            <input type="hidden" name="monedas_id[]" class="form-control moneda_id" readonly value="" />
            <input type="hidden" name="incluyeimpuestos[]" class="form-control incluyeimpuesto" readonly value="" />
            <input type="hidden" name="descuentos[]" class="form-control descuento" readonly value="0" />
			<input type="hidden" name="ids[]" class="form-control ids" value="0" />
			<input type="hidden" name="loteids[]" class="form-control loteids" value="0" />
			<input type="hidden" name="articulos_id[]" class="articulo_id" value="">
			<input type="hidden" class="articulo_id_previo" name="articulo_id_previo[]" value="">
            <input type="hidden" name="combinaciones_id[]" value="">
            <input type="hidden" name="modulos_id[]" value="">
        </td>
        <td class="align-middle">
			<div class="celda-articulo-ms-wrapper">
				<div class="celda-articulo-ms d-flex align-items-center flex-nowrap mb-0">
				<button type="button" title="Consulta artículos (F1)" class="btn-accion-tabla consultaarticulo flex-shrink-0" style="padding:1px 4px;">
					<i class="fa fa-search text-primary"></i>
				</button>
				<button type="button" title="Saldos por dep&oacute;sito" class="btn-accion-tabla btn-saldos-articulo-linea d-none flex-shrink-0" style="padding:1px 4px;" disabled>
					<i class="fa fa-warehouse text-secondary"></i>
				</button>
					<a href="#" class="btn btn-xs btn-link-articulo d-none flex-shrink-0" target="_blank" rel="noopener" title="Consultar artículo">
						<i class="fa fa-external-link text-primary"></i>
					</a>
					<input type="text" class="codigoarticulo form-control form-control-sm flex-grow-1" value="" autocomplete="off" placeholder="SKU">
				</div>
				<input type="hidden" class="ms-articulo-compra-elegido" value="">
				<div class="ms-conversion-formula small text-primary mt-1 d-none" aria-live="polite"></div>
			</div>
        </td>
        <td class="align-middle col-desc-celda">
        	<input type="text" class="descripcionarticulo form-control form-control-sm" value="" readonly>
        </td>
        @include('stock.movimientostock.partials.fila_celda_npu_baja')
        @include('stock.movimientostock.partials.fila_saldo_origen')
        @include('stock.movimientostock.partials.fila_item_estandar')
        <td class="align-middle text-center">
			<button type="button" title="Elimina esta linea" style="padding:0;" class="btn-accion-tabla eliminar tooltipsC">
        		<i class="fa fa-trash text-danger"></i>
			</button>
        </td>
	</tr>
</template>
@endif
