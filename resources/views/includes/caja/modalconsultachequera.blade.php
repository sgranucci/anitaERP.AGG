<div class="modal fade" id="consultachequeraModal" role="dialog" aria-labelledby="consultachequeraTitulo" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="consultachequeraTitulo">Chequeras</h5>
          <div id="consultachequeraSubtitulo" class="text-muted small"></div>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post" class="mb-2">
          <div class="form-group row mb-2">
            <label for="consultachequera" class="col-form-label col-auto pr-2">Buscar:</label>
            <div class="col">
              <input type="text" name="consultachequera" id="consultachequera" class="form-control" autocomplete="off" autofocus
                placeholder="Código, tipo (al día / diferido) o nro. de cheque">
            </div>
            <div class="col-auto">
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="consultachequeraTerminadas">
                <label class="form-check-label" for="consultachequeraTerminadas">Incluir terminadas</label>
              </div>
            </div>
          </div>
        </form>
        <div class="table-responsive">
          <table class="table table-striped table-bordered table-hover table-sm mb-0" id="tabla-chequera-consulta">
            <thead>
              <tr>
                <th>Código</th>
                <th>Tipo</th>
                <th>Chequera</th>
                <th>Rango</th>
                <th>Último usado</th>
                <th>Disp.</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="datoschequera"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="sinconsultachequeraModal" class="btn btn-outline-secondary">Sin chequera</button>
        <button type="button" id="cierraconsultachequeraModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultachequeraModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
<style>
  #tabla-chequera-consulta tbody tr.chequera-consulta-activa { background-color: #d6eaf8; }
  #tabla-chequera-consulta tbody tr.chequera-consulta-sugerida td:first-child { font-weight: 600; }
</style>
