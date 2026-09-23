{{-- Modal importación rápida Anita → ERP (Interforming). --}}
@php
    $hoy = date('Y-m-d');
    $fechaDefault = $filtros['fecha_desde'] ?? $hoy;
    $tipoDefault = $filtros['tipo'] ?? 'TODOS';
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
                        Trae pedidos Anita (<code>pendmae</code>/<code>pendmov</code>) por fecha de pedido
                        (<code>penm_fecha</code> y tipo PED/PEX. No importa remitos.
                    </p>
                    <div class="form-group">
                        <label for="import_anita_fecha" class="requerido">Fecha de pedido</label>
                        <input type="date"
                               name="fecha"
                               id="import_anita_fecha"
                               class="form-control"
                               value="{{ $fechaDefault }}"
                               required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="import_anita_tipo">Tipo</label>
                        <select name="tipo" id="import_anita_tipo" class="form-control">
                            <option value="TODOS" {{ $tipoDefault === 'TODOS' ? 'selected' : '' }}>Todos (PED + PEX)</option>
                            <option value="PED" {{ $tipoDefault === 'PED' ? 'selected' : '' }}>PED</option>
                            <option value="PEX" {{ $tipoDefault === 'PEX' ? 'selected' : '' }}>PEX</option>
                        </select>
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
