@once('anita-modal-consulta-npu-baja')
<div class="modal fade" id="consultanpubajaModal" role="dialog" aria-labelledby="consultanpubajaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultanpubajaModalLabel">N&uacute;meros de parte &uacute;nica (activos)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultanpubaja" class="col-form-label">Buscar:</label>
          <input type="text" name="consultanpubaja" id="consultanpubaja" autocomplete="off" placeholder="NPU, SKU o descripci&oacute;n">
        </div>

        <table class="table table-striped table-bordered table-hover" id="tabla-data-npu-baja">
          <thead>
              <th>NPU</th>
              <th>SKU</th>
              <th>Descripci&oacute;n</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datosnpubaja"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultanpubajaModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultanpubajaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
@endonce
