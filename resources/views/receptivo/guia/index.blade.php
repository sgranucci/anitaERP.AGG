@extends("theme.$theme.layout")
@section('titulo')
	Guías
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
 $.extend(true, $.fn.dataTable.defaults, {
    cuota: [[ 1, 'desc' ]],
    pageLength: 100,
  });
  $('.datatable-Order:not(.ajaxTable)').DataTable({ buttons: dtButtons })
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e){
        $($.fn.dataTable.tables(true)).DataTable()
            .columns.adjust();
    });
</script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Guías</h3>
                <div class="card-tools">
                    <a href="{{route('crear_guia')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-guia', false))
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
							<th>Código anita</th>
							<th>Tipo de Guía</th>
							<th>Domicilio</th>
							<th>Teléfono</th>
                            <th>Idiomas</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($guias as $guia)
    						<tr data-entry-id="{{ $guia->id }}">
        						<td>
            						{{ $guia->id ?? '' }}
        						</td>
        						<td>
            						{{ $guia->nombre ?? '' }}
        						</td>
								<td>
									{{ $guia->codigo }}
								</td>
								<td>
									@foreach($tipoguia_enum as $tipoguia)
										@if ($tipoguia['valor'] == $guia->tipoguia)
											{{ $tipoguia['nombre'] }}
										@endif
									@endforeach
								</td>
								<td>{{ $guia->domicilio }}</td>
								<td>{{ $guia->telefono }}</td>
        						<td>
            						<ul>
									@foreach($guia->guia_idiomas as $idioma)
										<li>{{ $idioma->idiomas->nombre }} </li>
            						@endforeach
            						</ul>
        						</td>
        						<td>
                       			@if (can('editar-guia', false))
                                	<a href="{{route('editar_guia', ['id' => $guia->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-guia', false))
                                	<form action="{{route('eliminar_guia', ['id' => $guia->id])}}" class="d-inline form-eliminar" method="POST">
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
