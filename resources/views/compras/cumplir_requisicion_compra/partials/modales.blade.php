<div class="modal fade" id="consultarequisicioncompraCumpleModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Requisiciones de compra para cumplir</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Solo requisiciones internas en estado <strong>APROBADA</strong> con &iacute;tems pendientes (se abastecen del stock existente).</p>
                <div class="form-group row">
                    <label for="consultarequisicioncompraCumple" class="col-sm-2 col-form-label">Buscar</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="consultarequisicioncompraCumple" placeholder="N&uacute;mero, id, comentario&hellip;" autocomplete="off" autofocus>
                    </div>
                </div>
                <table class="table table-striped table-bordered table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>N&ordm; Req.</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Empresa</th>
                            <th>Centro costo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="datosrequisicioncompraCumple"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
