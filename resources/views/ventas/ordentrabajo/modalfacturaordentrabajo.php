<div class="modal fade" id="facturarOrdenTrabajoModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Facturaci&oacute;n de OT</h5>
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
                            <input type="text" id="nombrecliente" name="nombrecliente" class="col-lg-5 form-control" value=""></input>
                        </div>
                    </div>
                    <div class="col-sm-6">
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
                        <div class="form-group row" id="puntoventaremito">
                            <label for="recipient-name" class="col-lg-4 col-form-label requerido">Pto.venta del remito</label>
                            <select name="puntoventaremito_id" id="puntoventaremito_id" data-placeholder="Punto de venta del remito" class="col-lg-5 form-control required" data-fouc>
                            </select>
                        </div>
                        <div class="form-group row">
                            <label for="recipient-name" class="col-lg-4 col-form-label">Cantidad de bultos</label>
                            <input type="number" id="cantidadbulto" name="cantidadbulto" value="0"></input>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="recipient-name" class="col-form-label">Cantidad m&oacute;dulos</label>
                    <input type="text" size="5" style="font-weight: bold; text-align: center;" id="facturarcantmodulo" name="facturarcantmodulo" value="1"></input>
                </div>
                <div class="form-group">
                    <label for="recipient-name" class="col-form-label">Medidas</label>
                    <div id="facturarMedidasModal"></div>
                </div>
                <div class="form-group">
                    <label for="recipient-name" class="col-form-label">Total pares</label>
                    <input type="text" size="5" style="font-weight: bold; text-align: center;" id="facturartotpares" name="facturartotpares" readonly></input>
                </div>
            </form>
            <!-- textarea -->
            <div class="form-group" id="div_leyendafacturacion">
                <label>Leyendas</label>
                <textarea id="leyendafactura" class="form-control" cols="40" rows="6" placeholder="Leyendas de factura ..."></textarea>
            </div>
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
        <div class="modal-footer">
            <button type="button" id="cierraFacturarOrdenTrabajoModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
            <button type="button" id="aceptaFacturarOrdenTrabajoModal" class="btn btn-primary">Genera Factura</button>
        </div>
    </div>
  </div>
</div>
