@once('anita-modal-consulta-tipotransaccion-stock')
<div class="modal fade" id="consultatipotransaccionstockModal" role="dialog" aria-labelledby="consultatipotransaccionstockModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultatipotransaccionstockModalLabel">Tipos de transacci&oacute;n de stock</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultatipotransaccionstock" class="col-form-label">Buscar:</label>
          <input type="text" name="consultatipotransaccionstock" id="consultatipotransaccionstock" autocomplete="off">
        </div>

        <table class="table table-striped table-bordered table-hover" id="tabla-data-tipotransaccion-stock">
          <thead>
              <th>ID</th>
              <th>Abreviatura</th>
              <th>Nombre</th>
              <th>Operaci&oacute;n</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datostipotransaccionstock"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultatipotransaccionstockModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultatipotransaccionstockModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
@endonce
