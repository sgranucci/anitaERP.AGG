{{-- Modal importación rápida Anita → ERP (solo El Bierzo). --}}
@php
    $hoy = \App\Support\Ventas\ListadoRepartoFechaEntregaSupport::fechaHoy();
    $fechaDefault = $filtros['fecha_entrega_desde'] ?? $hoy;
    $repartoDefault = $filtros['filtro_reparto'] ?? '';
@endphp
<div class="modal fade" id="modalImportarRemitoAnita" tabindex="-1" role="dialog"
     aria-labelledby="modalImportarRemitoAnitaLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post"
                  action="{{ route('remito_importar_anita_index') }}"
                  id="form-importar-remito-anita-index"
                  autocomplete="off">
                @csrf
                <div class="modal-header bg-info">
                    <h5 class="modal-title text-white" id="modalImportarRemitoAnitaLabel">
                        <i class="fa fa-download"></i> Importar remitos desde Anita
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Trae remitos de Anita (<code>pendmae</code>/<code>pendmov</code>) tipo
                        <strong>REM R 1</strong> a anitaERP por fecha y repartos.
                        Crea los faltantes y actualiza los existentes que aún no estén facturados.
                    </p>
                    <div class="form-group">
                        <label for="import_anita_remito_fecha" class="requerido">Fecha</label>
                        <input type="date"
                               name="fecha_entrega"
                               id="import_anita_remito_fecha"
                               class="form-control"
                               value="{{ $fechaDefault }}"
                               required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="import_anita_remito_filtro_reparto">Repartos</label>
                        <input type="text"
                               name="filtro_reparto"
                               id="import_anita_remito_filtro_reparto"
                               class="form-control"
                               value="{{ $repartoDefault }}"
                               placeholder="Ej: 101,95 ó 10/20"
                               autocomplete="off"
                               title="Coma = lista; barra / = rango. Vacío = todos.">
                        <small class="form-text text-muted">
                            Lista con coma (<strong>101,95</strong>); rango con barra (<strong>10/20</strong>).
                            Vacío importa todos los repartos de esa fecha.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info btn-sm" id="btn-confirmar-importar-remito-anita">
                        <i class="fa fa-download"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'overlay-importar-remito-anita',
    'tituloId' => 'overlay-importar-remito-anita-titulo',
    'subtituloId' => 'overlay-importar-remito-anita-subtitulo',
    'titulo' => 'Importando remitos desde Anita…',
    'subtitulo' => 'Puede demorar según la cantidad. No cierre la página.',
])
