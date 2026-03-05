@extends("theme.$theme.layout")
@section('titulo')
M&oacute;dulos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
 $.extend(true, $.fn.dataTable.defaults, {
    talle: [[ 1, 'desc' ]],
    pageLength: 100,
  });
  $('.datatable-Order:not(.ajaxTable)').DataTable({ buttons: dtButtons })
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e){
        $($.fn.dataTable.tables(true)).DataTable()
            .columns.adjust();
    });
})

</script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">M&oacute;dulos</h3>
                <div class="card-tools">
                    <a href="{{route('crear_modulo')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-modulos', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>C&oacute;digo</th>
                            <th>Medidas</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($modulos as $modulo)
    						<tr data-entry-id="{{ $modulo->id }}">
        						<td>
            						{{ $modulo->id ?? '' }}
        						</td>
        						<td>
            						{{ $modulo->nombre ?? '' }}
        						</td>
        						<td>
            						{{ $modulo->codigo ?? '' }}
        						</td>
        						<td>
            						<ul>
									@foreach($modulo->talles as $item)
                						<li>{{ $item->nombre }} ({{ $item->pivot->cantidad }})</li>
            						@endforeach
            						</ul>
        						</td>
        						<td>
                       			@if (can('editar-modulos', false))
                                	<a href="{{route('editar_modulo', ['id' => $modulo->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-modulos', false))
                                	<form action="{{route('eliminar_modulo', ['id' => $modulo->id])}}" class="d-inline form-eliminar" method="POST">
                                   		@csrf @method("delete")
                                   		<button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                       	<i class="fa fa-times-circle text-danger"></i>
                                   	</button>
                                	</form>
								@endif
                            	</td>
                        	</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
