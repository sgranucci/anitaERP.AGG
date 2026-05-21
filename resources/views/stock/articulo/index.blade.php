@extends("theme.$theme.layout")
@section('titulo')
Art&iacute;culos
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/filtro.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/salida.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/configurar_salida.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/configuracion/configurar_modeloetiqueta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta-precios.js")}}" type="text/javascript"></script>

<script>

var url = "{{ route('configurar_salida', ['programa' => ':programa']) }}";

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
                @include('includes.configurar-salida')
                @include('includes.configurar-modeloetiqueta')
                <div class="card-tools">
                    <a href="{{route('crear_articulo')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-articulos', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                    @if (can('actualizar-articulos', false))
                    <form action="{{ route('sincronizar_articulo_anita') }}" method="POST" class="d-inline" onsubmit="return confirm('La sincronizaci\u00f3n puede tardar muchos minutos. Solo se dan de alta art\u00edculos que existan en Anita y a\u00fan no est\u00e9n en el ERP. Si aparece error 504 (tiempo de espera), ejecute en el servidor:\nphp artisan articulo:sincronizar-anita\n\n\u00bfContinuar?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm" title="Importar desde Anita (stkmae) artículos que falten en el ERP">
                            <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                        </button>
                    </form>
                    @endif
    				<a href="#" onclick="configurarSalida()" class="btn btn-outline-secondary btn-sm">
						<i class="fa fa-fw fa-cog"></i> Configura salida
					</a>
    				<a href="#" onclick="configurarModeloEtiqueta()" class="btn btn-success btn-sm">
						<i class="fa fa-fw fa-print"></i> Configura etiqueta
					</a>                    
                </div>
                <div class="d-md-flex justify-content-md-end">
					<form action="{{ route('articulo') }}" method="GET">
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
                            <th>SKU</th>
                            <th>Descripci&oacute;n</th>
                            <th>Unidad de Medida</th>
                            <th>Categoría</th>
                            <th>Tipo de Artículo</th>
                            <th>Uso</th>
                            <th>Nro.Parte</th>
                            <th>Ubic.Parte</th>
                            <th class="text-right" title="Saldo en Anita (stkdep) para artículos LAB con depósito de entrega">Saldo dep.</th>
                            <th>Facturable</th>
                            <th>Estado</th>
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
                                    {{ $articulo->nombreusoarticulo ?? '' }}
                                </td>
                                <td>{{$articulo->numeroparte ?? ''}}</td>
                                <td>{{$articulo->ubicacionparte ?? ''}}</td>
                                <td class="text-right">
                                    @if(isset($saldosStkdep[$articulo->id]))
                                        {{ number_format($saldosStkdep[$articulo->id], 2, ',', '.') }}
                                    @endif
                                </td>
                                <td>
                                    {{ ($articulo->nofactura == '0' ? 'Facturable' : ($articulo->nofactura == '1' ? 'No facturable' : '' )) }}
                                </td>
                                <td>{{ $articulo->estado }}</td>
                            <td>
                       			@if (can('listar-precios', false) || can('listar-articulos', false))
                                	<button type="button"
                                	    class="btn-accion-tabla consultapreciosarticulo tooltipsC"
                                	    title="Consultar precios en listas de venta"
                                	    data-articulo-id="{{ $articulo->id }}"
                                	    data-articulo-sku="{{ $articulo->codigoarticulo ?? $articulo->sku ?? '' }}"
                                	    data-articulo-descripcion="{{ $articulo->descripcion ?? '' }}">
                                        <i class="fas fa-dollar-sign text-success"></i>
                                	</button>
								@endif
                       			@if (can('editar-articulos', false))
                                	<a href="{{route('editar_articulo', ['id' => $articulo->id])}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                	</a>                                    
								@endif
                       			@if (can('imprimir-articulos-qr', false))
          							<a href="{{route('listar_etiqueta_articulo', ['id' => $articulo->id])}}" class="btn-accion-tabla tooltipsC" title="Imprimir QR">
                                   		<i class="fa fa-qrcode"></i>
									</a>
								@endif
                       			@if (can('borrar-articulos', false))
                                <form action="{{route('eliminar_articulo', ['id' => $articulo->id])}}" class="d-inline form-eliminar" method="POST">
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
@include('includes.stock.modalconsultaprecioarticulo')
@endsection
