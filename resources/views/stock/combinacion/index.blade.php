@extends("theme.$theme.layout")
@section('titulo')
	Combinaciones
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
function cambiarEstado(id, index){
  var textoEstado = (index == 0 )?'desactivar':'activar';
  var confirmar = confirm("¿Desea " + textoEstado + " combinación?");
  if(confirmar){
    var token = $('meta[name="csrf-token"]').attr('content');
    var estado = (index == 1)?'A':'I';
    var data = "id=" + id + "&estado=" + estado + "&_token=" + token;
    $.ajax({
        type: "post",
        url: '/anitaERP/public/stock/combinacion/updateState',
        data: data,
        success: function(response){
          $("#container-button-state"+id).html("");
          var btn = '';
          var estado = '';
          if(index == 1){
            btn = "<button type='button' class='btn-xs btn-danger ml-2' onclick='cambiarEstado("+id+", 0)'>Desactivar</button>";
            estado = "A";
          }else{
            btn = "<button type='button' class='btn-xs btn-success ml-2' onclick='cambiarEstado("+id+", 1)'>Activar</button>";
            estado = "I";
          }
          $("#container-button-state"+id).html(btn);

          $("#container-estado"+id).html("");
          $("#container-estado"+id).html(estado);
        }
    });
  }
}

</script>

@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}" />
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Combinaciones</h3>
                <div class="card-tools">
                    <a href="{{route('combinacion.create', ['id' => $articulo->id] )}}" class="btn btn-outline-secondary btn-sm">
            						{{ $combinacion->articulos->sku ?? '' }} {{ $combinacion->articulos->descripcion ?? '' }}
                       	@if (can('crear-combinaciones', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm">
                        	<i class="fa fa-fw fa-plus-circle"></i> Volver a art&iacute;culos
                    </a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th class="width80">Combinaci&oacute;n</th>
                            <th>Nombre</th>
                            <th>Art&iacute;culo</th>
                            <th class="width20">Estado</th>
                            <th class="width80">Foto</th>
                            <th data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($combinaciones as $combinacion)
    						<tr data-entry-id="{{ $combinacion->id }}">
        						<td>
            						{{ $combinacion->id ?? '' }}
        						</td>
        						<td>
            						{{ $combinacion->codigo ?? '' }}
        						</td>
        						<td>
            						{{ $combinacion->nombre ?? '' }}
        						</td>
        						<td>
            						{{ $combinacion->articulos->sku ?? '' }} {{ $combinacion->articulos->descripcion ?? '' }}
        						</td>
        						<td> 
                        		<span id="container-estado{{$combinacion->id}}">
            						{{ $combinacion->estado ?? '' }}
								</span>
        						</td>
                            	<td><img width=100px src="{{ isset($combinacion->foto) ? asset("storage/imagenes/fotos_articulos/$combinacion->foto") : asset("storage/imagenes/fotos_articulos/".$combinacion->articulos->sku."-".$combinacion->codigo.".jpg") }}"></td>
        						<td>
                       			@if (can('cambiar-estado-combinaciones', false))
                        		<span id="container-button-state{{$combinacion->id}}">
									@if ($combinacion->estado == 'A')
            							<button type="button" class="btn-xs btn-danger ml-2" onclick="cambiarEstado({{$combinacion->id}}, 0)">Desactivar</button>
									@else
            							<button type="button" class="btn-xs btn-success ml-2" onclick="cambiarEstado({{$combinacion->id}}, 1)">Activar</button>
									@endif
                        		</span>
								@endif
                       			@if (can('editar-combinaciones-disenio', false))
          							<a href="/anitaERP/public/stock/combinacion/edit/{{ $combinacion->id }}" type="button" class="btn-xs btn-primary ml-2">Dise&ntilde;o</a>
								@endif
                       			@if (can('editar-combinaciones-tecnica', false))
          							<a href="/anitaERP/public/stock/combinacion/edit/{{$combinacion->id}}/tecnica" type="button" class="btn-xs btn-primary ml-2">T&eacute;cnica</a>
								@endif
                       			@if (can('imprimir-articulos-qr', false))
          							<a href="/anitaERP/public/stock/product/{{$combinacion->articulos->sku}}/{{$combinacion->codigo}}" class="btn-accion-tabla tooltipsC" title="Imprimir QR">
                                   		<i class="fa fa-qrcode"></i>
									</a>
								@endif
                       			@if (can('borrar-combinaciones', false))
                                	<form action="{{route('eliminar_combinacion', ['id' => $combinacion->id])}}" class="d-inline form-eliminar" method="POST">
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
