<div class="modal fade" id="facturarOrdenventaModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Facturaci&oacute;n de Ordenes de Venta</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="modal-body">
            <form>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group row">
                            <label for="fecha" class="col-lg-4 col-form-label requerido">Fecha</label>
                            <div class="col-lg-4">
                                <input type="date" id="fechafactura" name="fechafactura" class="form-control">
                            </div>
                        </div>
                        <div class="form-group row" id="tipotransaccion">
                            <label for="recipient-name" class="col-lg-4 col-form-label requerido">Tipo de transacci&oacute;n</label>
                            <select name="tipotransaccion_id" id="tipotransaccion_id" data-placeholder="Tipo de transacci&oacute;n" class="col-lg-6 form-control required" data-fouc>
                            </select>
                        </div>
                        <div class="form-group row" id="puntoventa">
                            <label for="recipient-name" class="col-lg-4 col-form-label requerido">Punto de venta</label>
                            <select name="puntoventa_id" id="puntoventa_id" data-placeholder="Punto de venta" class="col-lg-7 form-control required" data-fouc>
                            </select>
                        </div>
                        <div class="form-group row" id="actividad_arca">
                            <label for="recipient-name" class="col-lg-4 col-form-label requerido">Actividad</label>
                            <input type="hidden" id="actividad_arcadefault_id" class="form-control" value="{{old('actividad_arcadefault_id', $data->puntoventas->actividad_arca_id ?? '')}}" />
                            <select name="actividad_arca_id" id="actividad_arca_id" data-placeholder="Actividad ARCA" class="col-lg-5 form-control required" data-fouc>
                                <option value="">-- Seleccionar Actividad ARCA --</option>
                                @foreach($actividad_arca_query as $key => $value)
                                    @if( (int) $value->id == (int) old('actividad_arca_id', $data->actividad_arca_id ?? ''))
                                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                    @else
                                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                    @endif
                                @endforeach					
                            </select>                            
                        </div>
                        <div class="form-group row">
                            <label for="recipient-name" class="col-lg-4 col-form-label">Cliente</label>
                            <input type="text" id="nombrecliente" name="nombrecliente" class="col-lg-7 form-control" value=""></input>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group row">
                            <label for="recipient-name" class="col-lg-4 col-form-label">Empresa</label>
                            <input type="text" id="nombreempresa" name="nombreempresa" value="" readonly></input>
                        </div>
                        <input type="hidden" id="descuentolinea" name="descuentolinea" value=""></input>
                        <div class="form-group row">
                            <label for="recipient-name" class="col-lg-4 col-form-label">Descuento pie factura</label>
                            <input type="number" id="descuentopie" name="descuentopie" value=""></input>
                        </div>
                        <div class="form-group row">
                            <label for="recipient-name" class="col-lg-4 col-form-label">Descuento pie importe</label>
                            <input type="number" id="descuentoimportepie" name="descuentoimportepie" value=""></input>
                        </div>
                        <div class="form-group row">
                            <label for="recipient-name" class="col-lg-4 col-form-label">Monto Total</label>
                            <input type="text" name="moneda" id="moneda" class="col-lg-2 form-control" value="{{$data->monedas->abreviatura}}" readonly>
                            <input type="text" name="monto" id="montototalfactura" class="col-lg-3 form-control" placeholder="Monto Total" aria-label="Monto Total" value="{{number_format($data->monto,2,'.',',')}}" readonly>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <table class="table" id="ordenventa-conceptofactura-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Concepto</th>
                                <th style="width: 50%;">Detalle</th>
                                <th style="width: 7%;">Cantidad</th>
                                <th style="width: 15%;">Monto total</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-ordenventa-conceptofactura-table" class="container-concepto">
                            @if ($data->ordenventa_conceptos ?? '') 
                                @foreach (old('cuota', $data->ordenventa_conceptos->count() ? $data->ordenventa_conceptos : ['']) as $concepto)
                                    @if (isset($concepto->monto))     
                                    <tr>
                                        <td>
                                            <input type="hidden" name="conceptofacturas[]" class="form-control iiconceptofactura" readonly value="{{ $loop->index+1 }}" />
                                            <select name="concepto_ordenventa_ids[]" id="concepto_ordenventa_id" data-placeholder="Concepto" class="form-control required" data-fouc readonly>
                                                @foreach($concepto_ordenventa_query as $key => $value)
                                                    @if( (int) $value->id == (int) old('concepto_ordenventa_id', $concepto->concepto_ordenventa_id ?? ''))
                                                        <option value="{{ $value->id }}" selected="select">{{ $value->nombre }}</option>    
                                                    @else
                                                        <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                                                    @endif
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <textarea id="detalle" name="detalleconceptofacturas[]" class="form-control required" rows="3" readonly placeholder="Detalle ...">{{old('detalle', $concepto->detalle ?? '')}}</textarea>
                                        </td>
                                        <td>
                                            <input type="text" name="cantidadconceptofacturas[]" class="form-control cantidadconceptofactura" readonly value="{{$concepto->cantidad}}">
                                        </td>
                                        <td>
                                            <input type="text" name="montoconceptofacturas[]" class="form-control montoconceptofactura" readonly value="{{number_format($concepto->monto??0,2,'.','')??''}}">
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <input type="text" name="numerocuota" id="numerocuota" class="col-lg-4 form-control" value="" readonly>
                    </div>
                    <div class="col-sm-6">
                        <table class="table table-sm" id="total-factura-ordenventa-table">
                            <thead>
                                <th style="width: 25%;"></th>
                                <th style="width: 10%;"></th>
                                <th style="width: 15%;"></th>
                            </thead>
                            <tbody id="tbody-tabla-total-factura">

                            </tbody>
                        </table>
                    </div>                        
                </div>
            </form>
            <!-- textarea -->
            <div class="form-group" id="div_leyendafacturacion">
                <label>Leyendas</label>
                <textarea id="leyendafactura" class="form-control" cols="40" rows="6" placeholder="Leyendas de factura ..."></textarea>
            </div>
            <div id="datos-exportacion" style="display: none">
                <div class="form-group" id="div_leyendaexportacion">
                    <label>Leyenda Exportaci&oacute;n</label>
                    <textarea id="leyendaexportacion" class="form-control" cols="90" rows="6" placeholder="Leyendas de exportación ..."></textarea>
                </div>
                <div class="form-group row" id="div_incoterm">
                    <label for="recipient-name" class="col-lg-4 col-form-label requerido">Condiciones de venta (incoterms)</label>
                    <select name="incoterm_id" id="incoterm_id" data-placeholder="Incoterms" class="col-lg-5 form-control required" data-fouc>
                    </select>
                </div>
                <div class="form-group row" id="div_formapago">
                    <label for="recipient-name" class="col-lg-4 col-form-label requerido">Forma de pago</label>
                    <select name="formapago_id" id="formapago_id" data-placeholder="Forma de pago" class="col-lg-5 form-control required" data-fouc>
                    </select>
                </div>
                <div class="form-group row" id="div_mercaderia">
                    <label for="recipient-name" class="col-lg-4 col-form-label">Mercader&iacute;a</label>
                    <input type="text" class="col-lg-5 form-control" id="mercaderia" name="marcaderia" value=""></input>
                </div>
            </div>
            @include('ordenventa.ordenventa.templatefacturaordenventa')
            @include('ordenventa.ordenventa.templatetotalfacturaordenventa')
        </div>
        <div class="modal-footer">
            <button type="button" id="cierraFacturarOrdenTrabajoModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
            <button type="button" id="aceptaFacturarOrdenTrabajoModal" class="btn btn-primary" data-padron-accion-factura="1">Genera Factura</button>
        </div>
    </div>
  </div>
</div>
