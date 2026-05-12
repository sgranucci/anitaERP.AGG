<div class="modal fade" id="consultarequisicionModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Requisiciones aprobadas</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Busque y utilice <strong>Elegir</strong> para cargar la plantilla en la orden de compra (sin historia ni archivos). <strong>Consultar</strong> abre la requisición en otra pestaña.</p>
                <div class="form-group row">
                    <label for="consultarequisicion" class="col-sm-2 col-form-label">Buscar</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="consultarequisicion" placeholder="Número, proveedor, centro de costo…" autocomplete="off" autofocus>
                    </div>
                </div>
                <table class="table table-striped table-bordered table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>Nº Req.</th>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Centro costo</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="datosrequisicion"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
                <button type="button" class="btn btn-primary" id="aceptaconsultarequisicionModal">Acepta</button>
            </div>
        </div>
    </div>
</div>
