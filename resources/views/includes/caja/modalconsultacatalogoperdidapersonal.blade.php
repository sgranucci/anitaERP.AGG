@once('anita-modal-consulta-catalogo-perdida-personal')
<div class="modal fade" id="consultaCatalogoPerdidaPersonalModal" role="dialog"
     aria-labelledby="consultaCatalogoPerdidaPersonalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="consultaCatalogoPerdidaPersonalModalLabel">Consulta</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row align-items-end">
                    <div class="col-md-7">
                        <label for="consultaCatalogoPerdidaPersonal" class="col-form-label">Buscar:</label>
                        <input type="text" id="consultaCatalogoPerdidaPersonal"
                               class="form-control form-control-sm"
                               placeholder="C&oacute;digo o descripci&oacute;n" autocomplete="off">
                    </div>
                    <div class="col-md-5 d-none" id="filtroEmpleadoCatalogoPerdidaPersonalWrap">
                        <label for="filtroEmpleadoCatalogoPerdidaPersonal" class="col-form-label">Vigencia:</label>
                        <select id="filtroEmpleadoCatalogoPerdidaPersonal" class="form-control form-control-sm">
                            <option value="activos" selected>Activos a hoy</option>
                            <option value="bajas">Dados de baja</option>
                        </select>
                    </div>
                </div>
                <table class="table table-striped table-bordered table-hover table-sm">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th class="width160 text-nowrap">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="datosCatalogoPerdidaPersonal"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
            </div>
        </div>
    </div>
</div>
@endonce
