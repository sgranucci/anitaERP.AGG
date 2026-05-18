<div class="modal fade" id="modal-cierre-parcial-turno" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Cierre parcial del turno</h6>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body py-2">
                <p class="text-muted small">Totales de esta terminal desde la habilitación del turno activo.</p>
                <div id="modal-cierre-parcial-totales"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-warning" id="modal-cierre-parcial-confirmar">Registrar cierre parcial</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-cerrar-turno-pos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Cierre de turno</h6>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body py-2">
                <div id="modal-cerrar-turno-totales" class="small mb-3"></div>
                <div id="modal-cerrar-turno-errores" class="alert alert-warning d-none small"></div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label class="small">Redondeo invitaciones</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="pos-redondeo-invitaciones"/>
                    </div>
                    <div class="form-group col-md-4">
                        <label class="small">Redondeo turno</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="pos-redondeo-turno" value="0"/>
                    </div>
                    <div class="form-group col-md-4">
                        <label class="small">Sobrante / faltante</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" id="pos-sobrante-faltante" value="0"/>
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label class="small">Observaciones</label>
                    <textarea class="form-control form-control-sm" id="pos-observacion-cierre" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-danger" id="modal-cerrar-turno-confirmar">Cerrar turno</button>
            </div>
        </div>
    </div>
</div>
