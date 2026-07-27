@once('anita-modal-consulta-concepto-solicitudpago')
<div class="modal fade" id="consultaconcepto_solicitudpagoModal" role="dialog"
     aria-labelledby="consultaconcepto_solicitudpagoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultaconcepto_solicitudpagoModalLabel">Conceptos de solicitud de pago</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultaconcepto_solicitudpago" class="col-form-label">Buscar:</label>
          <input type="text" name="consultaconcepto_solicitudpago" id="consultaconcepto_solicitudpago" autocomplete="off">
        </div>
        <p class="text-muted small mb-2" id="consulta-concepto-sp-filtro-sector"></p>
        <table class="table table-striped table-bordered table-hover" id="tabla-data-concepto-solicitudpago">
          <thead>
              <th>ID</th>
              <th>C&oacute;digo</th>
              <th>Nombre</th>
              <th>Sector</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datosconcepto_solicitudpago"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultaconcepto_solicitudpagoModal" class="btn btn-primary" data-dismiss="modal">Acepta</button>
      </div>
    </div>
  </div>
</div>
@endonce
