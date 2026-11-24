<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultaclienteModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Clientes</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row align-items-center mb-2" style="gap: 8px;">
   				    <label for="consultacliente" class="col-form-label mb-0">Buscar:</label>
              {{-- Sin autofocus HTML: en página de factura robaba el foco del #codigocliente. El shown.bs.modal ya enfoca. --}}
              <input type="text" name="consultacliente" id="consultacliente" class="form-control form-control-sm" style="max-width: 18rem;" autocomplete="off">
              <label for="consulta_cliente_modalidad" class="col-form-label mb-0 ml-2">Modalidad:</label>
              <select name="consulta_cliente_modalidad" id="consulta_cliente_modalidad" class="form-control form-control-sm" style="max-width: 16rem;" title="Genérica busca en todos los campos. Nombre empieza con filtra solo por nombre. Se recuerda por usuario en este navegador.">
                <option value="generica">Genérica (contiene)</option>
                <option value="nombre_empieza">Nombre empieza con</option>
              </select>
              <input type="hidden" name="consultacliente_id" id="consultacliente_id">
			      </div>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>Código Anita</th>
              <th>Domicilio</th>
              <th>Provincia</th>
              <th>Localidad</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datoscliente"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultaclienteModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultaclienteModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
