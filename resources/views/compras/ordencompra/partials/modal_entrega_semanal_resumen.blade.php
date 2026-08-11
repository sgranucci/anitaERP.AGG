{{-- Resumen matriz: 1 fila por artículo × columnas = fechas de entrega semanal + totales --}}
<div class="modal fade" id="modalOcEntregaSemanalResumen" tabindex="-1" role="dialog" aria-labelledby="modalOcEntregaSemanalResumenLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalOcEntregaSemanalResumenLabel">
                    <i class="fa fa-calendar"></i> Entregas semanales de la orden
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="oc-entrega-semanal-resumen-subtitulo">
                    Una fila por artículo; cada columna es una fecha de entrega. Totales por artículo y por fecha.
                </p>
                <div class="table-responsive" style="max-height: 70vh;">
                    <table class="table table-sm table-bordered table-striped mb-0" id="oc-entrega-semanal-resumen-tabla">
                        <thead style="background:#85C1E9;color:#17202A;" id="oc-entrega-semanal-resumen-thead"></thead>
                        <tbody id="oc-entrega-semanal-resumen-tbody"></tbody>
                        <tfoot id="oc-entrega-semanal-resumen-tfoot"></tfoot>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0 d-none" id="oc-entrega-semanal-resumen-vacio">
                    No hay artículos con entregas semanales cargadas en esta orden.
                </p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
