{{-- F5 remito Z: kilos de hoy en Villafranca (comprob/compaux). FAC+ND-NC. --}}
<div class="modal fade" id="asignarKilosRemitoModal" tabindex="-1" role="dialog" aria-labelledby="asignarKilosRemitoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="asignarKilosRemitoLabel">Asignar kilos (F5)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row tm-transporte-campo">
                    <label class="col-sm-4 col-form-label">Reparto</label>
                    <div class="col-sm-8">
                        <input type="hidden" class="transporte_id" id="asigna_kilos_transporte_id" value="">
                        <div class="input-group">
                            <input type="text" id="asigna_kilos_codigotransporte" class="form-control codigotransporte"
                                   placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off" autofocus>
                            <input type="text" id="asigna_kilos_nombretransporte" class="form-control nombretransporte" readonly placeholder="Nombre">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary consultatransporte" title="Consulta repartos (F1)">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <small class="form-text text-muted">F1 o lupa para consultar. Usa el reparto del formulario si ya est&aacute; cargado.</small>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-sm-4 col-form-label">Porcentaje</label>
                    <div class="col-sm-4">
                        <input type="number" min="0" max="100" step="0.1" id="asigna_kilos_porcentaje" class="form-control" value="0">
                    </div>
                    <div class="col-sm-4 col-form-label text-muted">
                        Sobre kilos de hoy en Villafranca (FAC+ND&minus;NC)
                    </div>
                </div>
                <div id="asigna_kilos_aviso" class="alert alert-warning d-none" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancela</button>
                <button type="button" class="btn btn-primary" id="aceptaAsignarKilosRemito">Acepta</button>
            </div>
        </div>
    </div>
</div>
