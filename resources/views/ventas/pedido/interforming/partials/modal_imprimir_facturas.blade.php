{{-- Modal: elegir factura(s) a imprimir desde index pedidos IF --}}
{{-- Bases con APP_CARPETA (no depender de JS/route() sin carpeta → 404 en Apache). --}}
<div class="modal fade" id="modalImprimirFacturasPedido" tabindex="-1" role="dialog" aria-labelledby="modalImprimirFacturasPedidoLabel" aria-hidden="true"
     data-base-factura="{{ urlAppCarpeta('ventas/impresion-sesion/factura') }}"
     data-base-lote="{{ urlAppCarpeta('ventas/impresion-sesion/pedido-facturas') }}"
     data-retorno-index="{{ urlAppCarpeta('ventas/pedido') }}">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalImprimirFacturasPedidoLabel">Imprimir facturas del pedido</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-2 text-muted" id="modal-imprimir-facturas-pedido-codigo"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:2.5rem;" class="text-center">
                                    <input type="checkbox" id="modal-imprimir-facturas-check-todas" title="Seleccionar todas" checked>
                                </th>
                                <th>Comprobante</th>
                                <th>Fecha</th>
                                <th class="text-right">Total</th>
                                <th>CAE</th>
                            </tr>
                        </thead>
                        <tbody id="modal-imprimir-facturas-tbody">
                        </tbody>
                    </table>
                </div>
                <small class="form-text text-muted mt-2">Pod&eacute;s marcar una o varias. &laquo;Imprimir todas&raquo; env&iacute;a el lote completo.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-outline-primary" id="btn-imprimir-facturas-seleccionadas">
                    <i class="fa fa-print"></i> Imprimir seleccionadas
                </button>
                <button type="button" class="btn btn-primary" id="btn-imprimir-facturas-todas">
                    <i class="fa fa-print"></i> Imprimir todas
                </button>
            </div>
        </div>
    </div>
</div>
