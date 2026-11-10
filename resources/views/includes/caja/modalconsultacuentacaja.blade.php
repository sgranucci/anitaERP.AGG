<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultacuentacajaModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document" style="max-width: 96%;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Cuentas de Caja</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        {{-- Sin form anidado: si el partial va dentro de otro form, HTML cierra el padre. --}}
        <div class="form-group row">
          <label for="consultacuentacaja" class="col-form-label">Buscar:</label>
          <input type="text" name="consultacuentacaja" id="consultacuentacaja" class="form-control" autofocus autocomplete="off">
          <input type="hidden" name="consultacuentacaja" id="consultacuentacaja_id">
          <input type="hidden" name="consultaempresacaja" id="consultaempresacaja_id">
        </div>

        <div class="table-responsive" style="max-height: 65vh;">
          <table class="table table-striped table-bordered table-hover mb-0" id="tabla-data">
            <thead>
                <th>ID</th>
                <th>Código</th>
                <th>Nombre</th>
                <th>Empresa</th>
                <th>Cuenta Contable</th>
                <th>Nombre Cuenta Contable</th>
                <th>Cod.Mon.</th>
                <th>Nombre Moneda</th>
                <th>CBU</th>
                <th style="min-width: 11rem;">Acciones</th>
            </thead>
            <tbody id="datoscuentacaja"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultacuentacajaModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultacuentacajaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
