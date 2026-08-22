@once('anita-modal-consulta-tipo-sancion')
<div class="modal fade" id="consultatipo_sancionModal" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tipos de sanción</h5>
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label class="col-form-label">Buscar:</label>
          <input type="text" id="consultatipo_sancion" autocomplete="off" autofocus>
        </div>
        <table class="table table-striped table-bordered table-hover">
          <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                  <th>ID</th>
                  <th>Código</th>
                  <th>Nombre</th>
                  <th>Clase</th>
                  <th>Acciones</th>
              </tr>
          </thead>
          <tbody id="datostipo_sancion"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
@endonce
