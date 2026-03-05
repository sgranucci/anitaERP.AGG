@extends("theme.$theme.layout")
@section('titulo')
Movimientos de Stock
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Movimientos de Stock</h3>
                <div class="card-tools">
					<a href="{{route('crear_movimientostock')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-movimientos-de-stock', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data-2">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
							<th>Tipo de transacción</th>
                            <th>Número</th>
							<th>Marca</th>
							<th>Lote</th>
                            <th>Pares</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($datas as $movimientostock)
    						<tr data-entry-id="{{ $movimientostock['id'] }}">
        						<td>
            						{{ $movimientostock['id'] ?? '' }}
        						</td>
        						<td>
            						{{date("d/m/Y", strtotime($movimientostock['fecha'] ?? ''))}} 
        						</td>
        						<td>
            						<b>{{ $movimientostock['tipostransaccion']['nombre'] ?? '' }}</b>
        						</td>
        						<td>
                					<small> {{$movimientostock['codigo']}}</small>
        						</td>
								<td>
                					<small> {{ $movimientostock['mventas']['nombre'] ?? ''}}</small>
        						</td>
								<td>
                					<small> {{$movimientostock['articulos_movimiento'][0]['lote']}}</small>
        						</td>
        						<td>
									@php $totalPares = 0; @endphp
									@foreach ($movimientostock['articulos_movimiento'] as $item)
										@php $totalPares += $item->cantidad; @endphp
									@endforeach
									{{ $totalPares }}
								</td>
        						<td>
                       			@if (can('editar-movimientos-de-stock', false))
                                	<a href="{{route('editar_movimientostock', ['id' => $movimientostock['id']])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                   	<i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-movimientos-de-stock', false))
                                	<form action="{{route('eliminar_movimientostock', ['id' => $movimientostock['id']])}}" class="d-inline form-eliminar" method="POST">
                                   		@csrf @method("delete")
                                   		<button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                       	<i class="fa fa-times-circle text-danger"></i>
                                   	</button>
                                	</form>
								@endif
                       			@if (can('listar-movimientos-de-stock', false))
                                	<a href="{{route('listar_movimientostock', ['id' => $movimientostock['id']])}}" class="btn-accion-tabla tooltipsC" title="Listar el Movimiento de Stock">
                                   	<i class="fa fa-print"></i>
                                	</a>
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
