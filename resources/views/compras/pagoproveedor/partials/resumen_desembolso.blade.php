{{-- Barra de totales (todas las solapas): cajas individuales --}}
@php
    $esEdicionOp = isset($data) && ! empty($data->id);
@endphp
<div id="pp-resumen-desembolso" class="px-3 pb-2">
    <div class="row no-gutters">
        <div class="col-6 col-md-3 pr-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">Aplicado a deuda (equiv. OP)</div>
                <div class="font-weight-bold text-right text-nowrap" id="pp-bar-aplicado" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-3 px-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">Medios (cuentas + cheques)</div>
                <div class="font-weight-bold text-right text-nowrap" id="pp-bar-medios" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-3 px-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">Diferencia medios − aplicado</div>
                <div class="font-weight-bold text-right text-nowrap" id="pp-bar-dif" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-3 pl-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">Dif. de cambio</div>
                <div class="font-weight-bold text-right text-nowrap" id="pp-bar-dc" style="font-size:1.05rem;">—</div>
            </div>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-1">
        <p class="small text-muted mb-0 pr-2">
            El tope es lo aplicado a la deuda. Las cuentas de caja deben cubrir ese importe; las retenciones se descuentan en el asiento.
        </p>
        @if ($esEdicionOp)
            <div class="text-nowrap mb-0">
                <strong>{{ $data->etiquetaComprobante() }}</strong>
                <a class="btn btn-sm btn-secondary ml-2" target="_blank" rel="noopener"
                   href="{{ route('imprimir_pagoproveedor', $data->id) }}">
                    <i class="fa fa-print"></i> Imprimir
                </a>
            </div>
        @endif
    </div>
</div>
