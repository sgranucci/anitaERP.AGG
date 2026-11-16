{{-- Preview del comprobante FAC de reemplazo que se grabaría (sin emitir). --}}
@if (!empty($previewFac) && is_array($previewFac))
<div class="card card-outline card-info mb-3" id="cdm-preview-fac-panel">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">
            <i class="fa fa-eye"></i> Preview de la factura a generar
        </h3>
        <span class="float-right small text-muted">Marcá las líneas a incluir. No graba hasta confirmar.</span>
    </div>
    <div class="card-body py-2">
        <div id="cdm-preview-fac-alerta" class="alert alert-warning py-2 mb-2 d-none" role="alert"></div>
        <p class="small text-muted mb-2">Elegí <strong>qué línea(s)</strong> van en esta factura. Vacío = no se emite.</p>
        <div class="table-responsive mb-2">
            <table class="table table-sm table-bordered mb-0" id="cdm-preview-fac-tabla">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:42px" class="text-center">Incluir</th>
                        <th>SKU</th>
                        <th>Descripción</th>
                        <th class="text-right" style="width:90px">Cantidad</th>
                        <th class="text-right" style="width:110px">Precio</th>
                        <th class="text-right" style="width:120px">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="cdm-preview-fac-tbody">
                    <tr>
                        <td colspan="6" class="text-muted text-center">Elegí el modo de precio en el botón de emitir.</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">Pares en esta factura</th>
                        <th class="text-right" id="cdm-preview-fac-pares">—</th>
                        <th class="text-right">Total</th>
                        <th class="text-right" id="cdm-preview-fac-total">—</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div id="cdm-preview-fac-resumen" class="small text-muted"></div>
        <div id="cdm-preview-fac-avisos" class="small text-info mt-1"></div>
    </div>
</div>
@endif
