<div class="modal fade" id="facturarPedidoModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Facturaci&oacute;n de Pedido</h5>
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
                                <input type="date" id="fechafactura" name="fechafactura" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="form-group row" id="tipotransaccion">
                            <label for="recipient-name" class="col-lg-4 col-form-label requerido">Tipo de transacci&oacute;n</label>
                            <select name="tipotransaccion_id" id="tipotransaccion_id" data-placeholder="Tipo de transacci&oacute;n" class="col-lg-6 form-control required" data-fouc>
                            </select>
                        </div>
                        <div class="form-group row" id="puntoventa">
                            <label for="recipient-name" class="col-lg-4 col-form-label requerido">Punto de venta</label>
                            <select name="puntoventa_id" id="puntoventa_id" data-placeholder="Punto de venta" class="col-lg-5 form-control required" data-fouc>
                            </select>
                        </div>
                        <div class="form-group row">
                            <label for="recipient-name" class="col-lg-4 col-form-label">Cliente</label>
                            <input type="text" id="nombrecliente" name="nombrecliente" class="col-lg-7 form-control" value=""></input>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        @if (config('app.empresa') == "EL BIERZO")
                            <div class="form-group row" style="display: none">
                                <label for="recipient-name" class="col-lg-4 col-form-label">Descuento pie factura</label>
                                <input type="number" id="descuentopie" name="descuentopie" value=""></input>
                                <input type="hidden" id="descuentolinea" name="descuentolinea" value=""></input>
                                <input type="hidden" id="descuentoimportepie" name="descuentoimportepie" value=""></input>
                            </div>
                        @else
                            <div class="form-group row">
                                <label for="recipient-name" class="col-lg-4 col-form-label">Descuento de l&iacute;nea</label>
                                <input type="number" id="descuentolinea" name="descuentolinea" value=""></input>
                            </div>
                            <div class="form-group row">
                                <label for="recipient-name" class="col-lg-4 col-form-label">Descuento pie factura</label>
                                <input type="number" id="descuentopie" name="descuentopie" value=""></input>
                            </div>
                            <div class="form-group row">
                                <label for="recipient-name" class="col-lg-4 col-form-label">Descuento pie importe</label>
                                <input type="number" id="descuentoimportepie" name="descuentoimportepie" value=""></input>
                            </div>
                        @endif
                        <div class="form-group row" id="puntoventaremito">
                            <label for="recipient-name" class="col-lg-4 col-form-label requerido">Pto.venta del remito</label>
                            <select name="puntoventaremito_id" id="puntoventaremito_id" data-placeholder="Punto de venta del remito" class="col-lg-5 form-control required" data-fouc>
                            </select>
                        </div>
                        <div class="form-group row" id="actividad_arca">
                            <label for="recipient-name" class="col-lg-4 col-form-label requerido">Actividad</label>
                            <input type="hidden" id="actividad_arcadefault_id" class="form-control" value="{{old('actividad_arcadefault_id', $data->puntoventas->actividad_arca_id ?? '')}}" />
                            <select name="actividad_arca_id" id="actividad_arca_id" data-placeholder="Actividad ARCA" class="col-lg-6 form-control required" data-fouc>
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
                    </div>
                </div>
                <div id="aviso-deposito-facturacion-pedido" class="alert alert-info aviso-deposito-facturacion d-none mb-2" role="status"></div>
                <div id="alert-preview-factura-pedido" class="alert alert-warning d-none mb-2" role="alert"></div>
                <div class="form-group">
                    <label for="recipient-name" class="col-form-label">Items a Facturar</label>
                    <table class="table table-sm" id="factura-pedido-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Art&iacute;culo</th>
                                <th style="width: 25%;">Descripción Artículo</th>
                                <th style="width: 8%;">UMD</th>
                                <th style="width: 10%;">Cajas</th>
                                <th style="width: 10%;">Piezas</th>
                                <th style="width: 10%;">Pesada</th>
                                <th style="width: 10%;">Bonificación</th>
                                <th style="width: 10%;">Precio</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-tabla-factura"> 
                        </tbody>
                    </table>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                    </div>
                    <div class="col-sm-6">
                        <table class="table table-sm" id="total-factura-pedido-table">
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
                <div class="form-group row">
                    <label for="cantidadbulto" class="col-lg-4 col-form-label">Cantidad de bultos</label>
                    <input type="number" id="cantidadbulto" name="cantidadbulto" value="" min="0" step="1" inputmode="numeric">
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
            @include('ventas.pedido.templatefacturapedido')
            @include('ventas.pedido.templatetotalitemfacturapedido')
            @include('ventas.pedido.templatetotalfacturapedido')
        </div>
        <div class="modal-footer">
            <button type="button" id="cierraFacturarOrdenTrabajoModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
            <button type="button" id="aceptaFacturarOrdenTrabajoModal" class="btn btn-primary" data-padron-accion-factura="1">Genera Factura</button>
        </div>
    </div>
  </div>
</div>
