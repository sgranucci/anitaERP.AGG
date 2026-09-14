<div class="modal fade" id="consultachequecarteraModal" role="dialog" aria-labelledby="consultachequecarteraTitulo" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="consultachequecarteraTitulo">Cheques de terceros en cartera</h5>
          <div id="consultachequecarteraSubtitulo" class="text-muted small">Valores disponibles para entregar / endosar</div>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post" class="mb-2" onsubmit="return false;">
          <div class="form-group row mb-2">
            <label for="consultachequecartera" class="col-form-label col-auto pr-2">Buscar:</label>
            <div class="col">
              <input type="text" name="consultachequecartera" id="consultachequecartera" class="form-control" autocomplete="off" autofocus
                placeholder="Nro. interno Anita, nro. cheque, monto, cliente…">
            </div>
          </div>
        </form>
        <div class="table-responsive">
          <table class="table table-striped table-bordered table-hover table-sm mb-0" id="tabla-cheque-cartera-consulta">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>Int.</th>
                <th>Nro.</th>
                <th>F. pago</th>
                <th>Banco</th>
                <th>Cliente / librador</th>
                <th class="text-right">Monto</th>
                <th>Mon.</th>
                <th>Empresa</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="datoschequecartera"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultachequecarteraModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultachequecarteraModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
<style>
  #tabla-cheque-cartera-consulta tbody tr.cheque-cartera-activa { background-color: #d6eaf8; }
</style>
