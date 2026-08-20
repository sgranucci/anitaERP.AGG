@include('includes.tabs-activas-estilos')
<div class="d-flex flex-wrap align-items-end cliente-tabs-header">
	<div class="flex-grow-1 tabs-activas" style="min-width: 0; overflow-x: auto;">
		@include('ventas.cliente.partials.tabs_nav', ['mostrarSuitecrm' => $mostrarSuitecrm ?? false])
	</div>
	<button type="button" id="btn-consulta-arca-padron-crear" class="btn btn-outline-secondary btn-sm ml-2 flex-shrink-0 mb-1" title="Ingresá el CUIT y consultá el padrón ARCA">
		<i class="fa fa-search"></i> Consulta padr&oacute;n ARCA
	</button>
</div>
