@extends("theme.$theme.layout")
@section('titulo')
	Retenciones de IIBB
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
                <h3 class="card-title">Retenciones de IIBB</h3>
                <div class="card-tools">
                    <a href="{{route('crear_retencionIIBB')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-retencion-de-IIBB', false))
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
							<th>Provincia</th>
							<th>Cuenta Contable</th>
                            <th>Condiciones IIBB</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($retencionesIIBB as $retencionIIBB)
    						<tr data-entry-id="{{ $retencionIIBB->id }}">
        						<td>
            						{{ $retencionIIBB->id ?? '' }}
        						</td>
        						<td>
            						{{ $retencionIIBB->nombre ?? '' }}
        						</td>
								<td>
									{{ $retencionIIBB->provincias->nombre }}
								</td>
								<td>
									@if (isset($retencionIIBB->cuentascontables))
										{{ $retencionIIBB->cuentascontables->nombre }}
									@endif
								</td>
        						<td>
            						<ul>
									@foreach($retencionIIBB->retencionIIBB_condiciones as $item)
										<li>{{ $item->condicionesIIBB->nombre }} Minimo Ret. {{ number_format($item->minimoretencion,2) }} Minimo imp. {{ number_format($item->minimoimponible,2) }} Porc.:{{ number_format($item->porcentajeretencion,2).'%' }}</li>
            						@endforeach
            						</ul>
        						</td>
        						<td>
                       			@if (can('editar-retencion-de-IIBB', false))
                                	<a href="{{route('editar_retencionIIBB', ['id' => $retencionIIBB->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-retencion-de-IIBB', false))
                                	<form action="{{route('eliminar_retencionIIBB', ['id' => $retencionIIBB->id])}}" class="d-inline form-eliminar" method="POST">
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
