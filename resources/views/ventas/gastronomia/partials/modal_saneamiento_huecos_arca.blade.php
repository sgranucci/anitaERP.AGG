<div class="modal fade" id="modal-saneamiento-huecos-arca" tabindex="-1" role="dialog" aria-labelledby="modal-saneamiento-huecos-arca-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="modal-saneamiento-huecos-arca-titulo">Saneamiento fiscal ARCA</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="saneamiento-huecos-arca-loading" class="text-muted d-none">
                    Consultando ARCA&hellip;
                </div>
                <div id="saneamiento-huecos-arca-aviso" class="alert alert-info d-none" role="alert"></div>
                <div id="saneamiento-huecos-arca-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="saneamiento-huecos-arca-contenido" class="d-none">
                    <p class="mb-2">Se recuperarán las FAC autorizadas en ARCA ausentes en ERP y se emitirá <strong>una sola NC</strong> por el total del lote (PeriodoAsoc = fecha de jornada).</p>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Nº FAC</th>
                                    <th>Importe ARCA</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="saneamiento-huecos-arca-tbody"></tbody>
                        </table>
                    </div>
                    <div id="saneamiento-huecos-arca-preview-nc" class="border rounded p-2 bg-light small"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" id="btn-saneamiento-huecos-arca-cancelar">Cancelar</button>
                <button type="button" class="btn btn-outline-warning d-none" id="btn-saneamiento-huecos-arca-cerrar-igual">Cerrar turno sin sanear</button>
                <button type="button" class="btn btn-primary d-none" id="btn-saneamiento-huecos-arca-ejecutar">Corregir lote confirmado</button>
            </div>
        </div>
    </div>
</div>
