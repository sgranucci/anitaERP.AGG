@php
    // El filtro de lista usa modal + código (sin cargar todas las listas en el select).
@endphp
<div class="modal fade" id="consultaprecioarticuloModal" tabindex="-1" role="dialog" aria-labelledby="consultaprecioarticuloTitulo" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document" style="max-width: 960px;">
    <div class="modal-content consultaprecioarticulo-content">
      <div class="modal-header py-2">
        <h5 class="modal-title text-truncate pr-2" id="consultaprecioarticuloTitulo">Precios del artículo</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body py-2 d-flex flex-column" style="max-height: 70vh;">
        <p class="mb-2 small text-muted" id="consultaprecioarticuloSubtitulo"></p>
        <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 0.35rem 0.5rem;">
          <label class="mb-0 small" for="consultaprecioarticuloFechaRef">Vigentes al</label>
          <input type="date" id="consultaprecioarticuloFechaRef" class="form-control form-control-sm" style="width: 9.5rem;">
          <label class="mb-0 small" for="codigolistaprecio">Lista</label>
          <div class="tm-listaprecio-campo d-inline-flex align-items-center" style="gap: 3px; max-width: 22rem;"
               title="Código de lista (Enter / F1). Vacío = todas">
            <input type="hidden" id="consultaprecioarticuloListaId" class="listaprecio_id" value="">
            <button type="button" class="btn-accion-tabla consultalistaprecio tooltipsC" title="Consultar listas (F1)">
              <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="form-control form-control-sm codigolistaprecio" id="codigolistaprecio"
                   value="" placeholder="C&oacute;d." autocomplete="off" style="width: 4.25rem;"
                   title="C&oacute;digo de lista (Enter / F1). Vac&iacute;o = todas">
            <input type="text" class="form-control form-control-sm nombrelistaprecio" id="nombrelistaprecio"
                   value="" placeholder="Todas" readonly tabindex="-1"
                   style="width: 10rem; min-width: 6rem;">
          </div>
          <button type="button" class="btn btn-primary btn-sm" id="consultaprecioarticuloRecargar">
            <i class="fa fa-refresh"></i> Actualizar
          </button>
        </div>
        <div id="consultaprecioarticuloError" class="alert alert-danger py-2 d-none" role="alert"></div>
        <div id="consultaprecioarticuloCargando" class="text-center text-muted py-3 d-none">
          <span class="fa fa-spinner fa-spin mr-2"></span>Cargando…
        </div>
        <div class="table-responsive consultaprecioarticulo-tabla-wrap flex-grow-1 border rounded">
          <table class="table table-sm table-striped table-bordered mb-0" id="consultaprecioarticuloTabla">
            <thead class="text-nowrap" style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>C&oacute;d. lista</th>
                <th>Lista</th>
                <th>Vigencia</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Precio ant.</th>
                <th>Moneda</th>
                <th>&Uacute;lt. cambio</th>
                <th data-orderable="false" style="width:2.5rem;"></th>
              </tr>
            </thead>
            <tbody id="consultaprecioarticuloBody"></tbody>
          </table>
        </div>
        <div id="consultaprecioarticuloPaginador" class="d-flex flex-wrap align-items-center justify-content-between mt-2 pt-1 border-top small" style="gap: 0.5rem;">
          <span id="consultaprecioarticuloPaginadorInfo" class="text-muted"></span>
          <div class="btn-group btn-group-sm" role="group" aria-label="Paginación precios">
            <button type="button" class="btn btn-outline-secondary" id="consultaprecioarticuloPagPrev" disabled>
              <i class="fa fa-chevron-left"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary disabled" id="consultaprecioarticuloPagLabel" tabindex="-1">1 / 1</button>
            <button type="button" class="btn btn-outline-secondary" id="consultaprecioarticuloPagNext" disabled>
              <i class="fa fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="modal-footer py-2">
        @if (can('crear-precios', false))
          <a href="{{ route('crear_precio') }}" id="consultaprecioarticuloNuevo" class="btn btn-success btn-sm mr-auto d-none">
            <i class="fa fa-plus"></i> Nuevo precio
          </a>
        @endif
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<style>
  #consultaprecioarticuloModal .consultaprecioarticulo-tabla-wrap {
    max-height: 280px;
    overflow: auto;
  }
  #consultaprecioarticuloModal .consultaprecioarticulo-tabla-wrap thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #85C1E9;
    color: #17202A;
  }
  @media (min-height: 800px) {
    #consultaprecioarticuloModal .consultaprecioarticulo-tabla-wrap {
      max-height: 340px;
    }
  }
</style>
