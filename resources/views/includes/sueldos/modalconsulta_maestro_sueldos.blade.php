@once('anita-modal-consulta-maestro-sueldos')
<div class="modal fade" id="consultamaestro_sueldosModal" role="dialog" aria-hidden="true"
     data-maestro="">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultamaestro_sueldosTitulo">Consulta</h5>
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label class="col-form-label">Buscar:</label>
          <input type="text" id="consultamaestro_sueldos" autocomplete="off" autofocus>
        </div>
        <table class="table table-striped table-bordered table-hover">
          <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                  <th>ID</th>
                  <th>Código</th>
                  <th>Nombre</th>
                  <th>Acciones</th>
              </tr>
          </thead>
          <tbody id="datosmaestro_sueldos"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
@endonce
