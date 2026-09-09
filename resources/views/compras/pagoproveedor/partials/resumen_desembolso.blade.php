{{-- Barra de liquidación (todas las solapas): aplicado − retenciones = a desembolsar vs medios --}}
@php
    $esEdicionOp = isset($data) && ! empty($data->id);
@endphp
<div id="pp-resumen-desembolso" class="px-3 pb-2">
    <div class="row no-gutters">
        <div class="col-6 col-md-2 pr-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">Aplicado a deuda</div>
                <div class="font-weight-bold text-right text-nowrap" id="pp-bar-aplicado" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-2 px-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">(-) Retenciones</div>
                <div class="font-weight-bold text-right text-nowrap" id="pp-bar-retenciones" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-2 px-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#d5f5e3;">
                <div class="small text-muted text-right">= A desembolsar</div>
                <div class="font-weight-bold text-right text-nowrap" id="pp-bar-desembolsar" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-2 px-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">Medios (caja + cheques)</div>
                <div class="font-weight-bold text-right text-nowrap" id="pp-bar-medios" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-2 px-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">Cuadra (medios − desembolso)</div>
                <div class="font-weight-bold text-right text-nowrap" id="pp-bar-dif" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-2 pl-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">Dif. de cambio</div>
                <div class="font-weight-bold text-right text-nowrap" id="pp-bar-dc" style="font-size:1.05rem;">—</div>
            </div>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-1">
        <p class="small text-muted mb-0 pr-2">
            Liquidación: aplicado − retenciones = a desembolsar. Los medios (caja/cheques) deben igualar ese neto; el asiento también lleva las retenciones en Haber.
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
