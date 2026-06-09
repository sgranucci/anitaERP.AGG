<template id="template-renglon-comprobante">
    <tr class="item-comprobante">
        <td>
            <input type="text" class="codigocomprobante form-control" name="codigocomprobantes[]" value="" >
        </td>							
        <td>
            <input type="date" class="fechacomprobante form-control" name="fechacomprobantes[]" value="" readonly>
        </td>
        <td>
            <input type="date" class="fechavencimientocomprobante form-control" name="fechavencimientocomprobantes[]" value="" readonly>
        </td>
        <td>
            <select name="monedacomprobante_ids[]" data-placeholder="Moneda" class="monedacomprobante form-control required" required readonly data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($moneda_query as $key => $value)
                    <option value="{{ $value->id }}" selected="select">{{ $value->abreviatura }}</option>    
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" style="text-align: right;" name="cotizacioncomprobantes[]" class="form-control cotizacioncomprobante" value="0"  readonly>                            
        </td>
        <td>
            <input type="number" style="text-align: right;" name="montocomprobantes[]" class="form-control montocomprobante" value=""  readonly>
        </td>
        <td>
            <input type="number" style="text-align: right;" name="montoaplicadocomprobantes[]" class="form-control montoaplicadocomprobante" value="">
        </td>
        <td>
            <input type="number" style="text-align: right;" name="saldocomprobantes[]" class="form-control saldocomprobante" value=""  readonly>
        </td>
        <td>
            @if (can('editar-factura', false))
                <a href="" target="_blank" rel="noopener noreferrer" class="btn-accion-tabla editarfactura tooltipsC" title="Editar este registro">
                <i class="fa fa-edit"></i>
                </a>
            @endif
            @if ($puede_descuento_cobranza ?? false)
                <button type="button" class="btn-accion-tabla tooltipsC btn-descuento-comprobante" title="Descuento (genera NC al confirmar)">
                    <i class="fa fa-percent text-warning"></i>
                </button>
            @endif
            @if (can('generar-nota-de-credito', false))
                <a href="" target="_blank" rel="noopener noreferrer" class="btn-accion-tabla generarnotadecredito tooltipsC" title="Generar nota de crédito">
                <i class="fa fa-undo text-danger"></i>
                </a>
            @endif                            
            @if (can('listar-factura', false))
                <a href="" class="btn-accion-tabla listarfactura tooltipsC" title="Listar el Comprobante de Venta">
                <i class="fa fa-print"></i>
                </a>
            @endif                                             
        </td>
        <td>
            <input name="checkaplicaciones[]" class="checkaplicacion" type="checkbox" autocomplete="off"> 
            <input type="hidden" class="idcuentacorriente form-control" name="idcuentacorrientes[]" value="" >
            <input type="hidden" class="idventa form-control" name="idventas[]" value="" >
            <input type="hidden" class="descuento_tipo" name="descuento_tipos[]" value="" />
            <input type="hidden" class="descuento_valor" name="descuento_valores[]" value="" />
            <input type="hidden" class="descuento_importe" name="descuento_importes[]" value="" />
            <input type="hidden" class="descuento_venta_origen_id" name="descuento_venta_origen_ids[]" value="" />
            <input type="hidden" class="descuento_cc_origen_id" name="descuento_cc_origen_ids[]" value="" />
            <input type="hidden" class="descuento_leyenda" name="descuento_leyendas[]" value="" />
        </td>
    </tr>
</template>