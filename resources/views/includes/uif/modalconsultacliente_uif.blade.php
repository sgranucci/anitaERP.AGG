{{-- Modal consulta cliente UIF (compartido: unificar, futuros formularios) --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultacliente_uifModal" role="dialog" aria-labelledby="consultacliente_uifModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info">
        <h5 class="modal-title text-white" id="consultacliente_uifModalLabel">
          <i class="fa fa-users"></i> Consultar clientes UIF
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row mb-2">
          <label for="consultacliente_uif" class="col-form-label col-lg-1 text-right pr-2">Buscar</label>
          <div class="col-lg-7">
            <input type="text" name="consultacliente_uif" id="consultacliente_uif" class="form-control"
              placeholder="ID, DNI o nombre" autofocus autocomplete="off">
          </div>
          <div class="col-lg-4">
            <small class="text-muted">Enter elige la primera fila. F1 / lupa abren este modal.</small>
          </div>
        </div>

        <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
          <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-consulta-cliente-uif">
            <thead style="background:#85C1E9;color:#17202A; position: sticky; top: 0; z-index: 1;">
              <tr>
                <th>ID</th>
                <th>Origen</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>N&uacute;mero doc.</th>
                <th>Premios</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="datoscliente_uif"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultacliente_uifModal" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
