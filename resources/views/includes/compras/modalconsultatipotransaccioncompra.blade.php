@once('anita-modal-consulta-tipotransaccion-compra')
<div class="modal fade" id="consultatipotransaccioncompraModal" role="dialog" aria-labelledby="consultatipotransaccioncompraModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultatipotransaccioncompraModalLabel">Tipos de comprobante (compras)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultatipotransaccioncompra" class="col-form-label">Buscar:</label>
          <input type="text" name="consultatipotransaccioncompra" id="consultatipotransaccioncompra" autocomplete="off">
        </div>

        <table class="table table-striped table-bordered table-hover" id="tabla-data-tipotransaccion-compra">
          <thead style="background-color:#85C1E9;color:#17202A;">
              <th>ID</th>
              <th>Abreviatura</th>
              <th>Nombre</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datostipotransaccioncompra"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultatipotransaccioncompraModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultatipotransaccioncompraModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
@endonce
