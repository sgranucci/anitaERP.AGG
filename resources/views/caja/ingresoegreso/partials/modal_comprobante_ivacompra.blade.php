@php
    $tiposCompraIe = collect($tipotransaccion_compra_query ?? [])
        ->sortBy(function ($tipo) {
            $suspendida = strtoupper((string) ($tipo->estado ?? 'A')) === 'S' ? '1' : '0';

            return $suspendida.'|'.strtoupper((string) ($tipo->abreviatura ?? ''));
        })
        ->values();
@endphp
<style>
    #modal-ie-comprobante-iva .ie-cp-dialog { max-width: 1180px; }
    #modal-ie-comprobante-iva .modal-header { border-bottom: 0; padding-bottom: .35rem; }
    #modal-ie-comprobante-iva .ie-cp-subtitulo { opacity: .85; font-size: .8rem; font-weight: 400; }
    #modal-ie-comprobante-iva .modal-body { padding: 0; background: #f4f6f7; }
    #modal-ie-comprobante-iva .ie-cp-bloque {
        background: #fff;
        margin: .75rem 1rem;
        border: 1px solid #d5d8dc;
        border-radius: .35rem;
        overflow: hidden;
    }
    #modal-ie-comprobante-iva .ie-cp-bloque-head {
        background: #1B4F72;
        color: #fff;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        padding: .45rem .85rem;
    }
    #modal-ie-comprobante-iva .ie-cp-bloque-head.ie-cp-head-conceptos { background: #1A5276; }
    #modal-ie-comprobante-iva .ie-cp-bloque-head.ie-cp-head-asiento { background: #1B4F72; }
    #modal-ie-comprobante-iva .ie-cp-bloque-body { padding: .85rem .9rem .7rem; }
    #modal-ie-comprobante-iva .ie-cp-label {
        display: block;
        font-size: .72rem;
        font-weight: 700;
        color: #1B4F72;
        margin-bottom: .2rem;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    #modal-ie-comprobante-iva .ie-cp-tipo-badge {
        display: inline-block;
        min-width: 3.2rem;
        text-align: center;
        font-weight: 700;
        letter-spacing: .04em;
        background: #D6EAF8;
        color: #1B4F72;
        border: 1px solid #85C1E9;
    }
    #modal-ie-comprobante-iva #ie-cp-conceptos-scroll { max-height: 320px; overflow-y: auto; }
    #modal-ie-comprobante-iva #ie-cp-preview-scroll { max-height: 320px; overflow-y: auto; }
    #modal-ie-comprobante-iva .ie-cp-resumen {
        background: #EAF2F8;
        border-top: 1px solid #d5d8dc;
        padding: .45rem .85rem;
        font-size: .85rem;
    }
</style>
<div class="modal fade" id="modal-ie-comprobante-iva" tabindex="-1" role="dialog" aria-hidden="true"
     data-preview-url="{{ route('ingresoegreso_comprobante_iva_preview_asiento') }}"
     data-pdf-ia-url="{{ route('ingresoegreso_comprobante_iva_pdf_ia_preview') }}"
     data-duplicado-url="{{ route('ingresoegreso_comprobante_iva_validar_duplicado') }}"
     data-descartar-url="{{ route('descartar_ai_decision') }}">
    <div class="modal-dialog modal-xl modal-dialog-scrollable ie-cp-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fa fa-file-invoice"></i>
                        <span id="modal-ie-comprobante-iva-titulo">Comprobante IVA compras</span>
                    </h5>
                    <div class="ie-cp-subtitulo">Primero el encabezado. Los conceptos se arman solos al elegir el tipo.</div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ie-cp-edit-index" value="">
                <input type="hidden" id="ie-cp-pdf-temp-id" value="">

                <div class="ie-cp-bloque">
                    <div class="ie-cp-bloque-head">Encabezado del comprobante</div>
                    <div class="ie-cp-bloque-body">
                        <div class="form-row">
                            <div class="form-group col-md-3 mb-2">
                                <label class="ie-cp-label" for="ie-cp-tipo-tesoreria">Tipo tesorer&iacute;a</label>
                                <select class="form-control form-control-sm" id="ie-cp-tipo-tesoreria">
                                    @foreach ($tipos_tesoreria ?? [] as $tipo)
                                        <option value="{{ $tipo }}">{{ \App\Support\Compras\ComprobanteProveedorTipoTesoreria::etiqueta($tipo) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6 mb-2">
                                <label class="ie-cp-label" for="ie-cp-tipotransaccion-compra-id">Tipo de comprobante</label>
                                <div class="d-flex align-items-center" style="gap:.4rem;">
                                    <select class="form-control form-control-sm" id="ie-cp-tipotransaccion-compra-id">
                                        <option value="">-- Seleccionar --</option>
                                        @foreach ($tiposCompraIe as $tipo)
                                            <option value="{{ $tipo->id }}" data-abreviatura="{{ $tipo->abreviatura }}">
                                                {{ $tipo->abreviatura }} — {{ $tipo->nombre }}
                                                @if (strtoupper((string) ($tipo->estado ?? '')) === 'S')
                                                    (suspendida)
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="badge ie-cp-tipo-badge d-none" id="ie-cp-tipo-abreviatura"></span>
                                </div>
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="ie-cp-label">N&uacute;mero</label>
                                <div class="form-row">
                                    <div class="col-3 pr-1">
                                        <input type="text" maxlength="1" class="form-control form-control-sm text-uppercase" id="ie-cp-letra" placeholder="L" title="Letra">
                                    </div>
                                    <div class="col-4 px-1">
                                        <input type="number" class="form-control form-control-sm" id="ie-cp-sucursal" placeholder="Suc." title="Sucursal">
                                    </div>
                                    <div class="col-5 pl-1">
                                        <input type="number" class="form-control form-control-sm" id="ie-cp-numero" placeholder="Nro" title="N&uacute;mero">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-3 mb-2">
                                <label class="ie-cp-label" for="ie-cp-fecha-comprobante">Fecha comprobante</label>
                                <input type="date" class="form-control form-control-sm" id="ie-cp-fecha-comprobante">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="ie-cp-label" for="ie-cp-fecha-iva">Fecha IVA</label>
                                <input type="date" class="form-control form-control-sm" id="ie-cp-fecha-iva">
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <label class="ie-cp-label" for="ie-cp-total">Total</label>
                                <input type="number" step="0.01" class="form-control form-control-sm text-right font-weight-bold" id="ie-cp-total">
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <label class="ie-cp-label" for="ie-cp-moneda-id">Moneda</label>
                                <select class="form-control form-control-sm" id="ie-cp-moneda-id">
                                    @foreach ($moneda_query ?? [] as $moneda)
                                        <option value="{{ $moneda->id }}">{{ $moneda->abreviatura }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <label class="ie-cp-label" for="ie-cp-tipo-autorizacion">Autorizaci&oacute;n</label>
                                <select class="form-control form-control-sm" id="ie-cp-tipo-autorizacion">
                                    <option value="">—</option>
                                    @foreach (\App\Support\Compras\ComprobanteProveedorTipoAutorizacion::todos() as $tipoAut)
                                        <option value="{{ $tipoAut }}">{{ $tipoAut }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4 mb-0">
                                <label class="ie-cp-label" for="ie-cp-cae">N&ordm; CAE / CAEA / CAI</label>
                                <input type="text" class="form-control form-control-sm" id="ie-cp-cae">
                            </div>
                            <div class="form-group col-md-8 mb-0 d-flex align-items-end">
                                <small class="text-muted pb-1">CAEA puede repetirse. CAE y CAI se controlan como &uacute;nicos.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ie-cp-bloque">
                    <div class="ie-cp-bloque-head">Proveedor</div>
                    <div class="ie-cp-bloque-body">
                        <div class="form-row">
                            <div class="form-group col-lg-6 mb-2 mb-lg-0">
                                <label class="ie-cp-label" for="ie-cp-proveedor-nombre">Proveedor del maestro</label>
                                <div class="input-group input-group-sm">
                                    <input type="hidden" id="ie-cp-proveedor-id">
                                    <input type="text" class="form-control" id="ie-cp-proveedor-nombre" readonly placeholder="Consulta proveedor">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-primary consultaproveedor ie-cp-btn-proveedor" title="Consultar">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-lg-6 mb-0">
                                <label class="ie-cp-label">Proveedor eventual</label>
                                <div class="form-row">
                                    <div class="col-md-5 mb-1 mb-md-0">
                                        <input type="text" class="form-control form-control-sm" id="ie-cp-eventual-nombre" placeholder="Raz&oacute;n social">
                                    </div>
                                    <div class="col-md-3 mb-1 mb-md-0">
                                        <input type="text" class="form-control form-control-sm" id="ie-cp-eventual-documento" placeholder="CUIT">
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-control form-control-sm" id="ie-cp-eventual-condicioniva">
                                            <option value="">Condici&oacute;n IVA</option>
                                            @foreach ($condicioniva_query ?? [] as $condicion)
                                                <option value="{{ $condicion->id }}">{{ $condicion->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <small class="text-muted">Usalo si el proveedor no est&aacute; en el maestro.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mx-2 mb-2">
                    <div class="col-lg-7 px-2">
                        <div class="ie-cp-bloque mb-2" style="margin:0;">
                            <div class="ie-cp-bloque-head ie-cp-head-conceptos d-flex justify-content-between align-items-center">
                                <span>Conceptos IVA</span>
                                <button type="button" class="btn btn-sm btn-light py-0" id="ie-cp-agregar-concepto">
                                    <i class="fa fa-plus"></i> Agregar
                                </button>
                            </div>
                            <div id="ie-cp-conceptos-tipo-aviso" class="alert alert-info d-none small mb-0 rounded-0 py-2"></div>
                            <div id="ie-cp-conceptos-coherencia-error" class="alert alert-danger d-none small mb-0 rounded-0 py-2"></div>
                            <div id="ie-cp-conceptos-coherencia-aviso" class="alert alert-info d-none small mb-0 rounded-0 py-2"></div>
                            <div id="ie-cp-asiento-avisos" class="alert alert-warning d-none small mb-0 rounded-0 py-2"></div>
                            <div class="table-responsive" id="ie-cp-conceptos-scroll">
                                <table class="table table-sm table-bordered mb-0" id="ie-cp-conceptos-table">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th>Concepto</th>
                                            <th style="width: 34%;">Cuenta DEBE</th>
                                            <th style="width: 16%;" class="text-right">Importe</th>
                                            <th style="width: 4%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="ie-cp-tbody-conceptos"></tbody>
                                </table>
                            </div>
                            <div class="ie-cp-resumen d-flex justify-content-between align-items-center">
                                <span>Suma conceptos: <strong id="ie-cp-suma-conceptos">0.00</strong></span>
                                <span id="ie-cp-dif-conceptos" class="d-none"></span>
                            </div>
                            <template id="ie-cp-template-concepto">
                                <tr class="ie-cp-fila-concepto item-concepto">
                                    <td>
                                        <input type="hidden" class="concepto_ivacompra_id ie-cp-concepto-id" value="">
                                        <div class="d-flex flex-wrap align-items-center">
                                            <input type="text" class="form-control form-control-sm codigo_concepto_ivacompra mr-1"
                                                   value="" style="width:5.5rem;" autocomplete="off"
                                                   title="C&oacute;digo + Enter &middot; F1 consulta" placeholder="C&oacute;d.">
                                            <input type="text" class="form-control form-control-sm nombre_concepto_ivacompra mr-1"
                                                   value="" readonly style="min-width:7rem;flex:1;" placeholder="Descripci&oacute;n">
                                            <button type="button" class="btn btn-outline-primary btn-sm consultaconcepto_ivacompra tooltipsC flex-shrink-0"
                                                    title="Consulta conceptos (F1)">
                                                <i class="fa fa-search"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="tm-cuentacontable-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
                                            <input type="hidden" class="cuentacontable_id ie-cp-cuenta-id" value="">
                                            <input type="hidden" class="codigo_previo" value="">
                                            <button type="button" title="Consulta cuenta DEBE (F1)"
                                                    class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                                                <i class="fa fa-search text-primary"></i>
                                            </button>
                                            <input type="text" class="codigocuentacontable ie-cp-cuenta-codigo form-control form-control-sm"
                                                   style="width:5rem;flex-shrink:0;" value="" placeholder="C&oacute;d." autocomplete="off"
                                                   title="C&oacute;digo + Enter &middot; F1 consulta">
                                            <input type="text" class="nombrecuentacontable ie-cp-cuenta-nombre form-control form-control-sm text-truncate"
                                                   readonly value="" placeholder="Descripci&oacute;n" style="min-width:0;flex:1 1 auto;">
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm text-right ie-cp-monto" value="">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-link text-danger p-0 ie-cp-quitar-concepto" title="Quitar">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </div>
                    </div>
                    <div class="col-lg-5 px-2">
                        <div class="ie-cp-bloque mb-2" style="margin:0;">
                            <div class="ie-cp-bloque-head ie-cp-head-asiento">Vista previa del asiento</div>
                            <div class="px-3 pt-2">
                                <p class="text-muted small mb-2">El haber en disponibilidades sale de las cuentas de caja del movimiento.</p>
                            </div>
                            <div class="table-responsive" id="ie-cp-preview-scroll">
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
                            <div id="ie-cp-preview-error" class="alert alert-danger d-none m-2 small"></div>
                        </div>
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
