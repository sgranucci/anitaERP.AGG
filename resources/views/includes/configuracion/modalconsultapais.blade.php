@once('anita-modal-consulta-pais')
<div class="modal fade" id="consultapaisModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color:#85C1E9;color:#17202A;">
                <h5 class="modal-title">Pa&iacute;ses</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row">
                    <label for="consultapais" class="col-sm-2 col-form-label">Buscar</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="consultapais" autocomplete="off"
                               placeholder="C&oacute;digo o nombre…">
                    </div>
                </div>
                <table class="table table-striped table-bordered table-hover table-sm">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="d-none">ID</th>
                            <th>C&oacute;digo</th>
                            <th>Nombre</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="datospais"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
                <button type="button" class="btn btn-primary" id="aceptaconsultapaisModal">Acepta</button>
            </div>
        </div>
    </div>
</div>
@endonce
