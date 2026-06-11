<div class="modal fade" id="modal-corregir-arqueo-cierre" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-corregir-arqueo-titulo">Corregir arqueo del cierre</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modal-corregir-arqueo-error" class="alert alert-danger d-none"></div>
                <div id="modal-corregir-arqueo-bloqueo" class="alert alert-warning d-none font-weight-bold"></div>
                <p class="text-muted small mb-2" id="modal-corregir-arqueo-subtitulo"></p>
                <p class="text-muted small mb-2">
                    Solo cierres del <strong>día operativo en curso</strong> (jornada abierta) que
                    <strong>no estén presentados en caja</strong>.
                </p>
                <div id="modal-corregir-arqueo-conciliacion" class="mb-3"></div>
                <div id="modal-corregir-arqueo-medios" class="mb-3"></div>
                <div class="card card-outline card-warning mb-3" id="corregir-arqueo-ajustes-card">
                    <div class="card-header py-2"><strong>Ajustes al cierre</strong></div>
                    <div class="card-body py-2">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="corregir-redondeo-invitaciones" class="small mb-1">Redondeo invitaciones</label>
                                <input type="number" step="0.01" id="corregir-redondeo-invitaciones" class="form-control form-control-sm" value="0"/>
                            </div>
                            <div class="col-md-4">
                                <label for="corregir-redondeo-turno" class="small mb-1">Redondeo turno</label>
                                <input type="number" step="0.01" id="corregir-redondeo-turno" class="form-control form-control-sm" value="0"/>
                            </div>
                            <div class="col-md-4">
                                <label for="corregir-sobrante-faltante" class="small mb-1">Sobrante / faltante</label>
                                <input type="number" step="0.01" id="corregir-sobrante-faltante" class="form-control form-control-sm" value="0"/>
                                <small class="text-muted">Negativo = faltante, positivo = sobrante.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-0" id="corregir-arqueo-motivo-wrap">
                    <label for="corregir-arqueo-motivo">Motivo de la corrección <span class="text-danger">*</span></label>
                    <textarea id="corregir-arqueo-motivo" class="form-control" rows="2" maxlength="500"
                              placeholder="Ej.: Se detectó faltante en efectivo al abrir el turno tarde sin cerrar caja."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-guardar-correccion-arqueo">
                    <i class="fa fa-save"></i> Guardar corrección
                </button>
            </div>
        </div>
    </div>
</div>
