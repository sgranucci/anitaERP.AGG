@extends("theme.$theme.layout")
@section('titulo')
    Encuestas del Proveedor
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
                <h3 class="card-title">Encuestas del Proveedor Código {{$encuesta_proveedor[0]->codigoproveedor??''}}-{{$encuesta_proveedor[0]->nombreproveedor??''}}</h3>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('listar_encuesta_proveedor', ['id' => $id]) }}" method="GET">
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
                @include('includes.exportar-tabla-id', ['ruta' => 'listar_encuesta_proveedor', 'busqueda' => $busqueda, 'proveedor_id' => $id])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width10">ID</th>
                            <th>Nombre</th>
                            <th>Fecha</th>
                            <th>Origen</th>
                            <th>Comentarios</th>
                            <th>Preguntas</th>
                            <th>Puntaje Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($encuesta_proveedor as $data)
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{$data->fecha}}</td>
                            <td>{{$data->origen}}</td>
                            <td>{{$data->comentario}}</td>
                            <td>
                                @php $puntajeTotal = 0; @endphp
                                @foreach($data->proveedor_encuesta_preguntas as $pregunta)
                                    <li>{{$pregunta->encuesta_preguntas->nombre}} ({{$pregunta->encuesta_preguntas->desdepuntaje}}-{{$pregunta->encuesta_preguntas->hastapuntaje}}) Puntaje {{$pregunta->puntaje}}</li>
                                    @php $puntajeTotal += $pregunta->puntaje; @endphp
                                @endforeach
                            </td>
                            <td>{{$puntajeTotal}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $encuesta_proveedor->appends(['busqueda' => $busqueda])->links() }}
@endsection
