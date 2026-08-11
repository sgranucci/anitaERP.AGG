{{-- Modal entregas semanales OC (fecha / cantidad). También reutilizable en recepción (solo lectura). --}}
@php
    $soloLecturaModal = ! empty($soloLectura);
@endphp
<div class="modal fade" id="modalOcEntregaSemanal" tabindex="-1" role="dialog" aria-labelledby="modalOcEntregaSemanalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalOcEntregaSemanalLabel">Entregas semanales</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="oc-entrega-semanal-subtitulo">
                    Cargue fecha y cantidad por semana. La suma se aplica a la cantidad de la línea en la grilla.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2" id="oc-entrega-semanal-tabla">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:40%;">Fecha de entrega</th>
                                <th style="width:35%;">Cantidad</th>
                                <th style="width:25%;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="oc-entrega-semanal-tbody"></tbody>
                        <tfoot>
                            <tr>
                                <th class="text-right">Total</th>
                                <th id="oc-entrega-semanal-total" class="text-right font-weight-bold">0</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if (! $soloLecturaModal)
                    <button type="button" class="btn btn-outline-primary btn-sm" id="oc-entrega-semanal-agregar">
                        + Agregar entrega
                    </button>
                @endif
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                @if (! $soloLecturaModal)
                    <button type="button" class="btn btn-outline-danger btn-sm" id="oc-entrega-semanal-limpiar">Quitar todas</button>
                    <button type="button" class="btn btn-primary btn-sm" id="oc-entrega-semanal-aplicar">Aplicar a la línea</button>
                @endif
            </div>
        </div>
    </div>
</div>
<template id="oc-entrega-semanal-template-renglon">
    <tr class="oc-entrega-semanal-renglon">
        <td>
            <input type="date" class="form-control form-control-sm oc-entrega-fecha" value="">
        </td>
        <td>
            <input type="number" step="0.0001" min="0" class="form-control form-control-sm oc-entrega-cantidad text-right" value="">
        </td>
        <td class="text-center align-middle">
            <button type="button" class="btn-accion-tabla oc-entrega-quitar" title="Quitar entrega">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
