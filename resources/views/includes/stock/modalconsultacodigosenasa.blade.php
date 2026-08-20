@once('anita-modal-consulta-codigosenasa')
<div class="modal fade" id="consultacodigosenasaModal" role="dialog" aria-labelledby="consultacodigosenasaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultacodigosenasaModalLabel">C&oacute;digos SENASA</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        {{-- Sin form anidado: si el partial va dentro de otro form, HTML cierra el padre. --}}
        <div class="form-group row">
          <label for="consultacodigosenasa" class="col-form-label">Buscar:</label>
          <input type="text" name="consultacodigosenasa" id="consultacodigosenasa" autocomplete="off">
        </div>

        <table class="table table-striped table-bordered table-hover" id="tabla-data-codigosenasa">
          <thead>
              <th>ID</th>
              <th>C&oacute;digo</th>
              <th>Nombre</th>
              <th>Registro</th>
              <th>Prefijo</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datoscodigosenasa"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultacodigosenasaModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultacodigosenasaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
@endonce
