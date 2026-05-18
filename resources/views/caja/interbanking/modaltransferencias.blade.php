<div class="modal fade" id="transferenciasModal" role="dialog" aria-labelledby="transferenciasModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="transferenciasModalLabel">Transferencias Interbanking</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
          <div class="form-row align-items-end mb-3">
            <div class="form-group col-md-2">
              <label for="ib_tr_date_since">Desde</label>
              <input type="date" class="form-control" id="ib_tr_date_since" name="ib_tr_date_since">
            </div>
            <div class="form-group col-md-2">
              <label for="ib_tr_date_until">Hasta</label>
              <input type="date" class="form-control" id="ib_tr_date_until" name="ib_tr_date_until">
            </div>
            <div class="form-group col-md-1">
              <label for="ib_tr_limit">Límite</label>
              <input type="number" min="1" max="500" class="form-control" id="ib_tr_limit" value="100">
            </div>
            <div class="form-group col-md-1">
              <label for="ib_tr_page">Página</label>
              <input type="number" min="0" class="form-control" id="ib_tr_page" value="0">
            </div>
            <div class="form-group col-md-2">
              <button type="button" id="ib_transferencias_consultar" class="btn btn-primary btn-block">Consultar</button>
            </div>
          </div>
          <p class="text-muted small mb-2" id="ib_transferencias_pie"></p>
          <div class="card">
            <div class="card-body p-0 table-responsive ib-tabla-transferencias-scroll">
              <table class="table table-hover mb-0" id="itemstransferencias-table" style="min-width: 1500px;">
                <thead>
                  <tr>
                    <th style="width: 8%;">Fecha transferencia</th>
                    <th style="width: 5%;">Hora</th>
                    <th style="width: 11%;">Tipo</th>
                    <th style="width: 7%; text-align: right;">Importe</th>
                    <th style="width: 4%;">Mon.</th>
                    <th style="width: 12%;">CBU débito</th>
                    <th style="width: 9%;">Banco crédito</th>
                    <th style="width: 11%;">Denominación crédito</th>
                    <th style="width: 8%;">CUIT crédito</th>
                    <th style="width: 12%;">CBU crédito</th>
                    <th style="width: 8%;">ID transf.</th>
                    <th style="width: 8%;">Acciones</th>
                  </tr>
                </thead>
                <tbody id="tbody-transferencias">
                </tbody>
              </table>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" id="aceptatransferenciasModal" class="btn btn-primary">Cerrar</button>
      </div>
    </div>
  </div>
</div>
