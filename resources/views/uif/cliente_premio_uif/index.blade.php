@extends("theme.$theme.layout")
@section('titulo')
Premios UIF
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca ?>

@section('styles')
<style>
    .premio-foto-thumb-link { display: inline-block; line-height: 0; }
    .premio-foto-thumb {
        max-height: 48px;
        max-width: 48px;
        width: auto;
        height: auto;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #ddd;
        vertical-align: middle;
    }
    .premio-foto-thumb-link:hover .premio-foto-thumb { border-color: #3c8dbc; opacity: 0.92; }
</style>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Premios UIF</h3>
                <div class="card-tools">
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('consulta_cliente_premio_uif') }}" method="GET">
						<div class="btn-group">
							<input type="text" name="busqueda" class="form-control" placeholder="Busqueda ..." value="{{ $busqueda ?? '' }}"> 
							<button type="submit" class="btn btn-default">
								<span class="fa fa-search"></span>
							</button>
						</div>
					</form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla', ['ruta' => 'lista_cliente_premio_uif', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width10">ID</th>
                            <th>Nombre</th>
                            <th>Sala</th>
                            <th>Juego</th>
                            <th>Fecha Entrega</th>
                            <th style="text-align: right;">Monto</th>
                            <th>Posición</th>
                            <th>Número TITO</th>
                            <th>Forma de Pago</th>
                            <th style="text-align: center; width: 72px;">Foto</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cliente_premio_uifs as $data)
                       		<tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombrecliente}}</td>
                            <td>{{$data->nombresala}}</td>
                            <td><small>{{$data->nombrejuego}}</small></td>
                            <td><small>{{$data->fechaentrega}}</small></td>
                            <td style="text-align: right;"><small>{{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }}</small></td>
                            <td><small>{{$data->posicion ?? ''}}</small></td>
                            <td><small>{{$data->numerotito ?? ''}}</small></td>
                            <td><small>{{$data->nombreformapago}}</small></td>
                            <td class="text-center align-middle premio-foto-preview">
                                @if (! empty($data->foto ?? null))
                                    @if (can('editar-cliente-premio-uif', false))
                                        <a href="{{ route('muestra_foto_cliente_premio_uif', ['id' => $data->id]) }}" class="premio-foto-thumb-link tooltipsC" title="Ver foto del jugador">
                                            <img src="{{ asset('storage/imagenes/fotos_uif/'.$data->foto) }}" alt="" class="premio-foto-thumb">
                                        </a>
                                    @else
                                        <img src="{{ asset('storage/imagenes/fotos_uif/'.$data->foto) }}" alt="" class="premio-foto-thumb">
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                       			@if (can('editar-cliente-premio-uif', false))
                                	<a href="{{route('edita_cliente_premio_uif', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-cliente-premio-uif', false))
                                <form action="{{route('elimina_cliente_premio_uif', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
{{ $cliente_premio_uifs->appends(['busqueda' => $busqueda])->links() }}
@endsection
