<div class="modal fade" id="modal-ie-comprobante-iva" tabindex="-1" role="dialog" aria-hidden="true"
     data-preview-url="{{ route('ingresoegreso_comprobante_iva_preview_asiento') }}"
     data-pdf-ia-url="{{ route('ingresoegreso_comprobante_iva_pdf_ia_preview') }}"
     data-duplicado-url="{{ route('ingresoegreso_comprobante_iva_validar_duplicado') }}">
    <div class="modal-dialog modal-xl" role="document" style="max-width: 95%;">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fa fa-file-invoice"></i>
                    <span id="modal-ie-comprobante-iva-titulo">Comprobante IVA compras</span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ie-cp-edit-index" value="">
                <input type="hidden" id="ie-cp-pdf-temp-id" value="">
                <div class="row">
                    <div class="col-lg-6 border-right">
                        <h6 class="font-weight-bold">Conceptos IVA</h6>
                        <div id="ie-cp-conceptos-coherencia-error" class="alert alert-danger d-none small py-2"></div>
                        <div id="ie-cp-conceptos-coherencia-aviso" class="alert alert-info d-none small py-2"></div>
                        <div id="ie-cp-asiento-avisos" class="alert alert-warning d-none small"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="ie-cp-conceptos-table">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Concepto</th>
                                        <th style="width: 18%;">Cuenta DEBE</th>
                                        <th style="width: 15%;" class="text-right">Importe</th>
                                        <th style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="ie-cp-tbody-conceptos"></tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="ie-cp-agregar-concepto">
                            <i class="fa fa-plus"></i> Agregar concepto
                        </button>
                        <template id="ie-cp-template-concepto">
                            <tr class="ie-cp-fila-concepto">
                                <td>
                                    <select class="form-control form-control-sm ie-cp-concepto-id">
                                        <option value="">-- Concepto --</option>
                                        @foreach ($concepto_ivacompra_query ?? [] as $concepto)
                                            <option value="{{ $concepto->id }}"
                                                data-cuenta-debe="{{ $concepto->cuentacontabledebe_id ?? '' }}"
                                                data-tipoconcepto="{{ $concepto->tipoconcepto }}">
                                                {{ $concepto->codigo }} — {{ $concepto->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="hidden" class="ie-cp-cuenta-id">
                                        <input type="text" class="form-control ie-cp-cuenta-codigo" readonly placeholder="C&oacute;d.">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-primary btn-sm ie-cp-consulta-cuenta" title="Elegir cuenta">
                                                <i class="fa fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted ie-cp-cuenta-nombre d-block"></small>
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="form-control form-control-sm text-right ie-cp-monto" value="0">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-link text-danger p-0 ie-cp-quitar-concepto" title="Quitar">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="font-weight-bold">Encabezado del comprobante</h6>
                        <div class="form-group row">
                            <label class="col-lg-4 col-form-label">Tipo tesorer&iacute;a</label>
                            <div class="col-lg-8">
                                <select class="form-control form-control-sm" id="ie-cp-tipo-tesoreria">
                                    @foreach ($tipos_tesoreria ?? [] as $tipo)
                                        <option value="{{ $tipo }}">{{ \App\Support\Compras\ComprobanteProveedorTipoTesoreria::etiqueta($tipo) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-lg-4 col-form-label">Tipo comprobante</label>
                            <div class="col-lg-8">
                                <select class="form-control form-control-sm" id="ie-cp-tipotransaccion-compra-id">
                                    <option value="">-- Seleccionar --</option>
                                    @foreach ($tipotransaccion_compra_query ?? [] as $tipo)
                                        <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row align-items-center">
                            <label class="col-lg-4 col-form-label">N&uacute;mero</label>
                            <div class="col-lg-8">
                                <div class="form-row">
                                    <div class="col-2">
                                        <input type="text" maxlength="1" class="form-control form-control-sm" id="ie-cp-letra" placeholder="L">
                                    </div>
                                    <div class="col-4">
                                        <input type="number" class="form-control form-control-sm" id="ie-cp-sucursal" placeholder="Suc.">
                                    </div>
                                    <div class="col-6">
                                        <input type="number" class="form-control form-control-sm" id="ie-cp-numero" placeholder="Nro">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-lg-4 col-form-label">Proveedor (maestro)</label>
                            <div class="col-lg-8">
                                <div class="input-group input-group-sm">
                                    <input type="hidden" id="ie-cp-proveedor-id">
                                    <input type="text" class="form-control" id="ie-cp-proveedor-nombre" readonly placeholder="Consulta proveedor">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-primary consultaproveedor ie-cp-btn-proveedor" title="Consultar">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted">Opcional si carga proveedor eventual abajo.</small>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-lg-4 col-form-label">Proveedor eventual</label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control form-control-sm mb-1" id="ie-cp-eventual-nombre" placeholder="Raz&oacute;n social">
                                <input type="text" class="form-control form-control-sm mb-1" id="ie-cp-eventual-documento" placeholder="CUIT (solo d&iacute;gitos)">
                                <select class="form-control form-control-sm" id="ie-cp-eventual-condicioniva">
                                    <option value="">-- Condici&oacute;n IVA --</option>
                                    @foreach ($condicioniva_query ?? [] as $condicion)
                                        <option value="{{ $condicion->id }}">{{ $condicion->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-lg-4 col-form-label">Fechas</label>
                            <div class="col-lg-4">
                                <label class="small text-muted">Comprobante</label>
                                <input type="date" class="form-control form-control-sm" id="ie-cp-fecha-comprobante">
                            </div>
                            <div class="col-lg-4">
                                <label class="small text-muted">IVA</label>
                                <input type="date" class="form-control form-control-sm" id="ie-cp-fecha-iva">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-lg-4 col-form-label">Total / moneda</label>
                            <div class="col-lg-4">
                                <input type="number" step="0.01" class="form-control form-control-sm" id="ie-cp-total">
                            </div>
                            <div class="col-lg-4">
                                <select class="form-control form-control-sm" id="ie-cp-moneda-id">
                                    @foreach ($moneda_query ?? [] as $moneda)
                                        <option value="{{ $moneda->id }}">{{ $moneda->abreviatura }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-lg-4 col-form-label">CAE</label>
                            <div class="col-lg-8">
                                <input type="text" class="form-control form-control-sm" id="ie-cp-cae">
                            </div>
                        </div>

                        <hr>
                        <h6 class="font-weight-bold">Vista previa asiento (conceptos)</h6>
                        <p class="text-muted small mb-1">El haber en disponibilidades se arma autom&aacute;ticamente desde las cuentas de caja del movimiento.</p>
                        <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Cuenta</th>
                                        <th class="text-right">Debe</th>
                                        <th class="text-right">Haber</th>
                                    </tr>
                                </thead>
                                <tbody id="ie-cp-preview-asiento"></tbody>
                                <tfoot>
                                    <tr class="font-weight-bold">
                                        <td>Totales</td>
                                        <td class="text-right" id="ie-cp-preview-total-debe">0.00</td>
                                        <td class="text-right" id="ie-cp-preview-total-haber">0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div id="ie-cp-preview-error" class="alert alert-danger d-none mt-2 small"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="ie-cp-guardar-modal">
                    <i class="fa fa-check"></i> Aceptar comprobante
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-ie-comprobante-iva-pdf" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fa fa-magic"></i> Leer factura PDF</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">OCR + IA (mismo motor que comprobantes de proveedor, sin OC obligatoria).</p>
                <input type="file" id="ie-cp-pdf-archivo" class="form-control-file" accept="application/pdf,.pdf">
                <div id="ie-cp-pdf-error" class="alert alert-danger d-none mt-2"></div>
                <div id="ie-cp-pdf-advertencias" class="alert alert-warning d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-info" id="ie-cp-pdf-procesar">
                    <i class="fa fa-upload"></i> Procesar PDF
                </button>
            </div>
        </div>
    </div>
</div>
