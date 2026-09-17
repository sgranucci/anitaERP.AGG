{{-- Modal importación pedidos faltantes L8 → L12 (Calzados Ferli). --}}
@php
    $fechaDefaultL8 = date('Y-m-d', strtotime('-30 days'));
@endphp
<div class="modal fade" id="modalImportarPedidoL8" tabindex="-1" role="dialog"
     aria-labelledby="modalImportarPedidoL8Label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post"
                  action="{{ route('pedido_importar_l8_index') }}"
                  id="form-importar-pedido-l8-index"
                  autocomplete="off">
                @csrf
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="modalImportarPedidoL8Label">
                        <i class="fa fa-download"></i> Importar pedidos desde L8
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Trae a L12 los pedidos que existen en L8 y todavía no están acá
                        (cabecera, combinaciones, OT y tareas). Temporal hasta que producción use L12.
                    </p>
                    <div class="form-group">
                        <label for="import_l8_fecha_desde">Fecha pedido desde</label>
                        <input type="date"
                               name="fecha_desde"
                               id="import_l8_fecha_desde"
                               class="form-control"
                               value="{{ $fechaDefaultL8 }}">
                        <small class="form-text text-muted">Vacío = sin filtro de fecha (últimos candidatos).</small>
                    </div>
                    <div class="form-group mb-0">
                        <label for="import_l8_limite">Máximo a importar</label>
                        <input type="number"
                               name="limite"
                               id="import_l8_limite"
                               class="form-control"
                               value="100"
                               min="1"
                               max="300">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btn-confirmar-importar-pedido-l8">
                        <i class="fa fa-download"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'overlay-importar-pedido-l8',
    'tituloId' => 'overlay-importar-pedido-l8-titulo',
    'subtituloId' => 'overlay-importar-pedido-l8-subtitulo',
    'titulo' => 'Importando pedidos desde L8…',
    'subtitulo' => 'Puede demorar según la cantidad. No cierre la página.',
])
