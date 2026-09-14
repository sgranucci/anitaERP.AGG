<div class="modal fade" id="modalRechazoNdCheque" tabindex="-1" role="dialog" aria-labelledby="modalRechazoNdChequeLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRechazoNdChequeLabel">Rechazo de cheque — nota de d&eacute;bito</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="rechazo-nd-config-error" class="alert alert-warning py-2" style="display:none;"></div>
                <p class="mb-2">
                    Cheque: <strong id="rechazo-nd-ref"></strong>
                    — Cliente: <strong id="rechazo-nd-cliente"></strong>
                    — Monto: <strong id="rechazo-nd-monto"></strong>
                </p>
                <p class="text-muted small mb-3">
                    PV: <strong id="rechazo-nd-pv"></strong>
                    <span id="rechazo-nd-modo-fe"></span>
                </p>
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
                                <th style="width:12%;">Concepto ID</th>
                                <th>Descripci&oacute;n</th>
                                <th style="width:12%;">Cant.</th>
                                <th style="width:16%;">Precio</th>
                                <th style="width:8%;"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-rechazo-nd-lineas"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="rechazo_nd_agregar_linea">+ Agregar rengl&oacute;n</button>
                <p class="mt-3 mb-0">
                    Total l&iacute;neas: <strong id="rechazo-nd-total">0.00</strong>
                </p>
                <p class="small text-muted mt-2 mb-0">
                    Emisi&oacute;n por <strong>concepto de venta</strong> (sin art&iacute;culo), igual que el facturador mostrador. Si ARCA falla, el cheque no se marca rechazado.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" id="rechazo_nd_emitir">
                    <i class="fa fa-ban"></i> Rechazar y emitir ND
                </button>
            </div>
        </div>
    </div>
</div>

<template id="template-rechazo-nd-linea">
    <tr class="item-rechazo-nd-linea">
        <td>
            <input type="number" class="form-control form-control-sm rechazo-nd-concepto-id" min="1" step="1" value="" />
        </td>
        <td>
            <input type="text" class="form-control form-control-sm rechazo-nd-descripcion" maxlength="255" value="" />
        </td>
        <td>
            <input type="number" class="form-control form-control-sm rechazo-nd-cantidad" min="0.0001" step="0.0001" value="1" />
        </td>
        <td>
            <input type="number" class="form-control form-control-sm rechazo-nd-precio" min="0" step="0.01" value="0" />
        </td>
        <td class="text-center">
            <button type="button" class="btn-accion-tabla rechazo-nd-quitar-linea tooltipsC" title="Quitar rengl&oacute;n">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
