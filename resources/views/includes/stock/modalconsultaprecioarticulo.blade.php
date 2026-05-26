@php
    $listasPrecioConsulta = $listasPrecioConsulta
        ?? \App\Models\Stock\Listaprecio::orderBy('codigo')->get(['id', 'codigo', 'nombre']);
@endphp
<div class="modal fade" id="consultaprecioarticuloModal" tabindex="-1" role="dialog" aria-labelledby="consultaprecioarticuloTitulo" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document" style="max-width: 96%;">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="consultaprecioarticuloTitulo">Precios del artículo</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body py-2">
        <p class="mb-2 small text-muted" id="consultaprecioarticuloSubtitulo"></p>
        <div class="form-inline mb-2">
          <label class="mr-2 mb-0 small" for="consultaprecioarticuloFechaRef">Vigentes al</label>
          <input type="date" id="consultaprecioarticuloFechaRef" class="form-control form-control-sm mr-2">
          <label class="mr-2 mb-0 small" for="consultaprecioarticuloListaId">Lista de precios</label>
          <select id="consultaprecioarticuloListaId" class="form-control form-control-sm mr-2">
            <option value="">Todas</option>
            @foreach ($listasPrecioConsulta as $lp)
              <option value="{{ $lp->id }}">
                {{ $lp->codigo ? '['.$lp->codigo.'] ' : '' }}{{ $lp->nombre }}
              </option>
            @endforeach
          </select>
          <button type="button" class="btn btn-primary btn-sm" id="consultaprecioarticuloRecargar">
            <i class="fa fa-refresh"></i> Actualizar
          </button>
        </div>
        <div id="consultaprecioarticuloError" class="alert alert-danger py-2 d-none" role="alert"></div>
        <div id="consultaprecioarticuloCargando" class="text-center text-muted py-4 d-none">
          <span class="fa fa-spinner fa-spin mr-2"></span>Cargando…
        </div>
        <div class="table-responsive" style="max-height: 65vh;">
          <table class="table table-sm table-striped table-bordered mb-0" id="consultaprecioarticuloTabla">
            <thead class="thead-light text-nowrap">
              <tr>
                <th>Lista</th>
                <th>Cód. lista</th>
                <th>Vigencia</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Precio ant.</th>
                <th>Moneda</th>
                <th>Últ. cambio por</th>
                <th data-orderable="false"></th>
              </tr>
            </thead>
            <tbody id="consultaprecioarticuloBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
