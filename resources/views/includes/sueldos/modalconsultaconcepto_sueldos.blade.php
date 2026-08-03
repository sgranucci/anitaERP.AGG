@once('anita-modal-consulta-concepto-sueldos')
<div class="modal fade" id="consultaconcepto_sueldosModal" role="dialog"
     aria-labelledby="consultaconcepto_sueldosModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultaconcepto_sueldosModalLabel">Conceptos de liquidaci&oacute;n</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultaconcepto_sueldos" class="col-form-label">Buscar:</label>
          <input type="text" name="consultaconcepto_sueldos" id="consultaconcepto_sueldos" autocomplete="off" autofocus>
        </div>
        <p class="text-muted small mb-2">Conceptos activos. Busque por c&oacute;digo o descripci&oacute;n.</p>
        <table class="table table-striped table-bordered table-hover" id="tabla-data-concepto-sueldos">
          <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                  <th>ID</th>
                  <th>C&oacute;digo</th>
                  <th>Descripci&oacute;n</th>
                  <th>Tipo</th>
                  <th>Acciones</th>
              </tr>
          </thead>
          <tbody id="datosconcepto_sueldos"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultaconcepto_sueldosModal" class="btn btn-primary" data-dismiss="modal">Acepta</button>
      </div>
    </div>
  </div>
</div>
@endonce
