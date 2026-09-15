@once('anita-modal-consulta-pickings-dia')
<div class="modal fade" id="consultapickingsdiaModal" role="dialog" aria-labelledby="consultapickingsdiaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultapickingsdiaModalLabel">Pickings pendientes de facturar</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row mb-2">
          <label for="consultapickingsdia_fecha" class="col-form-label col-auto pr-2">Fecha:</label>
          <div class="col-auto">
            <input type="date" id="consultapickingsdia_fecha" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
          </div>
          <label for="consultapickingsdia" class="col-form-label col-auto pr-2">Buscar N&deg;:</label>
          <div class="col">
            <input type="text" id="consultapickingsdia" class="form-control form-control-sm" autocomplete="off" placeholder="N&uacute;mero de picking">
          </div>
          <div class="col-auto">
            <button type="button" class="btn btn-primary btn-sm" id="btn-buscar-pickings-dia">
              <i class="fa fa-search"></i> Buscar
            </button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped table-bordered table-hover mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>N&deg; Picking</th>
                <th>Fecha</th>
                <th>Usuario</th>
                <th class="text-right">L&iacute;neas pend.</th>
                <th class="text-right">Clientes</th>
                <th>Clientes (muestra)</th>
                <th style="width:1%;">Acciones</th>
              </tr>
            </thead>
            <tbody id="datospickingsdia"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-nuevo-picking-modal">
          <i class="fa fa-plus"></i> Nuevo picking
        </button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
@endonce
