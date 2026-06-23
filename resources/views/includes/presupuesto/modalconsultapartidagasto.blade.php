<div class="modal fade" id="consultapartidagastoModal" role="dialog" aria-labelledby="consultapartidagastoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultapartidagastoLabel">Partidas de gasto (último presupuesto)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row align-items-center mb-2">
          <label for="consultapartidagasto_centrocosto_id" class="col-sm-3 col-form-label">Centro de costo:</label>
          <div class="col-sm-9">
            <select id="consultapartidagasto_centrocosto_id" class="form-control form-control-sm" autocomplete="off">
              <option value="">— Todos —</option>
              @if(!empty($centrocosto_query))
                @foreach($centrocosto_query as $cc)
                  <option value="{{ $cc->id }}">{{ $cc->codigo }}-{{ $cc->nombre }}</option>
                @endforeach
              @endif
            </select>
          </div>
        </div>
        <div class="form-group row">
          <label for="consultapartidagasto" class="col-form-label">Buscar:</label>
          <input type="text" name="consultapartidagasto" id="consultapartidagasto" autocomplete="off">
        </div>
        <table class="table table-striped table-bordered table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>Código</th>
              <th>Detalle</th>
              <th>Concepto</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="datospartidagasto"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultapartidagastoModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
