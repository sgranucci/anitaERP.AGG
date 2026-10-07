@once('anita-modal-consulta-catalogo-categoria')
<div class="modal fade" id="consultacatalogocategoriaModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Categorías del catálogo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="text" id="consultacatalogocategoria" class="form-control form-control-sm mb-2" placeholder="Código o nombre" autocomplete="off">
                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;"><tr><th>ID</th><th>Código</th><th>Nombre</th><th></th></tr></thead>
                    <tbody id="datoscatalogocategoria"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endonce
@once('anita-modal-consulta-logistica-rol')
<div class="modal fade" id="consultalogisticarolModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Roles</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="text" id="consultalogisticarol" class="form-control form-control-sm mb-2" placeholder="Nombre del rol" autocomplete="off">
                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;"><tr><th>ID</th><th>Nombre</th><th></th></tr></thead>
                    <tbody id="datoslogisticarol"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endonce
