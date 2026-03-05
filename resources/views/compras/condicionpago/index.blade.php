@extends("theme.$theme.layout")
@section('titulo')
Condiciones de Venta
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
                <h3 class="card-title">Condiciones de pago</h3>
                <div class="card-tools">
                    <a href="{{route('crear_condicionpago')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-condiciones-de-pago', false))
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
                            <th>Cuotas</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($condicionespago as $condicionpago)
    						<tr data-entry-id="{{ $condicionpago->id }}">
        						<td>
            						{{ $condicionpago->id ?? '' }}
        						</td>
        						<td>
            						{{ $condicionpago->nombre ?? '' }}
        						</td>
								<td>
									{{ $condicionpago->codigo }}
								</td>
        						<td>
            						<ul>
									@foreach($condicionpago->condicionpagocuotas as $item)
										@foreach($tipoplazo_enum as $tipoPlazo)
											@if ($tipoPlazo['valor'] == $item->tipoplazo)
												<li>{{ $item->cuota }} ({{ $tipoPlazo['nombre'] }} Plazo:{{ $item->plazo }} {{ $item->fechavencimiento }} {{ $item->porcentaje.'%'}} {{ ($item->interes == 0 ? ' ' : $item->interes) }})</li>
											@endif		
										@endforeach										
            						@endforeach
            						</ul>
        						</td>
        						<td>
                       			@if (can('editar-condiciones-de-pago', false))
                                	<a href="{{route('editar_condicionpago', ['id' => $condicionpago->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-condiciones-de-pago', false))
                                	<form action="{{route('eliminar_condicionpago', ['id' => $condicionpago->id])}}" class="d-inline form-eliminar" method="POST">
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
