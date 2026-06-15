<div class="modal fade" id="consultaocrecepcionModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Órdenes de compra pendientes de recepción (AnitaERP)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    Listado desde <strong>AnitaERP</strong>: OC aprobadas sin COM o con COM parcial confirmado.
                    <span id="consultaocrecepcion-filtro-proveedor" class="d-none">Filtrado por proveedor del formulario.</span>
                </p>
                <div class="form-group row">
                    <label for="consultaocrecepcion" class="col-sm-2 col-form-label">Buscar</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="consultaocrecepcion"
                               placeholder="Número OC, proveedor, detalle…" autocomplete="off" autofocus>
                    </div>
                </div>
                <table class="table table-striped table-bordered table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Nº OC</th>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Empresa</th>
                            <th>Estado COM</th>
                            <th class="text-right">Pendiente</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="datosocrecepcion"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
                <button type="button" class="btn btn-primary" id="aceptaconsultaocrecepcionModal">Acepta</button>
            </div>
        </div>
    </div>
</div>
