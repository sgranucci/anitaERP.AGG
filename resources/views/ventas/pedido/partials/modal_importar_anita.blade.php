{{-- Modal importación rápida Anita → ERP (solo El Bierzo). --}}
@php
    $hoy = \App\Support\Ventas\ListadoRepartoFechaEntregaSupport::fechaHoy();
    $fechaDefault = $filtros['fecha_entrega_desde'] ?? $hoy;
    $repartoDefault = $filtros['filtro_reparto'] ?? '';
@endphp
<div class="modal fade" id="modalImportarPedidoAnita" tabindex="-1" role="dialog"
     aria-labelledby="modalImportarPedidoAnitaLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post"
                  action="{{ route('pedido_importar_anita_index') }}"
                  id="form-importar-pedido-anita-index"
                  autocomplete="off">
                @csrf
                <div class="modal-header bg-info">
                    <h5 class="modal-title text-white" id="modalImportarPedidoAnitaLabel">
                        <i class="fa fa-download"></i> Importar pedidos desde Anita
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Trae pedidos de Anita (<code>pendmae</code>/<code>pendmov</code>) a anitaERP
                        por fecha de entrega y repartos. Crea los faltantes y actualiza los existentes.
                    </p>
                    <div class="form-group">
                        <label for="import_anita_fecha_entrega" class="requerido">Fecha de entrega</label>
                        <input type="date"
                               name="fecha_entrega"
                               id="import_anita_fecha_entrega"
                               class="form-control"
                               value="{{ $fechaDefault }}"
                               required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="import_anita_filtro_reparto">Repartos</label>
                        <input type="text"
                               name="filtro_reparto"
                               id="import_anita_filtro_reparto"
                               class="form-control"
                               value="{{ $repartoDefault }}"
                               placeholder="Ej: 101,95 &oacute; 10/20"
                               autocomplete="off"
                               title="Coma = lista; barra / = rango. Vac&iacute;o = todos.">
                        <small class="form-text text-muted">
                            Lista con coma (<strong>101,95</strong>); rango con barra (<strong>10/20</strong>).
                            Vac&iacute;o importa todos los repartos de esa fecha.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info btn-sm" id="btn-confirmar-importar-pedido-anita">
                        <i class="fa fa-download"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'overlay-importar-pedido-anita',
    'tituloId' => 'overlay-importar-pedido-anita-titulo',
    'subtituloId' => 'overlay-importar-pedido-anita-subtitulo',
    'titulo' => 'Importando pedidos desde Anita…',
    'subtitulo' => 'Puede demorar según la cantidad. No cierre la página.',
])
