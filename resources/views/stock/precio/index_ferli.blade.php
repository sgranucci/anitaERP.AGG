@extends("theme.$theme.layout")
@section('titulo')
	Precios
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/stock/precio/indexferli.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/listaprecio/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/mventa/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/categoria/consulta.js') }}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/precio/filtro.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/precio/emitir-lista.js') }}" type="text/javascript"></script>

<script>
function limpiaFiltros(){
	$('#estado').val('');

    var token = $("meta[name='csrf-token']").attr("content");
    var data = "_token="+token;

    $.ajax({
        type: "POST",
        url: '{{ route("precio.limpiafiltro") }}',
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
                <h3 class="card-title">Precios</h3>
                <div class="card-tools">
					@if (session()->get('filtrosPrecios') == '')
						<a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" id='btn_advanced_filter' data-url-parameter=''
							title='Filtros y búsquedas avanzadas' class="btn btn-sm btn-default ">
								<i class="fa fa-filter"></i> Filtros
						</a>
					@endif
					@if (session()->get('filtrosPrecios') != '')
                    	<span id="container-button-state">
                            <button class="btn btn-outline-secondary btn-sm" style="color:white" onclick="limpiaFiltros()">Limpiar filtros</button>
                    	</span>
					@endif
                    <a href="{{route('crear_importacion_precio')}}" class="btn btn-outline-secondary btn-sm">
						@if (can('crear-precios', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Sube precios de excel
						@endif
                    </a>
					<a href="{{route('crear_precio')}}" class="btn btn-outline-secondary btn-sm">
                       	@if (can('crear-precios', false))
                        	<i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
						@endif
                    </a>
                    <a href="{{ route('precio_lista_ferli') }}" class="btn btn-outline-light btn-sm" title="Lista de precios: un artículo por fila y las listas pedidas en columnas">
                        <i class="fa fa-tags"></i> Lista de precios
                    </a>
                    <button type="button" class="btn btn-outline-light btn-sm" id="btn-emitir-lista-vigente" title="Listado plano anterior: una fila por artículo y lista">
                        <i class="fa fa-file-export"></i> Listado plano
                    </button>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data"
					data-url="{{ route('precio.datatable') }}">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Articulo</th>
                            <th>Lista de precios</th>
                            <th>Fecha vigencia</th>
                            <th>Moneda</th>
                            <th>Precio</th>
                            <th>Precio anterior</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('includes.filtroprecio')
@include('includes.stock.modalconsultalistaprecio')
@include('includes.stock.modalconsultamventa')
@include('includes.stock.modalconsultacategoria')
@include('stock.precio.partials.modal_emitir_lista')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'emitir-lista-overlay',
    'tituloId' => 'emitir-lista-overlay-titulo',
    'subtituloId' => 'emitir-lista-overlay-subtitulo',
    'titulo' => 'Generando lista…',
    'subtitulo' => 'Puede demorar según la cantidad de artículos.',
])

@endsection
