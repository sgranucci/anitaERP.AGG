<div class="modal fade" id="movimientodiarioModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Movimientos de Cuentas Interbanking</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
          <div class="form-row align-items-end mb-3">
            <div class="form-group col-md-2">
              <label for="ib_movimiento_tipo">Tipo</label>
              <select class="form-control" id="ib_movimiento_tipo" name="ib_movimiento_tipo">
                <option value="dia">Día</option>
                <option value="diferidos">Diferidos</option>
                <option value="anteriores">Anteriores</option>
                <option value="zughus">ZUGHUS</option>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label for="ib_date_since">Desde</label>
              <input type="date" class="form-control" id="ib_date_since" name="ib_date_since">
            </div>
            <div class="form-group col-md-2">
              <label for="ib_date_until">Hasta</label>
              <input type="date" class="form-control" id="ib_date_until" name="ib_date_until">
            </div>
            <div class="form-group col-md-1">
              <label for="ib_limit">Límite</label>
              <input type="number" min="1" max="500" class="form-control" id="ib_limit" value="100">
            </div>
            <div class="form-group col-md-1">
              <label for="ib_page">Página</label>
              <input type="number" min="0" class="form-control" id="ib_page" value="0">
            </div>
            <div class="form-group col-md-2">
              <button type="button" id="ib_movimientos_consultar" class="btn btn-primary btn-block">Consultar</button>
            </div>
          </div>
          <p class="text-muted small mb-2" id="ib_movimientos_pie"></p>
          <div class="card">
            <div class="card-body p-0">
              <table class="table table-hover mb-0" id="itemsmovimientodiario-table">
                <thead>
                  <tr>
                    <th style="width: 11%;">Fecha</th>
                    <th style="width: 8%; text-align: right;">Débito</th>
                    <th style="width: 8%; text-align: right;">Crédito</th>
                    <th style="width: 18%;">Descripción</th>
                    <th style="width: 8%;">Cód. IB</th>
                    <th style="width: 7%;">Comp.</th>
                    <th style="width: 14%;">CBU</th>
                    <th style="width: 16%;">Contraparte</th>
                  </tr>
                </thead>
                <tbody id="tbody-movimientodiario">
                </tbody>
              </table>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" id="aceptamovimientodiarioModal" class="btn btn-primary">Cerrar</button>
      </div>
    </div>
  </div>
</div>
