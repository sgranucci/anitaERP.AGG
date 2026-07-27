@once('anita-modal-consulta-asiento-oc')
<div class="modal fade" id="consultaAsientoOcModal" role="dialog" aria-labelledby="consultaAsientoOcModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header" style="background-color:#85C1E9;color:#17202A;">
        <h5 class="modal-title" id="consultaAsientoOcModalLabel">Ordenes de compra</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consulta-asiento-oc" class="col-form-label">Buscar:</label>
          <input type="text" id="consulta-asiento-oc" class="form-control col-lg-6 ml-2" autocomplete="off" placeholder="Numero OC, proveedor…">
        </div>
        <table class="table table-striped table-bordered table-hover" id="tabla-data-asiento-oc">
          <thead style="background-color:#85C1E9;color:#17202A;">
            <tr>
              <th>ID</th>
              <th>Nro. OC</th>
              <th>Proveedor</th>
              <th>Fecha</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="datos-asiento-oc"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
@endonce
