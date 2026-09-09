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

        <div class="table-responsive mb-2">
            <table class="table table-sm table-bordered table-hover mb-0" id="pp-retenciones-grilla">
                <thead style="background:#eaf2f8;">
                    <tr>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th class="text-right">Base</th>
                        <th class="text-right">Alícuota</th>
                        <th class="text-right">Importe</th>
                        <th class="text-center" style="width:9rem;">Cálculo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" class="text-muted text-center">Sin calcular — aplique comprobantes o pulse Calcular</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-right">Total</th>
                        <th class="text-right" id="pp-retenciones-total">0,00</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div id="pp-retenciones-pie" class="small text-muted mb-3"></div>

        @if($esEdicion && $retenciones->isNotEmpty())
            <h5 class="mt-3 mb-2" style="font-size:1rem;">Certificados grabados</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead style="background:#f4f6f7;">
                        <tr>
                            <th>Tipo</th>
                            <th class="text-right">Importe</th>
                            <th>Certificado</th>
                            <th class="text-center" style="width:6rem;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($retenciones as $ret)
                            <tr>
                                <td>{{ $ret->etiquetaTipo() }}</td>
                                <td class="text-right text-nowrap">{{ number_format((float) $ret->importe, 2, ',', '.') }}</td>
                                <td>{{ $ret->nro_certificado ?: '—' }}</td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-outline-secondary"
                                       target="_blank" rel="noopener"
                                       href="{{ route('imprimir_retencion_pagoproveedor', [$data->id, $ret->id]) }}">
                                        Imprimir
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="text-muted small mt-3 mb-0">
            Las retenciones se calculan al aplicar la deuda y al abrir esta solapa, y se vuelven a grabar al guardar la OP (certificados Anita G/V/T/S).
            Use <strong>Ver cálculo</strong> en cada fila para auditar mínimos, acumulado de Ganancias e IIBB.
        </p>
    </div>
</div>

<div class="modal fade" id="pp-modal-calculo-retencion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="pp-modal-retencion-titulo">Cálculo de retención</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="pp-modal-retencion-body"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
