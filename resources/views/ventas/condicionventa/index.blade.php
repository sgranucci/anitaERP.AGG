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
                <h3 class="card-title">Condiciones de venta</h3>
                <div class="card-tools">
                    <a href="{{route('crear_condicionventa')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-condiciones-de-venta', false))
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
                            <th>Cuotas</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($condicionesventa as $condicionventa)
    						<tr data-entry-id="{{ $condicionventa->id }}">
        						<td>
            						{{ $condicionventa->id ?? '' }}
        						</td>
        						<td>
            						{{ $condicionventa->nombre ?? '' }}
        						</td>
        						<td>
            						<ul>
									@foreach($condicionventa->condicionventacuotas as $item)
										@php
											$tipoplazo = $colTipoPlazo->where('valor', $item->tipoplazo)->first();
										@endphp
                						<li>{{ $item->cuota }} ({{ $tipoplazo['nombre'] }} Plazo:{{ $item->plazo }} {{ $item->fechavencimiento }} {{ $item->porcentaje.'%'}} {{ ($item->interes == 0 ? ' ' : $item->interes) }})</li>
            						@endforeach
            						</ul>
        						</td>
        						<td>
                       			@if (can('editar-condiciones-de-venta', false))
                                	<a href="{{route('editar_condicionventa', ['id' => $condicionventa->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-condiciones-de-venta', false))
                                	<form action="{{route('eliminar_condicionventa', ['id' => $condicionventa->id])}}" class="d-inline form-eliminar" method="POST">
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
