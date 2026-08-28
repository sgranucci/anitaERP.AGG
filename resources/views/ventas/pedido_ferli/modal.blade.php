<div class="modal fade" id="medidasModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Medidas</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
          <div class="form-group">
            <label for="recipient-name" class="col-form-label">Cantidad m&oacute;dulos</label>
            <input type="text" size="5" style="font-weight: bold; text-align: center;" id="cantmodulo" name="cantmodulo" value="1"></input>
          </div>
          <div class="form-group">
            <label for="recipient-name" class="col-form-label">Medidas</label>
            <div id="medidasModal"></div>
          </div>
          <div class="form-group">
            <label for="recipient-name" class="col-form-label">Total pares</label>
            <input type="text" size="5" style="font-weight: bold; text-align: center;" id="totPares" name="totPares" readonly></input>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
