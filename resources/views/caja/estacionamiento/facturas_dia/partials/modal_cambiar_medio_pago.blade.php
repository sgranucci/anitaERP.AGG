<style>
    #modal-fd-cambiar-medio-pago #fd-cmp-panel-cobranza {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        background: #f8f9fa;
        padding: 0.5rem;
    }
    #modal-fd-cambiar-medio-pago #fd-cmp-cuenta-table th,
    #modal-fd-cambiar-medio-pago #fd-cmp-cuenta-table td {
        vertical-align: middle;
    }
    #modal-fd-cambiar-medio-pago .fd-cmp-cc-cuenta-wrap {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: nowrap;
        min-width: 0;
    }
    #modal-fd-cambiar-medio-pago .fd-cmp-cc-codigo {
        width: 72px;
        flex: 0 0 72px;
    }
    #modal-fd-cambiar-medio-pago .fd-cmp-cc-nombre {
        flex: 1 1 auto;
        min-width: 0;
    }
    #modal-fd-cambiar-medio-pago .fd-cmp-cc-moneda {
        width: 56px;
        text-align: center;
        font-weight: 600;
        color: #495057;
    }
    #modal-fd-cambiar-medio-pago .fd-cmp-cc-monto {
        width: 110px;
    }
    #modal-fd-cambiar-medio-pago #fd-cmp-medios-rapidos {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: flex-start;
    }
    #modal-fd-cambiar-medio-pago #fd-cmp-medios-rapidos .fd-cmp-medio-rapido {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 72px;
        max-width: 110px;
        padding: 0.35rem 0.4rem 0.25rem;
        font-size: 0.68rem;
        line-height: 1.15;
        text-align: center;
        white-space: normal;
        word-break: break-word;
    }
    #modal-fd-cambiar-medio-pago #fd-cmp-medios-rapidos .fd-cmp-medio-rapido i,
    #modal-fd-cambiar-medio-pago #fd-cmp-medios-rapidos .fd-cmp-medio-rapido .est-icon-mercadopago {
        font-size: 1.15rem;
        margin-bottom: 0.15rem;
    }
    #modal-fd-cambiar-medio-pago .est-icon-mercadopago {
        display: inline-block;
        width: 1.15rem;
        height: 1.15rem;
        background: url('{{ asset('assets/pages/img/ventas/gastronomia/mercadopago.svg') }}') center/contain no-repeat;
    }
    #modal-fd-cambiar-medio-pago .fd-cmp-totales-resumen {
        font-size: 0.95rem;
    }
    #modal-fd-cambiar-medio-pago .fd-cmp-totales-resumen .fd-cmp-total-diff {
        color: #dc3545;
        font-weight: normal;
    }
    #modal-fd-cambiar-medio-pago .fd-cmp-cobranza-label {
        font-size: 0.8rem;
        color: #6c757d;
    }
</style>

<div class="modal fade" id="modal-fd-cambiar-medio-pago" tabindex="-1" role="dialog" aria-labelledby="modalFdCambiarMedioPagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalFdCambiarMedioPagoLabel">Cambiar medio de pago</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-2">
                <p class="small text-muted mb-2 mb-md-1">
                    Busque la cuenta por código o use los accesos rápidos.
                    Los montos son de solo lectura; al guardar no se modifica el total de la factura.
                </p>
                <div id="fd-cmp-error" class="alert alert-danger d-none py-2 small" role="alert"></div>
                <div id="fd-cmp-loading" class="text-center text-muted py-4 d-none">
                    <i class="fa fa-spinner fa-spin"></i> Cargando cobranza…
                </div>
                <div id="fd-cmp-form-wrap" class="d-none">
                    <input type="hidden" id="fd-cmp-empresa-id" value="">
                    <input type="hidden" id="empresa_id" value="">
                    <p class="mb-2">
                        <strong>Comprobante:</strong> <span id="fd-cmp-venta-codigo">—</span>
                        &nbsp;·&nbsp;
                        <strong>Total factura:</strong> <span id="fd-cmp-venta-total" class="text-monospace">—</span>
                    </p>
                    <div id="fd-cmp-panel-cobranza" class="small">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-1" style="gap: 0.35rem;">
                            <strong>Cobranza</strong>
                            <span class="text-muted" style="font-size:11px;"><kbd>Enter</kbd> en código valida la cuenta</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 bg-white" id="fd-cmp-cuenta-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 42%;">Cuenta de caja</th>
                                        <th style="width: 8%;">Mon.</th>
                                        <th style="width: 18%;">Monto</th>
                                        <th style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="fd-cmp-tbody-cuenta-table"></tbody>
                            </table>
                        </div>
                        <div class="mt-2 d-flex flex-wrap align-items-start" style="gap: 0.35rem;">
                            <div id="fd-cmp-medios-rapidos" role="group" aria-label="Medios de pago rápidos"></div>
                            <div id="fd-cmp-totales-cobranza" class="fd-cmp-totales-resumen ml-auto text-right"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="fd-cmp-guardar" disabled>
                    <i class="fa fa-save"></i> Guardar cambios
                </button>
            </div>
        </div>
    </div>
</div>

<template id="fd-cmp-template-renglon-cuenta">
    <tr class="item-cuenta-fd-cmp" data-linea-id="" data-monto-original="">
        <td>
            <div class="fd-cmp-cobranza-label mb-1 fd-cmp-row-cobranza-label d-none"></div>
            <div class="fd-cmp-cc-cuenta-wrap">
                <input type="hidden" class="cuentacaja_id" value="">
                <button type="button" title="Consulta cuentas de caja" class="btn-accion-tabla consultacuentacaja fd-cmp-consulta tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm fd-cmp-cc-codigo codigo" value="" placeholder="Cód." autocomplete="off">
                <input type="text" class="form-control form-control-sm fd-cmp-cc-nombre nombre" value="" placeholder="Descripción cuenta" readonly>
            </div>
        </td>
        <td class="fd-cmp-cc-moneda moneda-label">—</td>
        <td>
            <input type="hidden" class="moneda_id" value="">
            <input type="number" step="0.01" class="form-control form-control-sm fd-cmp-cc-monto monto" value="" readonly tabindex="-1">
        </td>
        <td class="text-center text-muted">
            <i class="fa fa-lock" title="Monto fijo"></i>
        </td>
    </tr>
</template>
