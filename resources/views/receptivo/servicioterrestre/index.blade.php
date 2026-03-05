@extends("theme.$theme.layout")
@section('titulo')
	Servicios Terrestres
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
                <h3 class="card-title">Servicios Terrestres</h3>
                <div class="card-tools">
                    <a href="{{route('crear_servicioterrestre')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-servicio-terrestre', false))
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
							<th>Tipo servicio</th>
							<th>Abreviatura</th>
							<th>Ubicación</th>
							<th>Precio</th>
							<th>Pre pago</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($serviciosterrestres as $servicioterrestre)
    						<tr data-entry-id="{{ $servicioterrestre->id }}">
        						<td>
            						{{ $servicioterrestre->id ?? '' }}
        						</td>
        						<td>
            						{{ $servicioterrestre->nombre ?? '' }}
        						</td>
								<td>
									{{ $servicioterrestre->codigo }}
								</td>
								<td>
									{{ $servicioterrestre->tiposervicioterrestres->nombre }}
								</td>
								<td>
            						{{ $servicioterrestre->abreviatura ?? '' }}
        						</td>
								<td>
									@foreach($ubicacion_enum as $ubicacion)
										@if ($ubicacion['valor'] == $servicioterrestre->ubicacion)
											{{ $ubicacion['nombre'] }}
										@endif
									@endforeach
								</td>
								<td>{{ number_format($servicioterrestre->precioindividual,2) }}</td>
								<td>
									@foreach($prepago_enum as $prepago)
										@if ($prepago['valor'] == $servicioterrestre->prepago)
											{{ $prepago['nombre'] }}
										@endif
									@endforeach
								</td>
        						<td>
                       			@if (can('editar-servicio-terrestre', false))
                                	<a href="{{route('editar_servicioterrestre', ['id' => $servicioterrestre->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-servicio-terrestre', false))
                                	<form action="{{route('eliminar_servicioterrestre', ['id' => $servicioterrestre->id])}}" class="d-inline form-eliminar" method="POST">
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
