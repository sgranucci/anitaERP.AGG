<div class="modal fade" id="modal-canon-ent-solicitudpago" tabindex="-1" role="dialog" aria-labelledby="modal-canon-ent-solicitudpago-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('generar_canon_entidades_solicitudpago') }}" id="form-canon-ent-solicitudpago" data-enter-campos="1" autocomplete="off">
                @csrf
                <div id="canon-ent-sp-filtros-hidden"></div>
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-canon-ent-solicitudpago-titulo">Generar solicitudes de pago</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="canon-ent-sp-loading" class="text-muted py-4 text-center d-none">
                        <i class="fa fa-spinner fa-spin"></i> Preparando solicitudes&hellip;
                    </div>
                    <div id="canon-ent-sp-error" class="alert alert-danger d-none"></div>

                    <div id="canon-ent-sp-contenido" class="d-none">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Empresa</label>
                                    <div class="col-8"><input type="text" class="form-control form-control-sm" id="canon-ent-sp-empresa" readonly></div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Proveedor</label>
                                    <div class="col-8"><input type="text" class="form-control form-control-sm" id="canon-ent-sp-proveedor" readonly></div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Sector</label>
                                    <div class="col-8"><input type="text" class="form-control form-control-sm" id="canon-ent-sp-sector" readonly></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Fecha</label>
                                    <div class="col-8"><input type="text" class="form-control form-control-sm" id="canon-ent-sp-fecha" readonly></div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Forma de pago</label>
                                    <div class="col-8"><input type="text" class="form-control form-control-sm" id="canon-ent-sp-formapago" readonly></div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Moneda</label>
                                    <div class="col-8"><input type="text" class="form-control form-control-sm" id="canon-ent-sp-moneda" readonly></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group row mb-2">
                            <label for="canon-ent-sp-vencimiento" class="col-md-2 col-form-label text-right pr-2 font-weight-bold requerido">Vencimiento</label>
                            <div class="col-md-4">
                                <input type="date" name="fecha_vencimiento" id="canon-ent-sp-vencimiento" class="form-control form-control-sm" required>
                                <small class="form-text text-muted">Uno solo para las dos solicitudes. Obligatorio.</small>
                            </div>
                            <label for="canon-ent-sp-observacion" class="col-md-2 col-form-label text-right pr-2 font-weight-bold">Observaci&oacute;n</label>
                            <div class="col-md-4">
                                <input type="text" name="observacion" id="canon-ent-sp-observacion" class="form-control form-control-sm" maxlength="160" placeholder="(opcional)">
                            </div>
                        </div>

                        <div id="canon-ent-sp-bloques"></div>

                        <div class="font-weight-bold mt-3 mb-1">Adjunto de las dos solicitudes (se genera solo)</div>
                        <div class="border rounded px-2 py-2 mb-3 d-flex align-items-center" style="background:#fafafa;">
                            <i class="fa fa-file-pdf text-danger mr-2"></i>
                            <span id="canon-ent-sp-pdf-nombre" class="mr-2"></span>
                            <span class="text-muted small flex-grow-1">— el mismo formulario que «PDF para SP»</span>
                            <a href="#" id="canon-ent-sp-pdf-ver" target="_blank" rel="noopener" class="btn btn-link btn-sm p-0">Ver</a>
                        </div>

                        <div id="canon-ent-sp-aviso-duplicado" class="alert alert-warning d-none mb-2">
                            <div id="canon-ent-sp-aviso-duplicado-msg"></div>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" name="generar_igual" id="canon-ent-sp-generar-igual" value="1">
                                <label class="custom-control-label" for="canon-ent-sp-generar-igual">Generar igual</label>
                            </div>
                        </div>
                        <div id="canon-ent-sp-aviso-dias" class="alert alert-warning d-none mb-0">
                            <div id="canon-ent-sp-aviso-dias-msg"></div>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" name="confirmo_dias" id="canon-ent-sp-confirmo-dias" value="1">
                                <label class="custom-control-label" for="canon-ent-sp-confirmo-dias">Confirmo que es correcto</label>
                            </div>
                        </div>
                        <div id="canon-ent-sp-aviso-bingo" class="alert alert-info d-none mt-2 mb-0"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="canon-ent-sp-btn-confirmar" disabled
                            title="Completá la fecha de vencimiento para habilitar el botón.">
                        <i class="fa fa-check"></i> Confirmar y enviar a aprobaci&oacute;n
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
