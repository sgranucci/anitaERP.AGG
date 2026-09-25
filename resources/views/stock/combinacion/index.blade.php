@extends("theme.$theme.layout")
@section('titulo')
	Combinaciones
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>

<script>
@php
    $uiFerliComboJs = \App\Support\Stock\CombinacionEstadoCanalSupport::uiFerliActiva();
@endphp
var UI_FERLI_COMBO = @json($uiFerliComboJs);

function cambiarEstado(id, index, ambito){
  ambito = ambito || 'AMBOS';
  var textoAmbito = (ambito === 'LOCAL') ? ' (local)' : ((ambito === 'FABRICA') ? ' (fábrica)' : '');
  var textoEstado = (index == 0 )?'desactivar':'activar';
  var confirmar = confirm("¿Desea " + textoEstado + " combinación" + textoAmbito + "?");
  if(confirmar){
    var token = $('meta[name="csrf-token"]').attr('content');
    var estado = (index == 1)?'A':'I';
    var data = "id=" + id + "&estado=" + estado + "&ambito=" + ambito + "&_token=" + token;
    $.ajax({
        type: "post",
        url: "{{ route('combinacion.updateState') }}",
        data: data,
        success: function(response){
          var parsed = {};
          try { parsed = (typeof response === 'string') ? JSON.parse(response) : response; } catch (e) { parsed = {}; }
          var fab = parsed.estado_fabrica || (ambito === 'FABRICA' || ambito === 'AMBOS' ? estado : null);
          var loc = parsed.estado_local || (ambito === 'LOCAL' || ambito === 'AMBOS' ? estado : null);
          var leg = parsed.estado || estado;

          if (UI_FERLI_COMBO) {
            if (fab) {
              $("#container-estado-fab"+id).html(fab);
              $("#container-button-fab"+id).html(
                fab === 'A'
                  ? "<button type='button' class='btn-xs btn-danger ml-1' onclick=\"cambiarEstado("+id+", 0, 'FABRICA')\">Fab. off</button>"
                  : "<button type='button' class='btn-xs btn-success ml-1' onclick=\"cambiarEstado("+id+", 1, 'FABRICA')\">Fab. on</button>"
              );
            }
            if (loc) {
              $("#container-estado-loc"+id).html(loc);
              $("#container-button-loc"+id).html(
                loc === 'A'
                  ? "<button type='button' class='btn-xs btn-danger ml-1' onclick=\"cambiarEstado("+id+", 0, 'LOCAL')\">Loc. off</button>"
                  : "<button type='button' class='btn-xs btn-success ml-1' onclick=\"cambiarEstado("+id+", 1, 'LOCAL')\">Loc. on</button>"
              );
            }
            $("#container-estado"+id).html(leg);
          } else {
            $("#container-button-state"+id).html(
              index == 1
                ? "<button type='button' class='btn-xs btn-danger ml-2' onclick='cambiarEstado("+id+", 0)'>Desactivar</button>"
                : "<button type='button' class='btn-xs btn-success ml-2' onclick='cambiarEstado("+id+", 1)'>Activar</button>"
            );
            $("#container-estado"+id).html(leg);
          }
        }
    });
  }
}

</script>

@endsection

<?php use App\Helpers\biblioteca ?>

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}" />
@php
    $uiFerliCombo = \App\Support\Stock\CombinacionEstadoCanalSupport::uiFerliActiva();
@endphp
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
                            @if ($uiFerliCombo)
                            <th class="width20">Fab.</th>
                            <th class="width20">Local</th>
                            @else
                            <th class="width20">Estado</th>
                            @endif
                            <th class="width80">Foto</th>
                            <th data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($combinaciones as $combinacion)
                            @php
                                $fab = $combinacion->estado_fabrica ?? $combinacion->estado;
                                $loc = $combinacion->estado_local ?? $combinacion->estado;
                            @endphp
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
                                @if ($uiFerliCombo)
        						<td>
                        		<span id="container-estado-fab{{$combinacion->id}}">{{ $fab }}</span>
        						</td>
        						<td>
                        		<span id="container-estado-loc{{$combinacion->id}}">{{ $loc }}</span>
        						</td>
                                @else
        						<td>
                        		<span id="container-estado{{$combinacion->id}}">
            						{{ $combinacion->estado ?? '' }}
								</span>
        						</td>
                                @endif
                            	<td><img width=100px src="{{ isset($combinacion->foto) ? asset("storage/imagenes/fotos_articulos/$combinacion->foto") : asset("storage/imagenes/fotos_articulos/".$combinacion->articulos->sku."-".$combinacion->codigo.".jpg") }}"></td>
        						<td>
                       			@if (can('cambiar-estado-combinaciones', false))
                                    @if ($uiFerliCombo)
                        		<span id="container-button-fab{{$combinacion->id}}">
									@if ($fab == 'A')
            							<button type="button" class="btn-xs btn-danger ml-1" onclick="cambiarEstado({{$combinacion->id}}, 0, 'FABRICA')">Fab. off</button>
									@else
            							<button type="button" class="btn-xs btn-success ml-1" onclick="cambiarEstado({{$combinacion->id}}, 1, 'FABRICA')">Fab. on</button>
									@endif
                        		</span>
                        		<span id="container-button-loc{{$combinacion->id}}">
									@if ($loc == 'A')
            							<button type="button" class="btn-xs btn-danger ml-1" onclick="cambiarEstado({{$combinacion->id}}, 0, 'LOCAL')">Loc. off</button>
									@else
            							<button type="button" class="btn-xs btn-success ml-1" onclick="cambiarEstado({{$combinacion->id}}, 1, 'LOCAL')">Loc. on</button>
									@endif
                        		</span>
                                    @else
                        		<span id="container-button-state{{$combinacion->id}}">
									@if ($combinacion->estado == 'A')
            							<button type="button" class="btn-xs btn-danger ml-2" onclick="cambiarEstado({{$combinacion->id}}, 0)">Desactivar</button>
									@else
            							<button type="button" class="btn-xs btn-success ml-2" onclick="cambiarEstado({{$combinacion->id}}, 1)">Activar</button>
									@endif
                        		</span>
                                    @endif
								@endif
                       			@if (can('editar-combinaciones-disenio', false))
          							<a href="{{ route('combinacion.edit', ['id' => $combinacion->id]) }}" type="button" class="btn-xs btn-primary ml-2">Dise&ntilde;o</a>
								@endif
                       			@if (can('editar-combinaciones-tecnica', false))
          							<a href="{{ route('combinacion.edit', ['id' => $combinacion->id, 'tipo' => 'tecnica']) }}" type="button" class="btn-xs btn-primary ml-2">T&eacute;cnica</a>
								@endif
                       			@if (can('imprimir-articulos-qr', false))
          							<a href="{{ route('product.download', ['sku' => $combinacion->articulos->sku, 'codigo' => $combinacion->codigo]) }}" class="btn-accion-tabla tooltipsC" title="Imprimir QR">
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
