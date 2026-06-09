@extends("theme.$theme.layout")
@section('titulo')
    Cuenta Corriente de Clientes
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/cuentacorriente/consulta.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cuenta Corriente Cliente: {{$nombrecliente}}</h3>
                <div class="card-tools">
                    @if (!str_contains($urlOrigen, 'editar'))
                        @if (isset($urlOrigen))
                            <a href="{{$urlOrigen}}" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver  
                            </a>
                        @else
                            <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver atrás
                            </a>
                        @endif
                    @endif
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('listar_cuentacorriente_cliente', ['id' => $id]) }}" method="GET">
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
                @include('includes.exportar-tabla-id', ['ruta' => 'listar_cuentacorriente_cliente', 'id' => $id, 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Fecha</th>
                            <th>Vencimiento</th>
                            <th>Comprobante</th>
                            <th>Moneda</th>
                            <th style="width: 12%; text-align: right;">Debe</th>
                            <th style="width: 12%; text-align: right;">Haber</th>
                            <th style="width: 12%; text-align: right;">Saldo</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $saldo = 0; @endphp
                        @foreach ($cuentacorriente as $data)
                            @php $saldo += $data->total; @endphp
                        <tr>
                            <td class="cuentacorriente_id">{{$data->id}}</td>
                            <td>{{$data->empresas->nombre ?? '' }}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fechavencimiento ?? ''))}}</td>
                            <td class="comprobante">
                                {{$data->cobranza_id > 0 ? $data->cobranzas->detalle : $data->ventas->codigo}}
                                @if ($data->venta_id > 0 && !empty($data->ventas->lugarentrega))
                                    <br><small class="text-muted">Entrega: {{ $data->ventas->lugarentrega }}</small>
                                @endif
                            </td>
                            <td>
                                {{$data->monedas->abreviatura}}
                                <input type="hidden" name="moneda" class="form-control moneda" value="{{$data->monedas->id}}"> 
                            </td>
                            <td class="debe" style="text-align: right;">
                                @if ($data->total >= 0)
                                    {{number_format($data->total, 2)}}
                                @endif
                            </td>
                            <td class="haber" style="text-align: right;">
                                @if ($data->total < 0)
                                    {{number_format(abs($data->total), 2)}}
                                @endif
                            </td>
                            <td style="text-align: right;">
                                {{number_format($saldo, 2)}}
                            </td>
                            <td>
                                <input type="hidden" name="total" id="total" class="form-control total" value="{{$data->total}}"/>
                               	<a href="{{route('editar_cuentacorriente_cliente', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                               	</a>
                                <a href="#" class="btn-accion-tabla tooltipsC veraplicaciones" title="Ver aplicaciones">
                                    <i class="fa fa-clone"></i>
                               	</a>                                
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@include('ventas.cuentacorriente.modalaplicacion')
{{ $cuentacorriente->appends(['busqueda' => $busqueda]) }}
@endsection
