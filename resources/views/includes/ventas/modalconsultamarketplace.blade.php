<div class="modal fade" id="consultamarketplaceModal" tabindex="-1" role="dialog" aria-labelledby="consultamarketplaceLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="consultamarketplaceLabel">Marketplaces</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row align-items-center mb-2">
                    <label for="consultamarketplace-buscar" class="col-form-label col-auto pr-2 mb-0">Buscar</label>
                    <div class="col">
                        <input type="text" id="consultamarketplace-buscar" class="form-control" autocomplete="off">
                    </div>
                </div>
                <table class="table table-striped table-bordered table-hover table-sm">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th class="width80">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="datosmarketplace"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
            </div>
        </div>
    </div>
</div>
