@extends('layouts.app')
@section('content')
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">Avios</div>
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                  
                    <div class="table-responsive">
                      <div class="col-md-6 float-right text-right mb-3 p-0">
                        @can('crear')
                          <a href="/avios/create" class="btn btn-success">Crear Avios</a>
                        @endcan
                      </div>
                      <table id="listado" class="table table-hover table-striped w-100">
                        <thead class="table-dark">
                          <tr>
                            <th scope="col">Artículo</th>
                            <th scope="col">Color</th>
                            <th scope="col">16/26</th>
                            <th scope="col">27/33</th>
                            <th scope="col">34/40</th>
                            <th scope="col">41/45</th>
                            <th scope="col">Tipo</th>
                            <th scope="col"></th>
                          </tr>
                        </thead>
                        <tbody> 
                        </tbody>
                      </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="//cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script type="text/javascript">
$(document).ready( function () {
  $.noConflict();
  $('#listado').DataTable({
    "ajax":{
      url:"/avios/list_json_response"
    },
    "order": [[ 0, "desc" ]], 
    "processing": true,
    "paginate":true,
    "deferRender": true,
    "columns": [
        {data:"material_id"},
        {data:"color_id"},
        {data:"consumo1"},
        {data:"consumo2"},
        {data:"consumo3"},
        {data:"consumo4"},
        {data:"tipo"},
        {data:"botones"},
    ],
    "columnDefs": [
      {
        targets:7,
        data:"botones",
        render:function(data, type, row, meta){
          var html = '<div class="d-flex float-right">' + 
          '@can("editar")<a class="btn btn-info ml-2" href="/avios/edit/'+ row.material_id + '">Ver</a>@endcan'+
          '@can("eliminar")<a class="btn btn-danger ml-2" href="/avios/delete/'+ row.material_id + ' " onclick="confirmar()">Borrar</a>@endcan'+
          '</div>';
          return html;
        }
      }
    ],
    "language": {
      "lengthMenu": "Mostrar _MENU_ registros",
      "zeroRecords": "No se encontraron resultados",
      "info": "Mostrando pagina _PAGE_ de _PAGES_",
      "infoEmpty": "Sin informacion disponible",
      "search": "Buscar:",
      "infoFiltered": "(filtrado de un total de _MAX_ registros)",
      "paginate": {
        "first":    "Primero",
        "last":     "Ultimo",
        "next":     "&nbsp;&nbsp; Siguiente",
        "previous": "Anterior &nbsp;&nbsp;"
      }
    }
  });
  
});

function confirmar(){
  if(! confirm('¿Desea eliminar selección?')) { 
    event.preventDefault();
    return false; 
  }
}
</script>
@endsection
