<div class="modal fade" id="consultaformulaModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">F&oacute;rmulas</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consulta_formula" class="col-form-label">Buscar:</label>
          <input type="text" name="consulta_formula" id="consulta_formula" class="form-control" autocomplete="off" />
        </div>
        @php $modalCfMostrarCodigo = \App\Support\Stock\FormulaArticuloNumero::mostrarCodigo(); @endphp
        <table class="table table-striped table-bordered table-hover">
          <thead>
            <tr>
              <th>{{ \App\Support\Stock\FormulaArticuloNumero::etiquetaColumnaPrimaria() }}</th>
              @unless ($modalCfMostrarCodigo)
              <th>C&oacute;digo</th>
              @endunless
              <th>SKU cabecera</th>
              <th>Descripci&oacute;n</th>
              <th>Detalle</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="datos-formula-consulta"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
