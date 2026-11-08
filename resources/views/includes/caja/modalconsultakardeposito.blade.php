<div class="modal fade" id="consultakardepositoModal" role="dialog" aria-labelledby="consultakardepositoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="consultakardepositoModalLabel">Depósitos (bóveda / pre-depósito / caja chica)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group row">
                    <label for="consultakardeposito" class="col-form-label">Buscar</label>
                    <input type="text" id="consultakardeposito" class="form-control" autocomplete="off" placeholder="Código o nombre">
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th class="text-center" style="width:90px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="datoskardeposito"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
