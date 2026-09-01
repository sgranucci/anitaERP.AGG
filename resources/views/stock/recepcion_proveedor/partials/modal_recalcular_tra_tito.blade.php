{{-- Modal: recalcular TRA TITO del mes en curso al cambiar cotización de COM --}}
<div class="modal fade" id="modalRecalcularTraTito" tabindex="-1" role="dialog" aria-labelledby="modalRecalcularTraTitoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalRecalcularTraTitoLabel">
                    <i class="fa fa-sync text-warning"></i> Recalcular TRA TITO
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-3">
                <p class="small mb-2" id="rtt-info"></p>
                <p class="small text-muted mb-2">
                    Se listan las TRA confirmadas del <strong>mes en curso</strong> de la <strong>misma empresa</strong>
                    con los artículos TITO de esta COM. El precio nuevo es el promedio de las 3 últimas compras
                    (ya con la cotización actualizada). Al aplicar se actualizan línea, stock, asiento y ctamov Anita.
                </p>
                <div id="rtt-loading" class="small text-muted d-none">
                    <i class="fa fa-spinner fa-spin"></i> Consultando…
                </div>
                <div id="rtt-error" class="small text-danger d-none"></div>
                <div id="rtt-vacio" class="small text-muted d-none">No hay TRA TITO confirmadas de esta empresa en el mes en curso.</div>
                <div id="rtt-sin-cambio" class="alert alert-info py-2 small d-none mb-2">
                    Las TRA listadas ya coinciden con el promedio actual. Marcá filas manualmente si querés forzar el recálculo.
                </div>
                <div class="table-responsive d-none" id="rtt-tabla-wrap" style="max-height: 22rem;">
                    <div class="mb-1">
                        <button type="button" id="rtt-check-cambios" class="btn btn-outline-secondary btn-xs btn-sm py-0">Solo con diferencias</button>
                        <button type="button" id="rtt-check-todas" class="btn btn-outline-secondary btn-xs btn-sm py-0">Seleccionar todas</button>
                        <span class="small text-muted ml-2" id="rtt-seleccion-hint"></span>
                    </div>
                    <table class="table table-sm table-bordered table-hover mb-1">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:2rem"></th>
                                <th>Fecha</th>
                                <th>TRA</th>
                                <th>Artículo</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">Precio</th>
                                <th class="text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody id="rtt-tbody"></tbody>
                    </table>
                    <p class="small mb-0" id="rtt-resumen"></p>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                <button type="button" id="rtt-btn-aplicar" class="btn btn-warning btn-sm" disabled>
                    <i class="fa fa-check"></i> Aplicar cambios
                </button>
            </div>
        </div>
    </div>
</div>
