@extends("theme.$theme.layout")
@section('titulo')
Art&iacute;culos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/filtro.js")}}" type="text/javascript"></script>

<script>
function checkState(index){
  var confirmar = confirm("¿Desea inactivar combinaciones de forma masiva?");
  if(confirmar){

    var id = $("#producto_id").val();
    var token = $("meta[name='csrf-token']").attr("content");
    var estado = 'I';
    var data = "id="+id+"&estado="+estado+"&_token="+token;

    $.ajax({
        type: "POST",
        url: "{{ route('combinacion.updateStateAll') }}",
        data: data,
        success: function(response){
          window.location.reload();
        }
    });
  }
}

function limpiaFiltros(){
	$('#estado').val('');
	$('#usoarticulo_id').val('');

    var token = $("meta[name='csrf-token']").attr("content");
    var data = "_token="+token;

    $.ajax({
        type: "POST",
        url: "{{ route('product.limpiafiltro') }}",
		data: data,
        success: function(response){
			window.location.replace(window.location.pathname);
        }
    });
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
                <h3 class="card-title">Art&iacute;culos</h3>
                <div class="card-tools">
                    <a href="{{route('product.create')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-articulos-disenio', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                    <span id="container-button-state">
                       	@if (can('cambiar-estado-combinaciones', false))
                            <button class="btn btn-outline-secondary btn-sm" style="color:white" onclick="checkState(0)">Inactivar combinaciones</button>
                        @endif
                    </span>
                   	<a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" id='btn_advanced_filter' data-url-parameter=''
						title='Filtros y búsquedas avanzadas' class="btn btn-sm btn-default ">
                       	@if (can('filtrar-articulos', false))
                       		<i class="fa fa-filter"></i> Filtros y Orden
						@endif
                    </a>
					@if (session()->get('filtros') != '')
                    	<span id="container-button-state">
                            <button class="btn btn-outline-secondary btn-sm" style="color:white" onclick="limpiaFiltros()">Limpiar filtros</button>
                    	</span>
					@endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Categor&iacute;a</th>
                            <th>Marca</th>
                            <th>L&iacute;nea</th>
                            <th>Facturable</th>
                            <th data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($articulos as $articulo)
    						<tr>
        						<td>
            						{{ $articulo->stkm_articulo ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->stkm_desc ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->stkm_agrupacion ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->stkm_marca ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->stkm_linea ?? '' }}
        						</td>
                                <td>
                                    {{ $articulo->nofactura == '0' ? 'Facturable' : 'No facturable'}}
                                </td>
                            <td>
								@if ($articulo->usoarticulo_id == 1)
                       				@if (can('editar-articulos-combinaciones', false))
          								<a class="btn-xs btn-primary ml-2" style="padding: 1px" href="combinacion/index/{{$articulo->id}}">Combinaciones</a>
									@endif
								@endif
                       			@if (can('editar-articulos-disenio', false))
          							<a class="btn-xs btn-primary ml-2" style="padding: 1px" href="product/edit/{{$articulo->id}}/disenio">Diseño</a>
								@endif
                       			@if (can('editar-articulos-tecnica', false))
          							<a class="btn-xs btn-primary ml-2" style="padding: 1px" href="product/edit/{{$articulo->id}}/tecnica">T&eacute;cnica</a>
								@endif
                       			@if (can('editar-articulos-contaduria', false))
          							<a class="btn-xs btn-primary ml-2" style="padding: 1px" href="product/edit/{{$articulo->id}}/contaduria">Contable</a>
								@endif
                       			@if (can('imprimir-articulos-qr', false))
          							<a href="product/{{$articulo->stkm_articulo}}/TODO" class="btn-accion-tabla tooltipsC" title="Imprimir QR">
                                   		<i class="fa fa-qrcode"></i>
									</a>
								@endif
                       			@if (can('borrar-articulos', false))
                                <form action="{{route('product.delete', ['id' => $articulo->id])}}" class="d-inline form-eliminar" method="POST">
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

@include('includes.filtroarticulo')

@endsection
