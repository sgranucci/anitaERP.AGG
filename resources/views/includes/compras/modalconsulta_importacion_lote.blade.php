@once('anita-modal-consulta-importacion-lote')
<div class="modal fade" id="consultaImportacionLoteModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color:#85C1E9;color:#17202A;">
                <h5 class="modal-title">Lotes / despachos</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row">
                    <label for="consulta-importacion-lote" class="col-sm-2 col-form-label">Buscar</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="consulta-importacion-lote" autocomplete="off"
                               placeholder="N&uacute;mero de despacho o ID…">
                    </div>
                </div>
                <table class="table table-striped table-bordered table-hover table-sm">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Despacho</th>
                            <th>Ingreso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="datos-importacion-lote"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endonce
