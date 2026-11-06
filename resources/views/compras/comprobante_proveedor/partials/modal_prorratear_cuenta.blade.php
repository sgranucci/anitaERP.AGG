{{-- Herramienta: prorratear una cuenta del asiento (IVA/conceptos) en N centros de costo.
     Disponible en todas las opciones de carga (AGG / Ferli). No anidar form. --}}
<div class="modal fade" id="cp-prorratear-cuenta-modal" tabindex="-1" role="dialog"
     aria-labelledby="cp-prorratear-cuenta-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="cp-prorratear-cuenta-titulo">
                    <i class="fa fa-pie-chart"></i> Prorratear cuenta por centro de costo
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Abre el importe de un concepto (por ejemplo IVA) en varios centros de costo,
                    aunque el neto no tenga centro. Al aplicar se actualizan los renglones de
                    conceptos y la vista previa del asiento.
                </p>
                <div class="row mb-2">
                    <div class="col-md-7">
                        <div class="small text-muted">Cuenta</div>
                        <div class="font-weight-bold" id="cp-prorratear-cuenta-label">—</div>
                    </div>
                    <div class="col-md-5">
                        <div class="small text-muted">Importe a repartir</div>
                        <div class="font-weight-bold text-right" id="cp-prorratear-importe-label">0,00</div>
                    </div>
                </div>
                <div class="small text-muted mb-3" id="cp-prorratear-concepto-label"></div>

                <div class="d-flex flex-wrap align-items-center mb-2" style="gap:12px;">
                    <label class="mb-0 small font-weight-normal" title="Reparte el importe en partes iguales entre las filas con centro elegido">
                        <input type="checkbox" id="cp-prorratear-auto" class="align-middle mr-1" checked>
                        Dividir automáticamente en partes iguales
                    </label>
                    <div class="d-flex align-items-center small" style="gap:6px;">
                        <span>Preparar</span>
                        <input type="number" id="cp-prorratear-n" class="form-control form-control-sm"
                               min="1" max="30" value="5" style="width:4rem;" title="Cantidad de centros">
                        <span>centros</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="cp-prorratear-preparar-n">
                            Armar filas
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-2" id="cp-prorratear-tabla">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Centro de costo</th>
                                <th style="width:9rem;" class="text-right">Importe</th>
                                <th style="width:2.5rem;"></th>
                            </tr>
                        </thead>
                        <tbody id="cp-prorratear-tbody"></tbody>
                        <tfoot>
                            <tr class="font-weight-bold">
                                <td class="text-right">Suma</td>
                                <td class="text-right" id="cp-prorratear-suma">0,00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="cp-prorratear-agregar-fila">
                    <i class="fa fa-plus"></i> Agregar centro de costo
                </button>
                <span class="small ml-2" id="cp-prorratear-aviso"></span>
                <div class="alert alert-warning py-2 mt-3 mb-0 d-none" id="cp-prorratear-sin-cc" role="alert">
                    Esta cuenta no tiene centros de costo asignados en la matriz contable.
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="cp-prorratear-aplicar">
                    <i class="fa fa-check"></i> Aplicar al asiento
                </button>
            </div>
        </div>
    </div>
</div>

<template id="cp-prorratear-template-fila">
    <tr class="cp-prorratear-fila">
        <td>
            <select class="form-control form-control-sm cp-prorratear-cc" title="Centros de costo de la cuenta">
                <option value="">— Seleccione CC —</option>
            </select>
        </td>
        <td>
            <input type="text" inputmode="decimal"
                   class="form-control form-control-sm text-right js-monto-ar cp-prorratear-importe"
                   value="" title="Importe de este centro">
        </td>
        <td class="text-center align-middle">
            <button type="button" class="btn-accion-tabla cp-prorratear-quitar tooltipsC" title="Quitar fila">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
