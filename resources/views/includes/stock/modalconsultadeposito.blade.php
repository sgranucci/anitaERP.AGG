@once('anita-modal-consulta-deposito')
<div class="modal fade" id="consultadepositoModal" role="dialog" aria-labelledby="consultadepositoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultadepositoModalLabel">Depósitos</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        {{-- Sin form anidado: si el partial va dentro de otro form, HTML cierra el padre y el submit falla. --}}
        <div class="form-group row">
          <label for="consultadeposito" class="col-form-label">Buscar:</label>
          <input type="text" name="consultadeposito" id="consultadeposito" autocomplete="off">
        </div>

        <table class="table table-striped table-bordered table-hover" id="tabla-data-deposito">
          <thead>
              <th>ID</th>
              <th>Código</th>
              <th>Descripción</th>
              <th>Tipo</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datosdeposito"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultadepositoModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultadepositoModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
@endonce
