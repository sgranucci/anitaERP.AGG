@once('anita-modal-consulta-camion')
<div class="modal fade" id="consultacamionModal" role="dialog" aria-labelledby="consultacamionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultacamionModalLabel">Camiones</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        {{-- Sin form anidado: si el partial va dentro de otro form, HTML cierra el padre. --}}
        <div class="form-group row">
          <label for="consultacamion" class="col-form-label">Buscar:</label>
          <input type="text" name="consultacamion" id="consultacamion" autocomplete="off">
        </div>

        <table class="table table-striped table-bordered table-hover" id="tabla-data-camion">
          <thead>
              <th>ID</th>
              <th>C&oacute;digo</th>
              <th>Dominio</th>
              <th>Habilitaci&oacute;n</th>
              <th>Tipo</th>
              <th>Precintos</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datoscamion"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultacamionModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultacamionModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
@endonce
