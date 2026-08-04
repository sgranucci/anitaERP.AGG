<div class="card card-outline card-info form3 mb-0 border-0 shadow-none" style="display: none;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fa fa-file-invoice"></i> Comprobantes IVA compras</h4>
            <div>
                <button type="button" class="btn btn-info btn-sm" id="ie-btn-pdf-ia-comprobante">
                    <i class="fa fa-magic"></i> Cargar PDF (OCR/IA)
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="ie-btn-nuevo-comprobante-iva">
                    <i class="fa fa-plus"></i> Agregar comprobante
                </button>
            </div>
        </div>
        <p class="text-muted small">
            Registra facturas de fondo fijo, gastos bancarios u otros comprobantes de IVA compras.
            Se graban en el mismo maestro que comprobantes de proveedor; el haber contable sale de las cuentas de caja del movimiento.
        </p>
        <input type="hidden" name="comprobantes_ivacompra_json" id="comprobantes_ivacompra_json" value="">
        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="tabla-comprobantes-iva-ie"
                   data-validar-url="{{ route('ingresoegreso_comprobante_iva_validar_totales') }}">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Tipo</th>
                        <th>Comprobante</th>
                        <th>Proveedor</th>
                        <th>Fecha IVA</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-comprobantes-iva-ie"></tbody>
            </table>
        </div>
        <div class="row mt-2">
            <div class="col-md-6">
                <strong>Total comprobantes:</strong>
                <span id="ie-total-comprobantes-iva">0.00</span>
            </div>
        </div>
    </div>
</div>

@include('caja.ingresoegreso.partials.modal_comprobante_ivacompra')

@php
    $ieConceptosCuentaMetaJson = $conceptos_cuenta_meta ?? [];
    $ieComprobantesInicialJson = $comprobantes_ivacompra_inicial ?? [];
@endphp
<script type="application/json" id="ie-conceptos-cuenta-meta">@json($ieConceptosCuentaMetaJson)</script>
<script type="application/json" id="ie-comprobantes-iva-inicial">@json($ieComprobantesInicialJson)</script>
