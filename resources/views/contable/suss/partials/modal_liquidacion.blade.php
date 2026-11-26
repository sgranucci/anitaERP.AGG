{{-- Liquidación completa SUSS: ZIP con F2004 de la quincena + PDF de liquidación (1Q o DJ). --}}
<div class="modal fade" id="modal-suss-liquidacion" tabindex="-1" role="dialog" aria-labelledby="modal-suss-liquidacion-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="get" action="{{ route('liquidacion_suss') }}" id="form-suss-liquidacion" target="suss-liquidacion-frame">
                <div class="modal-header" style="background:#1e88e5;color:#fff;">
                    <h5 class="modal-title" id="modal-suss-liquidacion-titulo">
                        Liquidación completa SUSS — <span id="suss-liq-titulo-periodo">{{ $periodo_texto ?? '' }}</span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label text-right font-weight-bold">Tipo *</label>
                        <div class="col-sm-9">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input js-suss-tipo" name="tipo_liquidacion" id="suss-liq-tipo-1q" value="1q"
                                    @checked($tipoDefault === '1q')>
                                <label class="custom-control-label" for="suss-liq-tipo-1q">1Q (pago a cuenta)</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input js-suss-tipo" name="tipo_liquidacion" id="suss-liq-tipo-dj" value="dj"
                                    @checked($tipoDefault === 'dj')>
                                <label class="custom-control-label" for="suss-liq-tipo-dj">DJ mensual (2Q)</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="suss-liq-saf" class="col-sm-3 col-form-label text-right">Saldo a favor anterior</label>
                        <div class="col-sm-3">
                            <input type="text" inputmode="decimal" class="form-control js-suss-saf" name="saldo_favor_anterior" id="suss-liq-saf"
                                placeholder="0,00" autocomplete="off">
                        </div>
                        <label for="suss-liq-pago" class="col-sm-3 col-form-label text-right">Pago a cuenta 1Q</label>
                        <div class="col-sm-3">
                            <input type="text" inputmode="decimal" class="form-control js-suss-pago" name="pago_cuenta" id="suss-liq-pago"
                                placeholder="0,00" autocomplete="off">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="suss-liq-pago-fecha" class="col-sm-3 col-form-label text-right">Fecha del pago a cuenta</label>
                        <div class="col-sm-4">
                            <input type="date" class="form-control js-suss-pago-fecha" name="pago_cuenta_fecha" id="suss-liq-pago-fecha">
                        </div>
                        <div class="col-sm-5 small text-muted align-self-center">
                            En la 1Q, saldo a favor y pago a cuenta quedan deshabilitados.
                        </div>
                    </div>

                    <div id="suss-liq-cuadro" class="mt-2"></div>
                    <div id="suss-liq-avisos" class="mt-2"></div>

                    <div class="small text-muted mt-3" id="suss-liq-ayuda">
                        <div>El ZIP trae:</div>
                        <div class="pl-3">📄 <span id="suss-liq-zip-f2004">F2004 de la quincena</span></div>
                        <div class="pl-3">📄 <span id="suss-liq-zip-pdf">Liquidación SUSS (PDF)</span></div>
                    </div>

                    <iframe name="suss-liquidacion-frame" id="suss-liquidacion-frame" class="d-none" title="Descarga liquidación SUSS"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning" id="suss-liq-zip" disabled>
                        <i class="fa fa-download"></i> Descargar ZIP
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
