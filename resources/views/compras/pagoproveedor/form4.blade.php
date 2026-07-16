@php
    $esEdicion = isset($data) && isset($data->id);
    $retenciones = ($data->pagoproveedor_retenciones ?? collect());
@endphp
<div class="card form4" style="display: none">
    <h3>Retenciones
        <button type="button" class="btn btn-sm btn-outline-primary ml-2" id="btn-calcular-retenciones">Calcular</button>
    </h3>
    <div class="card-body">
        <div id="pp-retenciones-resumen" class="mb-2 text-muted">Sin calcular</div>
        @if($esEdicion && $retenciones->isNotEmpty())
            <ul class="mb-0">
                @foreach($retenciones as $ret)
                    <li>
                        {{ $ret->etiquetaTipo() }} — {{ number_format((float) $ret->importe, 2, ',', '.') }}
                        @if($ret->nro_certificado) (cert. {{ $ret->nro_certificado }}) @endif
                        <a target="_blank" rel="noopener" href="{{ route('imprimir_retencion_pagoproveedor', [$data->id, $ret->id]) }}">imprimir</a>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="text-muted small mt-3 mb-0">
            Las retenciones se recalculan y graban al guardar la OP (certificados Anita G/V/T/S).
        </p>
    </div>
</div>
