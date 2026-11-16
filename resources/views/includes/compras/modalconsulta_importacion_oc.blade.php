@once('anita-modal-consulta-importacion-oc')
<div class="modal fade" id="consultaImportacionOcModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color:#85C1E9;color:#17202A;">
                <h5 class="modal-title">Órdenes de compra</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row">
                    <label for="consulta-importacion-oc" class="col-sm-2 col-form-label">Buscar</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="consulta-importacion-oc" autocomplete="off"
                               placeholder="N&uacute;mero OC, proveedor…">
                    </div>
                </div>
                <table class="table table-striped table-bordered table-hover table-sm">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Nro. OC</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="datos-importacion-oc"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endonce
