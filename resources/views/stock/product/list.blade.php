@extends("theme.$theme.layout")
@section('titulo')
Art&iacute;culos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/filtro.js")}}" type="text/javascript"></script>

<script>
function checkState(index){
}

function limpiaFiltros(){
	$('#estado').val('');
	$('#usoarticulo_id').val('');

    var token = $("meta[name='csrf-token']").attr("content");
    var data = "_token="+token;

    $.ajax({
        type: "POST",
        url: '/anitaERP/public/stock/product/limpiafiltro',
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
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('products.index') }}" method="GET">
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
                @include('includes.exportar-tabla', ['ruta' => 'lista_articulo', 'busqueda' => $busqueda])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Unidad de Medida</th>
                            <th>Categoría</th>
                            <th>Tipo de Artículo</th>
                            <th>Facturable</th>
                            <th data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
						@foreach($articulos as $articulo)
    						<tr>
        						<td>
            						{{ $articulo->codigoarticulo ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->descripcion ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->nombreunidadmedida ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->nombrecategoria ?? '' }}
        						</td>
        						<td>
            						{{ $articulo->nombretipoarticulo ?? '' }}
        						</td>
                                <td>
                                    {{ $articulo->nofactura == '0' ? 'Facturable' : 'No facturable'}}
                                </td>
                            <td>
                                @if (config('app.empresa') == 'Calzados Ferli')
                                    @if ($articulo->usoarticulo_id == 1)
                                        @if (can('editar-articulos-combinaciones', false))
                                            <a class="btn-xs btn-primary ml-2" style="padding: 1px" href="combinacion/index/{{$articulo->id}}">Combinaciones</a>
                                        @endif
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
{{ $articulos->appends(['busqueda' => $busqueda])->links() }}
@endsection
