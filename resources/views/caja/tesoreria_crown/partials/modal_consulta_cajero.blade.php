<div class="modal fade" id="modal-consulta-cajero-crown" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cajeros</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-2">
                    <input type="text" class="form-control" id="modal-consulta-cajero-crown-q" placeholder="Código, legajo o nombre" autocomplete="off">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-info" id="modal-consulta-cajero-crown-buscar">Buscar</button>
                    </div>
                </div>
                <p class="small text-muted mb-2" id="modal-consulta-cajero-crown-total"></p>
                <div class="table-responsive" style="max-height:60vh;overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead style="background:#85C1E9;color:#17202A;position:sticky;top:0;">
                            <tr>
                                <th>Código</th>
                                <th>Legajo</th>
                                <th>Nombre</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="modal-consulta-cajero-crown-filas"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
