{{-- Generar solicitud de pago del VEP SUSS (1Q pago a cuenta o DJ 2Q). Vista previa por AJAX. --}}
<div class="modal fade" id="modal-suss-solicitud" tabindex="-1" role="dialog" aria-labelledby="modal-suss-solicitud-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('generar_suss_solicitudpago') }}" enctype="multipart/form-data" id="form-suss-solicitud">
                @csrf
                <div class="modal-header" style="background:#1e88e5;color:#fff;">
                    <h5 class="modal-title" id="modal-suss-solicitud-titulo">Generar solicitud de pago — Retenciones SUSS</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="sp-cargando" class="text-muted">Armando la vista previa…</div>
                    <div id="sp-error" class="alert alert-warning d-none"></div>

                    <div id="sp-contenido" class="d-none">
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label text-right font-weight-bold">Tipo *</label>
                            <div class="col-sm-9">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input js-suss-tipo" name="tipo_liquidacion" id="sp-tipo-1q" value="1q"
                                        @checked($tipoDefault === '1q')>
                                    <label class="custom-control-label" for="sp-tipo-1q">1Q (pago a cuenta)</label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input js-suss-tipo" name="tipo_liquidacion" id="sp-tipo-dj" value="dj"
                                        @checked($tipoDefault === 'dj')>
                                    <label class="custom-control-label" for="sp-tipo-dj">DJ mensual (2Q)</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="sp-saf" class="col-sm-3 col-form-label text-right">Saldo a favor anterior</label>
                            <div class="col-sm-3">
                                <input type="text" inputmode="decimal" class="form-control js-suss-saf" name="saldo_favor_anterior" id="sp-saf" placeholder="0,00" autocomplete="off">
                            </div>
                            <label for="sp-pago" class="col-sm-3 col-form-label text-right">Pago a cuenta 1Q</label>
                            <div class="col-sm-3">
                                <input type="text" inputmode="decimal" class="form-control js-suss-pago" name="pago_cuenta" id="sp-pago" placeholder="0,00" autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="sp-pago-fecha" class="col-sm-3 col-form-label text-right">Fecha del pago a cuenta</label>
                            <div class="col-sm-4">
                                <input type="date" class="form-control js-suss-pago-fecha" name="pago_cuenta_fecha" id="sp-pago-fecha">
                            </div>
                        </div>

                        <table class="table table-sm table-bordered mb-2">
                            <tbody>
                                <tr><th style="width:30%;">Empresa</th><td id="sp-empresa"></td></tr>
                                <tr><th>Fecha</th><td id="sp-fecha"></td></tr>
                                <tr><th>Proveedor</th><td id="sp-proveedor"></td></tr>
                                <tr><th>Forma de pago</th><td id="sp-forma"></td></tr>
                                <tr><th>Concepto</th><td id="sp-concepto"></td></tr>
                                <tr><th>Sector / Moneda</th><td><span id="sp-sector"></span> / <span id="sp-moneda"></span></td></tr>
                                <tr><th>Detalle</th><td id="sp-detalle"></td></tr>
                            </tbody>
                        </table>

                        <div class="form-group row">
                            <label for="sp-monto-vep" class="col-sm-3 col-form-label text-right font-weight-bold">Monto (VEP) *</label>
                            <div class="col-sm-4">
                                <input type="text" inputmode="decimal" class="form-control" name="monto_vep" id="sp-monto-vep" required autocomplete="off">
                            </div>
                            <label for="sp-vencimiento" class="col-sm-2 col-form-label text-right font-weight-bold">Vencimiento *</label>
                            <div class="col-sm-3">
                                <input type="date" class="form-control" name="fecha_vencimiento" id="sp-vencimiento" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="sp-observacion" class="col-sm-3 col-form-label text-right">Observación</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="observacion" id="sp-observacion" maxlength="160" autocomplete="off">
                            </div>
                        </div>

                        <table class="table table-sm table-bordered">
                            <thead style="background-color:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Cuenta</th>
                                    <th>Centro de costo</th>
                                    <th class="text-center">D/H</th>
                                    <th class="text-right">Debe</th>
                                    <th class="text-right">Haber</th>
                                </tr>
                            </thead>
                            <tbody id="sp-asiento"></tbody>
                        </table>

                        <div id="sp-vep-check" class="alert alert-secondary py-2 small"></div>

                        <div id="sp-confirmo-wrap" class="custom-control custom-checkbox mb-2 d-none">
                            <input type="checkbox" class="custom-control-input" name="confirmo_diferencia" id="sp-confirmo" value="1">
                            <label class="custom-control-label" for="sp-confirmo">Confirmo el VEP distinto del saldo calculado</label>
                        </div>

                        <div id="sp-duplicado-wrap" class="alert alert-warning py-2 d-none">
                            <div id="sp-duplicado-texto"></div>
                            <div class="custom-control custom-checkbox mt-1">
                                <input type="checkbox" class="custom-control-input" name="generar_igual" id="sp-generar-igual" value="1">
                                <label class="custom-control-label" for="sp-generar-igual">Generar igual</label>
                            </div>
                        </div>

                        <div id="sp-avisos" class="mb-2"></div>

                        <div class="font-weight-bold mb-1">Adjuntos</div>
                        <div class="form-group row">
                            <label for="sp-adj-vep" class="col-sm-3 col-form-label text-right">VEP *</label>
                            <div class="col-sm-9">
                                <input type="file" class="form-control-file" name="adjunto_vep" id="sp-adj-vep" accept="application/pdf,image/*" required>
                            </div>
                        </div>
                        <div class="form-group row d-none" id="sp-f996-wrap">
                            <label for="sp-adj-f996" class="col-sm-3 col-form-label text-right">DJ F.996 * <small class="text-muted">(solo 2Q)</small></label>
                            <div class="col-sm-9">
                                <input type="file" class="form-control-file" name="adjunto_f996" id="sp-adj-f996" accept="application/pdf,image/*">
                            </div>
                        </div>
                        <div class="form-group row d-none" id="sp-sire-wrap">
                            <label for="sp-adj-sire" class="col-sm-3 col-form-label text-right">Certificados SIRE <small class="text-muted">(opcional, en la 1Q)</small></label>
                            <div class="col-sm-9">
                                <input type="file" class="form-control-file" name="adjuntos_sire[]" id="sp-adj-sire" multiple accept="application/pdf,image/*">
                            </div>
                        </div>
                        <div class="small text-muted">
                            Liquidación SUSS (PDF) — se adjunta sola: <span id="sp-nombre-pdf"></span>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="sp-submit" disabled>
                        <i class="fa fa-check"></i> Confirmar y enviar a aprobación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
