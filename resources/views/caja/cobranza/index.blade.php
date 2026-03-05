@extends("theme.$theme.layout")
@section('titulo')
    Cobranzas
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
    function eliminarCobranza(event) {
        var opcion = confirm("Desea eliminar la cobranza?");
        if(!opcion) {
            event.preventDefault();
        }
    }
</script>

@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cobranzas</h3>
                <div class="card-tools">
                    <a href="{{route('crear_cobranza')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-cobranza', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('cobranza') }}" method="GET">
						<div class="btn-group">
							<input type="text" name="busqueda" class="form-control" placeholder="Busqueda ..."> 
							<button type="submit" class="btn btn-default">
								<span class="fa fa-search"></span>
							</button>
						</div>
					</form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla', ['ruta' => 'lista_cobranza', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Detalle</th>
                            <th>Estado</th>
                            <th>Moneda</th>
                            <th>Monto</th>
                            <th>Movimientos</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cobranza as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombreempresa}}</td>
                            <td>{{$data->numerotransaccion}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>{{$data->nombrecliente}}</td>
                            <td>{{$data->detalle ?? ''}}</td>
                            <td>{{$data->estado ?? ''}}</td>
                            <td>{{$data->monedas->abreviatura ?? '' }}</td>
                            <td>{{number_format($data->monto,2)}}</td>
                            <td>
                                <ul>
                                @if ($data->caja_movimientos[0]->caja_movimiento_cuentacajas)
                                    @foreach($data->caja_movimientos[0]->caja_movimiento_cuentacajas as $movimiento)
                                        <li>{{ $movimiento->cuentacajas->nombre }} {{ $movimiento->monto > 0 ? number_format($movimiento->monto,2) : '' }} {{ $movimiento->monto < 0 ? number_format($movimiento->monto,2) : ''}}</li>
                                    @endforeach
                                @endif
                                @if ($data->cobranza_retenciones)
                                    @foreach($data->cobranza_retenciones as $retencion)
                                        <li>{{ $retencion->retencion_cobranzas->nombre }} {{ number_format($retencion->monto,2) }}</li>
                                    @endforeach
                                @endif
                                @for ($i = 0; $i < 10; $i++)
                                    @php $totalCheque[$i] = 0; $moneda[$i] = 0; @endphp
                                @endfor
                                @foreach ($data->cheques as $cheque)
                                    @php $totalCheque[$cheque->moneda_id] += $cheque->monto; $moneda[$cheque->moneda_id] = $cheque->monedas->abreviatura; @endphp
                                @endforeach
                                @for ($i = 0; $i < count($totalCheque); $i++)
                                    @if ($totalCheque[$i] != 0)
                                        <li>Total cheques en {{ $moneda[$i] }} {{ number_format($totalCheque[$i], 2) }}</li>
                                    @endif
                                @endfor
                                </ul>
                            </td>
                            <td>
                       			@if (can('editar-cobranza', false))
                                	<a href="{{route('editar_cobranza', ['id' => $data->id, 'origen' => 'cobranza'])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-cobranza', false))
                                <form action="{{route('eliminar_cobranza', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" onclick="eliminarCobranza(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
								@endif
                                @if (can('listar-cobranza', false))
                                	<a href="{{route('listar_una_cobranza', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Listar la Cobranza">
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
{{ $cobranza->appends(['busqueda' => $busqueda])->links() }}
@endsection
