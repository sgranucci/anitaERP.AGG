@extends("theme.$theme.layout")
@section('titulo')
	Comisiones de Servicios Terrestres
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
                <h3 class="card-title">Comisiones de Servicios Terrestres</h3>
                <div class="card-tools">
                    <a href="{{route('crear_comision_servicioterrestre')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-comision-servicio-terrestre', false))
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
                            <th>Servicio</th>
							<th>Forma de pago</th>
							<th>Tipo comisión</th>
							<th>Comisión</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($comision_serviciosterrestres as $comision_servicioterrestre)
    						<tr data-entry-id="{{ $comision_servicioterrestre->id }}">
        						<td>
            						{{ $comision_servicioterrestre->id ?? '' }}
        						</td>
								<td>
									{{ $comision_servicioterrestre->servicioterrestres->nombre }}
								</td>
								<td>
            						{{ $comision_servicioterrestre->formapagos->nombre ?? '' }}
        						</td>
								<td>
									@foreach($tipocomision_enum as $tipocomision)
										@if ($tipocomision['valor'] == $comision_servicioterrestre->tipocomision)
											{{ $tipocomision['nombre'] }}
										@endif
									@endforeach
								</td>
								<td>{{ number_format($comision_servicioterrestre->porcentajecomision,2) }}</td>
        						<td>
                       			@if (can('editar-comision-servicio-terrestre', false))
                                	<a href="{{route('editar_comision_servicioterrestre', ['id' => $comision_servicioterrestre->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-comision-servicio-terrestre', false))
                                	<form action="{{route('eliminar_comision_servicioterrestre', ['id' => $comision_servicioterrestre->id])}}" class="d-inline form-eliminar" method="POST">
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
