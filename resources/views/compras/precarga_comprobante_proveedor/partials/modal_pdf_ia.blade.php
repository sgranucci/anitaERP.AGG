<div class="modal fade" id="modal-precarga-pdf-ia" tabindex="-1" role="dialog" aria-labelledby="modalPrecargaPdfIaLabel" aria-hidden="true"
     data-preview-url="{{ $pdfIaPreviewUrl ?? route('precarga_comprobante_proveedor_pdf_ia_preview') }}"
     data-resolver-oc-url="{{ $pdfIaResolverOcUrl ?? route('precarga_comprobante_proveedor_pdf_ia_resolver_oc') }}"
     data-confirmar-url="{{ $pdfIaConfirmarUrl ?? route('precarga_comprobante_proveedor_pdf_ia_confirmar') }}"
     data-descartar-url="{{ $pdfIaDescartarUrl ?? route('descartar_ai_decision') }}"
     data-proveedor-id-selector="{{ $pdfIaProveedorIdSelector ?? '' }}"
     data-overlay-id="{{ $pdfIaOverlayId ?? '' }}">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalPrecargaPdfIaLabel">
                    <i class="fa fa-magic"></i> Cargar factura PDF con IA
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {{-- Visible en upload, OC manual y preview: no anidar dentro de un paso oculto --}}
                <div id="precarga-pdf-ia-error" class="alert alert-danger d-none"></div>

                <div id="precarga-pdf-ia-paso-upload">
                    <p class="text-muted small mb-2">
                        Identifica empresa, proveedor, conceptos IVA (sin artículos), moneda y cotización.
                        El <strong>tipo contable exacto</strong> (FGA/FIA/FCA…) lo resuelve el ERP con la OC vía API listaConcepto.
                        Si no detecta la OC, podrá ingresarla manualmente (6 dígitos, ej. <code>214482</code>).
                    </p>
                    <div class="form-group">
                        <label for="precarga-pdf-ia-archivo">Archivo PDF</label>
                        <input type="file" id="precarga-pdf-ia-archivo" class="form-control-file" accept="application/pdf,.pdf">
                    </div>
                    <div class="form-group">
                        <label for="precarga-pdf-ia-numero-oc">Orden de compra <span class="text-muted">(opcional al subir; 6 dígitos)</span></label>
                        <input type="text" id="precarga-pdf-ia-numero-oc" class="form-control form-control-sm" maxlength="6"
                               pattern="\d{0,6}" placeholder="Ej. 214482" inputmode="numeric">
                    </div>
                </div>

                <div id="precarga-pdf-ia-paso-oc-manual" class="d-none">
                    <div class="alert alert-warning">
                        <strong>OC requerida.</strong> <span id="precarga-pdf-ia-oc-mensaje"></span>
                    </div>
                    <div class="form-group row align-items-end">
                        <div class="col-md-4">
                            <label for="precarga-pdf-ia-numero-oc-manual">Número de OC (6 dígitos)</label>
                            <input type="text" id="precarga-pdf-ia-numero-oc-manual" class="form-control" maxlength="6"
                                   pattern="\d{6}" placeholder="214482" inputmode="numeric"
                                   autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-warning" id="precarga-pdf-ia-btn-aplicar-oc">
                                <i class="fa fa-check"></i> Aplicar OC y validar
                            </button>
                        </div>
                    </div>
                </div>

                <div id="precarga-pdf-ia-paso-preview" class="d-none">
                    <div id="precarga-pdf-ia-advertencias" class="alert alert-warning d-none"></div>
                    <div id="precarga-pdf-ia-constatacion" class="alert alert-info d-none"></div>
                    <div class="row mb-3">
                        <div class="col-md-4"><strong>Empresa:</strong> <span id="precarga-pdf-ia-empresa"></span></div>
                        <div class="col-md-4"><strong>Proveedor:</strong> <span id="precarga-pdf-ia-proveedor"></span></div>
                        <div class="col-md-4">
                            <strong>OC:</strong> <span id="precarga-pdf-ia-oc"></span>
                            <button type="button" class="btn btn-link btn-sm p-0 ml-1" id="precarga-pdf-ia-editar-oc">Cambiar</button>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Tipo ERP:</strong> <span id="precarga-pdf-ia-tipo"></span></div>
                        <div class="col-md-3"><strong>Moneda:</strong> <span id="precarga-pdf-ia-moneda"></span></div>
                        <div class="col-md-3"><strong>Cotización:</strong> <span id="precarga-pdf-ia-cotizacion"></span></div>
                        <div class="col-md-3"><strong>Total:</strong> <span id="precarga-pdf-ia-total"></span></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Total ARCA:</strong> <span id="precarga-pdf-ia-total-arca"></span></div>
                        <div class="col-md-3"><strong>CAE:</strong> <span id="precarga-pdf-ia-cae"></span></div>
                        <div class="col-md-3"><strong>Fecha:</strong> <span id="precarga-pdf-ia-fecha"></span></div>
                        <div class="col-md-3"><strong>Estado ARCA:</strong> <span id="precarga-pdf-ia-estado-arca"></span></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6"><strong>Comprobante:</strong> <span id="precarga-pdf-ia-numero"></span></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Cód. Anita</th>
                                    <th>Concepto</th>
                                    <th>Descripción IA</th>
                                    <th class="text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody id="precarga-pdf-ia-conceptos-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="precarga-pdf-ia-btn-analizar">
                    <i class="fa fa-search"></i> Analizar PDF
                </button>
                <button type="button" class="btn btn-success d-none" id="precarga-pdf-ia-btn-confirmar">
                    <i class="fa fa-check"></i> Crear precarga
                </button>
            </div>
        </div>
    </div>
</div>
