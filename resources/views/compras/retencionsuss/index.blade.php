@extends("theme.$theme.layout")
@section('titulo')
	Retenciones SUSS
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
                <h3 class="card-title">Retenciones SUSS</h3>
                <div class="card-tools">
                    <a href="{{route('crear_retencionsuss')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-retenciones-de-suss', false))
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
							<th>Forma cálculo</th>
							<th>Valor retencion</th>
							<th>Mínimo imponible</th>
							<th>Régimen</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($retencionessuss as $retencionsuss)
    						<tr data-entry-id="{{ $retencionsuss->id }}">
        						<td>
            						{{ $retencionsuss->id ?? '' }}
        						</td>
        						<td>
            						{{ $retencionsuss->nombre ?? '' }}
        						</td>
								<td>
									{{ $retencionsuss->codigo }}
								</td>
								<td>
									@foreach($formacalculo_enum as $formacalculo)
										@if ($formacalculo['valor'] == $retencionsuss->formacalculo)
											{{ $formacalculo['nombre'] }}
										@endif
									@endforeach
								</td>
								<td>{{ number_format($retencionsuss->valorretencion,2) }}</td>
								<td>{{ number_format($retencionsuss->minimoretencion,2) }}</td>
								<td>{{ $retencionsuss->regimen }}</td>
        						<td>
                       			@if (can('editar-retenciones-de-suss', false))
                                	<a href="{{route('editar_retencionsuss', ['id' => $retencionsuss->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-retenciones-de-suss', false))
                                	<form action="{{route('eliminar_retencionsuss', ['id' => $retencionsuss->id])}}" class="d-inline form-eliminar" method="POST">
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
