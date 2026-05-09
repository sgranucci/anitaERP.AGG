<div class="modal fade" id="consultalistasprecioModal" tabindex="-1" role="dialog" aria-labelledby="consultalistasprecioTitulo" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document" style="max-width: 96%;">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="consultalistasprecioTitulo">Listas de precios del artículo</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body py-2">
        <p class="mb-2 small text-muted" id="consultalistasprecioSubtitulo"></p>
        <div id="consultalistasprecioError" class="alert alert-danger py-2 d-none" role="alert"></div>
        <div id="consultalistasprecioCargando" class="text-center text-muted py-4 d-none">
          <span class="fa fa-spinner fa-spin mr-2"></span>Cargando…
        </div>
        <div class="table-responsive" style="max-height: 65vh;">
          <table class="table table-sm table-striped table-bordered mb-0" id="consultalistasprecioTabla">
            <thead class="thead-light text-nowrap">
              <tr>
                <th>Proveedor</th>
                <th>Lista</th>
                <th>F. lista</th>
                <th>Estado</th>
                <th>Moneda lista</th>
                <th>Precio vigente</th>
                <th>% Desc.</th>
                <th>Cód. art. prov.</th>
                <th>Vigencia ítem</th>
                <th title="Condiciones de la lista">Cond. pago</th>
                <th>Cond. entrega</th>
                <th>Cond. compra</th>
                <th>Obs. lista</th>
                <th>Alta lista</th>
                <th>Creador lista</th>
                <th>Últ. cambio ítem</th>
              </tr>
            </thead>
            <tbody id="consultalistasprecioBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
