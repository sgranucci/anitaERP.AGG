@once('anita-modal-consulta-importacion-com')
<div class="modal fade" id="consultaImportacionComModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color:#85C1E9;color:#17202A;">
                <h5 class="modal-title">Recepciones COM</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group row">
                    <label for="consulta-importacion-com" class="col-sm-2 col-form-label">Buscar</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="consulta-importacion-com" autocomplete="off" placeholder="N&ordm; recepción, OC…">
                    </div>
                </div>
                <table class="table table-sm table-striped table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr><th>ID</th><th>N&ordm; COM</th><th>OC</th><th>Proveedor</th><th>Fecha</th><th></th></tr>
                    </thead>
                    <tbody id="datos-importacion-com"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endonce
