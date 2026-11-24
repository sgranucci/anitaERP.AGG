<div class="modal fade" id="modal-canon-solicitudpago" tabindex="-1" role="dialog" aria-labelledby="modal-canon-solicitudpago-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('generar_canon_municipal_solicitudpago') }}" id="form-canon-solicitudpago" data-enter-campos="1" autocomplete="off">
                @csrf
                <div id="canon-sp-filtros-hidden"></div>
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-canon-solicitudpago-titulo">Generar solicitud de pago</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="canon-sp-loading" class="text-muted py-4 text-center d-none">
                        <i class="fa fa-spinner fa-spin"></i> Preparando solicitud&hellip;
                    </div>
                    <div id="canon-sp-error" class="alert alert-danger d-none"></div>
                    <div id="canon-sp-contenido" class="d-none">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Empresa</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="canon-sp-empresa" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Proveedor</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="canon-sp-proveedor" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Sector</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="canon-sp-sector" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Concepto</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="canon-sp-concepto" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Fecha</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="canon-sp-fecha" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Forma de pago</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="canon-sp-formapago" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Moneda</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="canon-sp-moneda" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Monto</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm text-right font-weight-bold" id="canon-sp-monto" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-md-2 col-form-label text-right pr-2 font-weight-bold">Detalle</label>
                            <div class="col-md-10">
                                <input type="text" class="form-control form-control-sm" id="canon-sp-detalle" readonly>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label for="canon-sp-vencimiento" class="col-md-2 col-form-label text-right pr-2 font-weight-bold requerido">Vencimiento</label>
                            <div class="col-md-4">
                                <input type="date" name="fecha_vencimiento" id="canon-sp-vencimiento" class="form-control form-control-sm" required>
                                <small class="form-text text-muted">Lo carga el usuario. Obligatorio.</small>
                            </div>
                            <label for="canon-sp-observacion" class="col-md-2 col-form-label text-right pr-2 font-weight-bold">Observaci&oacute;n</label>
                            <div class="col-md-4">
                                <input type="text" name="observacion" id="canon-sp-observacion" class="form-control form-control-sm" maxlength="160" placeholder="(opcional)">
                            </div>
                        </div>

                        <div class="font-weight-bold mb-1">Asiento (desde Configuraci&oacute;n canon municipal)</div>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Cuenta</th>
                                        <th>Centro de costo</th>
                                        <th class="text-center">D/H</th>
                                        <th class="text-right">Debe</th>
                                        <th class="text-right">Haber</th>
                                    </tr>
                                </thead>
                                <tbody id="canon-sp-asiento"></tbody>
                            </table>
                        </div>

                        <div class="font-weight-bold mb-1">Adjunto (se genera solo)</div>
                        <div class="border rounded px-2 py-2 mb-3 d-flex align-items-center" style="background:#fafafa;">
                            <i class="fa fa-file-pdf text-danger mr-2"></i>
                            <span id="canon-sp-pdf-nombre" class="mr-2"></span>
                            <span class="text-muted small flex-grow-1">— misma nota que &laquo;Descargar nota municipal&raquo;</span>
                            <a href="#" id="canon-sp-pdf-ver" target="_blank" rel="noopener" class="btn btn-link btn-sm p-0">Ver</a>
                        </div>

                        <div id="canon-sp-aviso-duplicado" class="alert alert-warning d-none mb-2">
                            <div id="canon-sp-aviso-duplicado-msg"></div>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" name="generar_igual" id="canon-sp-generar-igual" value="1">
                                <label class="custom-control-label" for="canon-sp-generar-igual">Generar igual</label>
                            </div>
                        </div>
                        <div id="canon-sp-aviso-dias" class="alert alert-warning d-none mb-0">
                            <div id="canon-sp-aviso-dias-msg"></div>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" name="confirmo_dias" id="canon-sp-confirmo-dias" value="1">
                                <label class="custom-control-label" for="canon-sp-confirmo-dias">Confirmo que es correcto</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="canon-sp-btn-confirmar" disabled
                            title="Completá la fecha de vencimiento para habilitar el botón.">
                        <i class="fa fa-check"></i> Confirmar y enviar a aprobaci&oacute;n
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
