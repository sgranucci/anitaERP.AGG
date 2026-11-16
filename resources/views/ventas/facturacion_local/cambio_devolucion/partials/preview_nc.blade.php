{{-- Preview NC del reemplazo y/o de lo a devolver. --}}
@if (!empty($previewNcReemplazo) && is_array($previewNcReemplazo))
<div class="card card-outline card-danger mb-3" id="cdm-preview-nc-reemplazo-panel">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">
            <i class="fa fa-eye"></i> Preview NC del reemplazo (error)
        </h3>
        <span class="float-right small text-muted">Marcá las líneas a anular. No graba hasta confirmar.</span>
    </div>
    <div class="card-body py-2">
        <div id="cdm-preview-nc-reemplazo-alerta" class="alert alert-warning py-2 mb-2 d-none" role="alert"></div>
        <div class="table-responsive mb-2">
            <table class="table table-sm table-bordered mb-0">
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
                <tbody id="cdm-preview-nc-reemplazo-tbody"></tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">Pares</th>
                        <th class="text-right" id="cdm-preview-nc-reemplazo-pares">—</th>
                        <th class="text-right">Total</th>
                        <th class="text-right" id="cdm-preview-nc-reemplazo-total">—</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endif

@if (!empty($previewNcDevolver) && is_array($previewNcDevolver))
<div class="card card-outline card-danger mb-3" id="cdm-preview-nc-devolver-panel">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">
            <i class="fa fa-eye"></i> Preview NC de lo devuelto
        </h3>
        <span class="float-right small text-muted">Marcá las líneas a acreditar. No graba hasta confirmar.</span>
    </div>
    <div class="card-body py-2">
        <div id="cdm-preview-nc-devolver-alerta" class="alert alert-warning py-2 mb-2 d-none" role="alert"></div>
        <div class="table-responsive mb-2">
            <table class="table table-sm table-bordered mb-0">
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
                <tbody id="cdm-preview-nc-devolver-tbody"></tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">Pares</th>
                        <th class="text-right" id="cdm-preview-nc-devolver-pares">—</th>
                        <th class="text-right">Total</th>
                        <th class="text-right" id="cdm-preview-nc-devolver-total">—</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div id="cdm-preview-nc-devolver-resumen" class="small text-muted"></div>
        <div id="cdm-preview-nc-devolver-avisos" class="small text-info mt-1"></div>
    </div>
</div>
@endif
