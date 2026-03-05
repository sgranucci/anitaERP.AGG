@extends("theme.$theme.layout")
@section('titulo')
Retenciones de Ganancias
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
                <h3 class="card-title">Retenciones de Ganancias</h3>
                <div class="card-tools">
                    <a href="{{route('crear_retencionganancia')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-retencion-de-ganancias', false))
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
							<th>Forma de cálculo</th>
							<th>Porc.Insc.</th>
							<th>Porc.No Insc.</th>
							<th>Excedente</th>
							<th>Régimen</th>
							<th>Mínima retención</th>
                            <th>Escala</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($retencionesganancia as $retencionganancia)
    						<tr data-entry-id="{{ $retencionganancia->id }}">
        						<td>
            						{{ $retencionganancia->id ?? '' }}
        						</td>
        						<td>
            						{{ $retencionganancia->nombre ?? '' }}
        						</td>
								<td>
									{{ $retencionganancia->codigo }}
								</td>
								<td>
									@foreach($formacalculo_enum as $formacalculo)
										@if ($formacalculo['valor'] == $retencionganancia->formacalculo)
											{{ $formacalculo['nombre'] }}
										@endif
									@endforeach
								</td>
								<td>{{ number_format($retencionganancia->porcentajeinscripto,2) }}</td>
								<td>{{ number_format($retencionganancia->porcentajenoinscripto,2) }}</td>
								<td>{{ number_format($retencionganancia->montoexcedente,2) }}</td>
								<td>{{ $retencionganancia->regimen }}</td>
								<td>{{ number_format($retencionganancia->minimoretencion,2) }}</td>
        						<td>
            						<ul>
									@foreach($retencionganancia->retencionganancia_escalas as $item)
										<li>{{ number_format($item->desdemonto,2) }} {{ number_format($item->hastamonto,2) }} Retiene: {{ number_format($item->montoretencion,2) }} Porc.:{{ number_format($item->porcentajeretencion,2).'%' }} Exced.:{{ number_format($item->excedente,2)}} </li>
            						@endforeach
            						</ul>
        						</td>
        						<td>
                       			@if (can('editar-retencion-de-ganancias', false))
                                	<a href="{{route('editar_retencionganancia', ['id' => $retencionganancia->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-retencion-de-ganancias', false))
                                	<form action="{{route('eliminar_retencionganancia', ['id' => $retencionganancia->id])}}" class="d-inline form-eliminar" method="POST">
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
