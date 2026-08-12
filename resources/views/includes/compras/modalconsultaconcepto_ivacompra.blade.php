{{-- Modal consulta conceptos IVA compra (filtrados por tipo de comprobante) --}}
<div class="modal fade" id="consultaconcepto_ivacompraModal" role="dialog" aria-labelledby="consultaconcepto_ivacompraModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultaconcepto_ivacompraModalLabel">Consulta conceptos IVA compra</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row mb-2">
          <label for="consultaconcepto_ivacompra" class="col-form-label col-sm-2">Buscar:</label>
          <div class="col-sm-10">
            <input type="text" name="consultaconcepto_ivacompra" id="consultaconcepto_ivacompra" class="form-control" autofocus placeholder="Código o nombre…">
            <small id="consultaconcepto_ivacompra-aviso" class="form-text text-muted"></small>
          </div>
        </div>
        <div class="table-responsive" style="max-height:360px;overflow:auto;">
          <table class="table table-sm table-bordered table-hover mb-0">
            <thead style="background-color:#85C1E9;color:#17202A;">
              <tr>
                <th style="width:70px;">ID</th>
                <th style="width:90px;">Código</th>
                <th>Nombre</th>
                <th style="width:70px;">Tipo</th>
                <th style="width:90px;"></th>
              </tr>
            </thead>
            <tbody id="datosconcepto_ivacompra"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultaconcepto_ivacompraModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultaconcepto_ivacompraModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
