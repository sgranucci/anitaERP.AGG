<div class="modal fade" id="modalRechazoNdCheque" tabindex="-1" role="dialog" aria-labelledby="modalRechazoNdChequeLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRechazoNdChequeLabel">Rechazo de cheque — nota de débito</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="rechazo-nd-config-error" class="alert alert-warning py-2" style="display:none;"></div>
                <p class="mb-2">
                    Cheque: <strong id="rechazo-nd-ref"></strong>
                    — Cliente: <strong id="rechazo-nd-cliente"></strong>
                    — Nominal: <strong id="rechazo-nd-monto"></strong>
                </p>
                <p class="small mb-2" id="rechazo-nd-cuenta-nominal"></p>
                @include('ventas.partials.campo_consulta_puntoventa', [
                    'prefix' => 'rechazo_nd',
                    'label' => 'Punto de venta',
                    'layout' => 'form_row',
                    'inputId' => 'rechazo_nd_puntoventa_id',
                    'inputName' => 'rechazo_nd_puntoventa_id',
                    'col_label' => 'col-lg-3 control-label text-right pr-2',
                    'col_input' => 'col-lg-8',
                    'required' => true,
                ])
                <p class="text-muted small mb-3" id="rechazo-nd-modo-fe"></p>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="rechazo_nd_fecha">Fecha ND</label>
                        <input type="date" id="rechazo_nd_fecha" class="form-control form-control-sm" />
                    </div>
                    <div class="form-group col-md-8">
                        <label for="rechazo_nd_leyenda">Leyenda</label>
                        <input type="text" maxlength="255" id="rechazo_nd_leyenda" class="form-control form-control-sm" />
                    </div>
                </div>
                <div class="form-group">
                    <label for="rechazo_nd_motivo">Motivo de rechazo (opcional)</label>
                    <input type="text" maxlength="255" id="rechazo_nd_motivo" class="form-control form-control-sm" placeholder="Sin fondos / cuenta cerrada / etc." />
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2" id="rechazo-nd-lineas-table">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Concepto</th>
                                <th>Descripción</th>
                                <th style="width:14%;">Neto</th>
                                <th style="width:16%;">IVA</th>
                                <th style="width:8%;"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-rechazo-nd-lineas"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="rechazo_nd_agregar_linea">+ Agregar gasto</button>
                <p class="mt-3 mb-1">
                    No gravado: <strong id="rechazo-nd-nogravado">0,00</strong>
                    · Gravado: <strong id="rechazo-nd-gravado">0,00</strong>
                    · Exento: <strong id="rechazo-nd-exento">0,00</strong>
                    · IVA: <strong id="rechazo-nd-iva">0,00</strong>
                    · Total ND: <strong id="rechazo-nd-total">0,00</strong>
                </p>
                <p class="small text-muted mb-2">
                    El nominal del cheque no lleva IVA. Cada gasto se carga neto: el IVA sale de la alícuota del renglón.
                </p>
                <div id="rechazo-nd-asiento" class="d-none">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Cuenta</th>
                                <th class="text-right" style="width:18%;">Debe</th>
                                <th class="text-right" style="width:18%;">Haber</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-rechazo-nd-asiento"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-info btn-sm" id="rechazo_nd_preview">Ver asiento</button>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" id="rechazo_nd_emitir">
                    <i class="fa fa-ban"></i> Rechazar y emitir ND
                </button>
            </div>
        </div>
    </div>
</div>

<template id="template-rechazo-nd-linea">
    <tr class="item-rechazo-nd-linea" data-rol="gasto">
        <td class="tm-concepto-venta-campo">
            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                <input type="hidden" class="concepto_venta_id rechazo-nd-concepto-id" value="">
                <button type="button" title="Consulta conceptos (F1)" class="btn-accion-tabla consultaconceptoventa flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigoconceptoventa" placeholder="Cód." autocomplete="off" style="width: 6.5rem;">
                <input type="text" class="form-control form-control-sm nombreconceptoventa text-truncate" readonly placeholder="Descripción" style="min-width: 0; flex: 1 1 auto;">
            </div>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm rechazo-nd-descripcion" maxlength="255" value="">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm rechazo-nd-precio" min="0" step="0.01" value="0">
        </td>
        <td>
            <select class="form-control form-control-sm rechazo-nd-impuesto"></select>
        </td>
        <td class="text-center">
            <button type="button" class="btn-accion-tabla rechazo-nd-quitar-linea tooltipsC" title="Quitar renglón">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>

@include('includes.ventas.modalconsultapuntoventa')
@include('includes.ventas.modalconsultaconceptoventa')
<style>
    #consultapuntoventaModal,
    #consultaconceptoventaModal { z-index: 1065; }
</style>
