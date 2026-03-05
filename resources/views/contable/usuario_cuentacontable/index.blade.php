@extends("theme.$theme.layout")
@section('titulo')
	Cuentas Contables por Usuario
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
                <h3 class="card-title">Cuentas Contables por Usuario</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Usuario</th>
                            <th>Cuentas Contables</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($datas as $usuario)
    						<tr data-entry-id="{{ $usuario->id }}">
        						<td>
            						{{ $usuario->id ?? '' }}
        						</td>
        						<td>
            						{{ $usuario->nombre ?? '' }}
        						</td>
        						<td>
            						<ul>
									@foreach($usuario->usuario_cuentacontables as $item)
										<li>{{ $item->cuentacontables->codigo }} ({{ $item->cuentacontables->nombre }} Empresa:{{ $item->cuentacontables->empresa_id }})</li>
            						@endforeach
            						</ul>
        						</td>
        						<td>
                       			@if (can('editar-usuario-cuentacontable', false))
                                	<a href="{{route('editar_usuario_cuentacontable', ['id' => $usuario->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-usuario-cuentacontable', false))
                                	<form action="{{route('eliminar_usuario_cuentacontable', ['id' => $usuario->id])}}" class="d-inline form-eliminar" method="POST">
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
