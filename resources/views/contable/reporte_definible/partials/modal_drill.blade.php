{{-- Drill de la celda: rubro → cuentas → asientos → comprobante de origen --}}
<div class="modal fade" id="rd-modal-drill" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title" id="rd-drill-titulo">Detalle de la celda</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="rd-drill-migas" class="mb-2 small text-muted"></div>
                <div id="rd-drill-cargando" class="text-center text-muted py-4" style="display:none;">
                    <i class="fa fa-spinner fa-spin"></i> Leyendo asientos&hellip;
                </div>
                <div id="rd-drill-error" class="alert alert-warning py-2" style="display:none;"></div>
                <div id="rd-drill-contenido" class="table-responsive"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@php
    // Resuelto antes: @json() de Blade no soporta 3 parámetros de ruta inline.
    $rdDrillUrlAsiento = \App\Support\Navegacion\ModoConsultaUrlSupport::route('editar_asiento', ['id' => '__ID__']);
@endphp
<script>
window.rdDrill = {
    url: @json($drill_url),
    urlAsiento: @json($rdDrillUrlAsiento),
    puedeVerAsiento: @json(can('editar-asiento', false) || can('listar-asiento', false))
};
</script>
<script src="{{ asset('assets/pages/scripts/contable/reporte_definible/drill.js') }}"></script>
