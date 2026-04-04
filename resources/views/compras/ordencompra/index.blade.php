@extends("theme.$theme.layout")
@section('titulo')
Ordenes de Compra
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
                <h3 class="card-title">Ordenes de Compra</h3>
                <div class="card-tools">
                    <a href="#" {{--"{{route('crear_ordencompra')}}"--}} class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-ordencompra', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data-2">
                    <thead>
                        <tr>
                            <th class="width10">ID</th>
                            <th>Fecha</th>
                            <th>Entrega</th>
                            <th>Empresa</th>
                            <th>Proveedor</th>
                            <th>CC Origen</th>
                            <th>CC Destino</th>
                            <th>Moneda</th>
                            <th>Cotizacion</th>
                            <th>Anticipada</th>
                            <th>Estado</th>
                            <th>Items</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ordencompra as $data)
							@if ($data->estado == '4')
                        		<tr class="table-danger">
							@else
                        		<tr>
							@endif
                            <td>{{$data->id}}</td>
                            <td>{{date('d/m/Y', strtotime($data->fecha))}}</td>
                            <td>{{date('d/m/Y', strtotime($data->fechaentrega))}}</td>
                            <td>{{$data->nombreempresa}}</td>
                            <td><small>{{$data->nombreproveedor}}</small></td>
                            <td><small>{{$data->ccorigen}}</small></td>
                            <td><small>{{$data->ccdestino}}</small></td>
                            <td>
                                @foreach ($moneda_query as $moneda)
                                    @if ($moneda->codigo == $data->codigomoneda)
                                        <small>{{$moneda->nombre ?? ''}}</small>
                                    @endif
                                @endforeach
                            </td>
                            <td style="text-align: right;"><small>{{number_format($data->cotizacion,4)}}</small></td>
                            <td><small>{{$data->esanticipo}}</small></td>
                            <td>
                                @switch($data->estado)
                                    @case('0')
                                        <small>PENDIENTE</small>
                                    @break
                                    @case('1')    
                                        <small>ENTR.PARCIAL</small>
                                    @break
                                    @case('2')    
                                        <small>ENTREGADO</small>
                                    @break
                                    @case('3')    
                                        <small>FACTURADO</small>
                                    @break
                                    @case('4')    
                                        <small>SUSPENDIDO</small>
                                    @break
                                    @case('C')    
                                        <small>CERRADA</small>
                                    @break
                                    @case('D')    
                                        <small>CERRADA CON DIF.</small>
                                    @break
                                    @case('A')    
                                        <small>A AUTORIZAR</small>
                                    @break 
                                @endswitch
                            </td>
                            <td>
                                @if ($items)
                                    @foreach ($items as $item)
                                        @if ($item->id == $data->id)
                                            <small>{{$item->sku}}-{{$item->descarticulo}}-Cant.:{{$item->cantidad}}-Precio:{{$item->precio}}</small><br>
                                        @endif
                                    @endforeach
                                @endif
                            </td>
                            <td>
                       			@if (can('editar-ordencompra', false))
                                	<a href="#" {{--"{{route('editar_ordencompra', ['id' => $data->id])}}"--}} class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-ordencompra', false))
                                <form action="#" {{--"{{route('eliminar_ordencompra', ['id' => $data->id])}}"--}} class="d-inline form-eliminar" method="POST">
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
