{{-- Barra de liquidación visible en todas las solapas: aplicado − retenciones = a cobrar vs medios --}}
@php
    $esEdicionCobranza = isset($data) && ! empty($data->id);
@endphp
<div id="cob-resumen-liquidacion" class="px-3 pb-2">
    <div class="row no-gutters">
        <div class="col-6 col-md-2 pr-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">Aplicado a deuda</div>
                <div class="font-weight-bold text-right text-nowrap" id="cob-bar-aplicado" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-2 px-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">(-) Descuentos / NC</div>
                <div class="font-weight-bold text-right text-nowrap" id="cob-bar-descuentos" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-2 px-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">(-) Retenciones</div>
                <div class="font-weight-bold text-right text-nowrap" id="cob-bar-retenciones" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-2 px-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#d5f5e3;">
                <div class="small text-muted text-right">= A cobrar</div>
                <div class="font-weight-bold text-right text-nowrap" id="cob-bar-acobrar" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-2 px-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;">
                <div class="small text-muted text-right">Medios (caja + cheques)</div>
                <div class="font-weight-bold text-right text-nowrap" id="cob-bar-medios" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
        <div class="col-6 col-md-2 pl-1 mb-1">
            <div class="border rounded px-2 py-1 h-100" style="background:#eaf2f8;" id="cob-bar-dif-wrap">
                <div class="small text-muted text-right">Cuadra (medios − a cobrar)</div>
                <div class="font-weight-bold text-right text-nowrap" id="cob-bar-dif" style="font-size:1.05rem;">0,00</div>
            </div>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-1">
        <p class="small text-muted mb-0 pr-2">
            Liquidación: aplicado − descuentos − retenciones = a cobrar. Los medios (caja/cheques) deben igualar ese neto.
        </p>
        @if ($esEdicionCobranza)
            <div class="text-nowrap mb-0">
                <strong>
                    {{ $data->tipotransaccioncajas->nombre ?? 'Cobranza' }}
                    {{ $data->numerotransaccion ?? '' }}
                </strong>
                @if (can('emitir-cobranza', false))
                    <a class="btn btn-sm btn-secondary ml-2" target="_blank" rel="noopener"
                       href="{{ route('listar_una_cobranza', ['id' => $data->id]) }}">
                        <i class="fa fa-print"></i> Imprimir
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
