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
        text-align: left;
    }
    #modal-ie-comprobante-iva .ie-cp-fecha,
    #modal-ie-comprobante-iva .ie-cp-fecha::-webkit-datetime-edit,
    #modal-ie-comprobante-iva .ie-cp-fecha::-webkit-datetime-edit-fields-wrapper {
        text-align: left;
        justify-content: flex-start;
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
                @php
                    $tiposCompraIeMeta = $tiposCompraIe->map(function ($tipo) {
                        return [
                            'id' => (int) $tipo->id,
                            'abreviatura' => (string) ($tipo->abreviatura ?? ''),
                            'nombre' => (string) ($tipo->nombre ?? ''),
                        ];
                    })->values()->all();
                @endphp
                <script type="application/json" id="ie-tipos-compra-meta">@json($tiposCompraIeMeta)</script>

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
                                <small class="text-muted d-block mt-1">Clasifica fondo fijo, gasto bancario u otros / varios. No cambia las cuentas del asiento.</small>
                            </div>
                            <div class="form-group col-md-5 mb-2">
                                <label class="ie-cp-label" for="ie-cp-tipo-abreviatura">Tipo de comprobante</label>
                                <div class="tm-tipotransaccion-compra-campo d-flex flex-nowrap align-items-center w-100" style="gap:4px;">
                                    <input type="hidden" id="ie-cp-tipotransaccion-compra-id" class="tipotransaccion_compra_id" value="">
                                    <button type="button" title="Consulta tipos de comprobante (F1)" class="btn-accion-tabla consultatipotransaccioncompra flex-shrink-0">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" class="form-control form-control-sm abreviaturatipotransaccioncompra text-uppercase"
                                        id="ie-cp-tipo-abreviatura" value=""
                                        placeholder="Abrev." title="Abreviatura; Enter valida y sigue; F1 consulta"
                                        autocomplete="off" style="width:5.5rem; flex-shrink:0;">
                                    <input type="text" class="form-control form-control-sm nombretipotransaccioncompra text-truncate"
                                        id="ie-cp-tipo-nombre" value="" placeholder="Descripci&oacute;n" readonly
                                        style="min-width:0; flex:1 1 auto;">
                                </div>
                                <small class="text-muted d-block mt-1">Abreviatura + Enter &middot; F1 o lupa. Al elegirlo se cargan sus conceptos.</small>
                            </div>
                            <div class="form-group col-md-4 mb-2">
                                <label class="ie-cp-label">N&uacute;mero</label>
                                <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                                    <input type="text" maxlength="1" class="form-control form-control-sm text-uppercase text-center" id="ie-cp-letra" placeholder="L" title="Letra" autocomplete="off" style="width:2.6rem; flex:0 0 2.6rem;">
                                    <span class="text-muted">#</span>
                                    <input type="number" class="form-control form-control-sm" id="ie-cp-sucursal" placeholder="Pto." title="Punto de venta" autocomplete="off" style="width:5.5rem; flex:0 0 5.5rem;">
                                    <span class="text-muted">#</span>
                                    <input type="number" class="form-control form-control-sm" id="ie-cp-numero" placeholder="Nro" title="N&uacute;mero" autocomplete="off" style="min-width:0; flex:1;">
                                </div>
                                <div id="ie-cp-aviso-sucursal" class="text-danger small d-none mt-1">El punto de venta no puede ser 0.</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-3 mb-2">
                                <label class="ie-cp-label" for="ie-cp-fecha-comprobante">Fecha comprobante</label>
                                <input type="date" class="form-control form-control-sm ie-cp-fecha" id="ie-cp-fecha-comprobante">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="ie-cp-label" for="ie-cp-fecha-iva">Fecha IVA</label>
                                <input type="date" class="form-control form-control-sm ie-cp-fecha" id="ie-cp-fecha-iva">
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <label class="ie-cp-label text-right" for="ie-cp-total">Total</label>
                                <input type="number" step="0.01" class="form-control form-control-sm text-right font-weight-bold" id="ie-cp-total" title="Total de la factura. Enter lo valida y pasa al primer importe">
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <label class="ie-cp-label" for="ie-cp-moneda-id">Moneda</label>
                                <select class="form-control form-control-sm" id="ie-cp-moneda-id">
                                    @foreach ($moneda_query ?? [] as $moneda)
                                        <option value="{{ $moneda->id }}">{{ $moneda->abreviatura }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row d-none" id="ie-cp-fila-autorizacion">
                            <div class="form-group col-md-3 mb-0">
                                <label class="ie-cp-label" for="ie-cp-tipo-autorizacion">Autorizaci&oacute;n (IA)</label>
                                <select class="form-control form-control-sm" id="ie-cp-tipo-autorizacion">
                                    <option value="">—</option>
                                    @foreach (\App\Support\Compras\ComprobanteProveedorTipoAutorizacion::todos() as $tipoAut)
                                        <option value="{{ $tipoAut }}">{{ $tipoAut }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4 mb-0">
                                <label class="ie-cp-label" for="ie-cp-cae">N&ordm; CAE / CAEA / CAI (IA)</label>
                                <input type="text" class="form-control form-control-sm" id="ie-cp-cae" readonly>
                            </div>
                            <div class="form-group col-md-5 mb-0 d-flex align-items-end">
                                <small class="text-muted pb-1">Solo se completa si el PDF lo ley&oacute; la IA.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ie-cp-bloque">
                    <div class="ie-cp-bloque-head">Proveedor</div>
                    <div class="ie-cp-bloque-body">
                        <div class="form-group row align-items-center mb-2 tm-proveedor-campo" id="ie-cp-div-proveedor">
                            <label class="col-lg-2 ie-cp-label mb-0" for="ie-cp-proveedor-codigo">Proveedor</label>
                            <div class="col-lg-10">
                                <input type="hidden" id="ie-cp-proveedor-id" class="proveedor_id" value="">
                                <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                                    <input type="text" class="form-control form-control-sm codigoproveedor" id="ie-cp-proveedor-codigo"
                                        value="" style="width:6rem; flex-shrink:0;" autocomplete="off"
                                        placeholder="C&oacute;digo" title="C&oacute;digo + Enter &middot; F1 consulta">
                                    <input type="text" class="form-control form-control-sm nombreproveedor" id="ie-cp-proveedor-nombre"
                                        value="" readonly placeholder="Nombre" style="min-width:0; flex:1;">
                                    <button type="button" title="Consulta proveedores (F1)" class="btn btn-outline-primary btn-sm consultaproveedor flex-shrink-0">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-1">C&oacute;digo + Enter &middot; F1 o lupa. El mismo maestro que el resto del sistema.</small>
                            </div>
                        </div>
                        <div class="form-group row align-items-center mb-0" id="ie-cp-eventual-bloque">
                            <label class="col-lg-2 ie-cp-label mb-0" for="ie-cp-eventual-nombre">Eventual</label>
                            <div class="col-lg-10">
                                <div class="form-row">
                                    <div class="col-md-5 mb-1 mb-md-0">
                                        <input type="text" class="form-control form-control-sm" id="ie-cp-eventual-nombre" placeholder="Raz&oacute;n social">
                                    </div>
                                    <div class="col-md-3 mb-1 mb-md-0">
                                        <input type="text" class="form-control form-control-sm" id="ie-cp-eventual-documento"
                                            placeholder="XX-XXXXXXXX-X" maxlength="13" autocomplete="off"
                                            title="CUIT" oninput="formatarCUIT(this)">
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
                                <small class="text-muted">Si el proveedor no est&aacute; en el maestro. El CUIT lleva los guiones al tipear.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mx-2 mb-2">
                    <div class="col-lg-5 px-2">
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
                                            <th style="width: 8rem;" class="text-right">Importe</th>
                                            <th style="width: 2rem;"></th>
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
                    <div class="col-lg-7 px-2">
                        <div class="ie-cp-bloque mb-2" style="margin:0;">
                            <div class="ie-cp-bloque-head ie-cp-head-asiento">Vista previa del asiento</div>
                            <div class="px-3 pt-2">
                                <p class="text-muted small mb-2">Impuestos a la cuenta del concepto. El neto sin COM es gasto abierto: la cuenta se indica ac&aacute; y se puede repartir en m&aacute;s d&eacute;bitos. Si la factura es menor que el pago, la diferencia se imputa al concepto de gasto.</p>
                            </div>
                            <div class="table-responsive" id="ie-cp-preview-scroll">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th>Cuenta</th>
                                            <th class="text-right">Debe</th>
                                            <th class="text-right">Haber</th>
                                            <th style="width:2rem;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="ie-cp-preview-asiento"></tbody>
                                    <tfoot>
                                        <tr class="font-weight-bold">
                                            <td>Totales</td>
                                            <td class="text-right" id="ie-cp-preview-total-debe">0.00</td>
                                            <td class="text-right" id="ie-cp-preview-total-haber">0.00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div id="ie-cp-debe-gasto-barra" class="px-3 py-2 border-top d-none">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="ie-cp-debe-gasto-agregar">
                                    <i class="fa fa-plus"></i> Agregar cuenta de gasto
                                </button>
                                <span class="small text-muted ml-2" id="ie-cp-debe-gasto-aviso"></span>
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
