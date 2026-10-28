<div class="modal fade" id="rdGrupoCcostoModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Grupos de centros de costo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="rd-grupo-ccosto-buscar">Buscar</label>
                    <input type="text" id="rd-grupo-ccosto-buscar" class="form-control form-control-sm" autocomplete="off"
                           placeholder="C&oacute;digo o nombre">
                </div>
                <table class="table table-sm table-bordered table-hover mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Grupo</th>
                            <th class="text-right">Centros</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="rd-grupo-ccosto-datos"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
