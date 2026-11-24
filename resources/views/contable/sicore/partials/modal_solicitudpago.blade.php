<div class="modal fade" id="modal-sicore-solicitudpago" tabindex="-1" role="dialog" aria-labelledby="modal-sicore-solicitudpago-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('generar_sicore_solicitudpago') }}" id="form-sicore-solicitudpago"
                  enctype="multipart/form-data" data-enter-campos="1" autocomplete="off">
                @csrf
                <div id="sicore-sp-filtros-hidden"></div>
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-sicore-solicitudpago-titulo">Generar solicitud de pago SICORE</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="sicore-sp-loading" class="text-muted py-4 text-center d-none">
                        <i class="fa fa-spinner fa-spin"></i> Calculando liquidaci&oacute;n y preparando solicitud&hellip;
                    </div>
                    <div id="sicore-sp-error" class="alert alert-danger d-none"></div>
                    <div id="sicore-sp-contenido" class="d-none">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Empresa</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="sicore-sp-empresa" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Proveedor</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="sicore-sp-proveedor" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Sector</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="sicore-sp-sector" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Concepto</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="sicore-sp-concepto" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Fecha</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="sicore-sp-fecha" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Forma de pago</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="sicore-sp-formapago" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Moneda</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm" id="sicore-sp-moneda" readonly>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-4 col-form-label text-right pr-2 font-weight-bold">Monto</label>
                                    <div class="col-8">
                                        <input type="text" class="form-control form-control-sm text-right font-weight-bold" id="sicore-sp-monto" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label class="col-md-2 col-form-label text-right pr-2 font-weight-bold">Detalle</label>
                            <div class="col-md-10">
                                <input type="text" class="form-control form-control-sm" id="sicore-sp-detalle" readonly>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <label for="sicore-sp-vencimiento" class="col-md-2 col-form-label text-right pr-2 font-weight-bold requerido">Vencimiento</label>
                            <div class="col-md-4">
                                <input type="date" name="fecha_vencimiento" id="sicore-sp-vencimiento" class="form-control form-control-sm" required>
                                <small class="form-text text-muted">Lo carga el usuario. Obligatorio.</small>
                            </div>
                            <label for="sicore-sp-observacion" class="col-md-2 col-form-label text-right pr-2 font-weight-bold">Observaci&oacute;n</label>
                            <div class="col-md-4">
                                <input type="text" name="observacion" id="sicore-sp-observacion" class="form-control form-control-sm" maxlength="160" placeholder="(opcional)">
                            </div>
                        </div>

                        <div class="font-weight-bold mb-1">Asiento (desde config SICORE solicitud pago)</div>
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
                                <tbody id="sicore-sp-asiento"></tbody>
                            </table>
                        </div>

                        <div class="font-weight-bold mb-1">Adjunto autom&aacute;tico</div>
                        <div class="border rounded px-2 py-2 mb-3 d-flex align-items-center" style="background:#fafafa;">
                            <i class="fa fa-file-pdf text-danger mr-2"></i>
                            <span id="sicore-sp-pdf-nombre" class="mr-2"></span>
                            <span class="text-muted small flex-grow-1">— PDF de la liquidaci&oacute;n SICORE</span>
                        </div>

                        <div class="form-group mb-3">
                            <label for="sicore-sp-adjuntos" class="font-weight-bold">Adjuntos del usuario (F.744 / VEP)</label>
                            <input type="file" name="adjuntos[]" id="sicore-sp-adjuntos" class="form-control form-control-sm" multiple
                                   accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff">
                            <small class="form-text text-muted">Opcional. Pod&eacute;s subir el acuse F.744 y el VEP.</small>
                        </div>

                        <div id="sicore-sp-aviso-duplicado" class="alert alert-warning d-none mb-0">
                            <div id="sicore-sp-aviso-duplicado-msg"></div>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" name="generar_igual" id="sicore-sp-generar-igual" value="1">
                                <label class="custom-control-label" for="sicore-sp-generar-igual">Generar igual</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="sicore-sp-btn-confirmar" disabled
                            title="Completá la fecha de vencimiento para habilitar el botón.">
                        <i class="fa fa-check"></i> Confirmar y enviar a aprobaci&oacute;n
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
