<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="carga_tarea_novedad_Modal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Novedades</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			<div class="form-group row">
				<label for="carga_tarea_novedad" class="col-form-label">Tarea: </label>
				<input style="width: 30%; border: 0" type="text" name="novedad_nombre" id="novedad_nombre" readonly>
			</div>
        </form>
        
        <table class="table" id="table-tarea-novedad">
			<thead>
				<th>Desde fecha</th>
				<th>Hasta fecha</th>
				<th>Comentario</th>
				<th>Estado</th>
				<th>Usuario</th>
				<th></th>
			</thead>
			<tbody id="tbody-tarea-novedad-table"  class="container-novedad"> 
			</tbody>
        </table>
		<div class="row">
			<div class="col-md-12">
				<button id="agrega_renglon_tarea_novedad" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
			</div>
		</div>	
		@include('ticket.administracion_ticket.templatetarea_novedad')
      </div>
      <div class="modal-footer">
        <button type="button" id="cierracarga_tarea_novedadModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptacarga_tarea_novedadModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
