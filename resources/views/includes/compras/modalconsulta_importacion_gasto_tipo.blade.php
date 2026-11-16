@once('anita-modal-consulta-importacion-gasto-tipo')
<div class="modal fade" id="consultaImportacionGastoTipoModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color:#85C1E9;color:#17202A;">
                <h5 class="modal-title">Tipos de gasto de importaci&oacute;n</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group row">
                    <label for="consulta-importacion-gasto-tipo" class="col-sm-2 col-form-label">Buscar</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="consulta-importacion-gasto-tipo" autocomplete="off" placeholder="C&oacute;digo o nombre…">
                    </div>
                </div>
                <table class="table table-sm table-striped table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr><th>C&oacute;digo</th><th>Nombre</th><th>Tratamiento</th><th></th></tr>
                    </thead>
                    <tbody id="datos-importacion-gasto-tipo"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endonce
