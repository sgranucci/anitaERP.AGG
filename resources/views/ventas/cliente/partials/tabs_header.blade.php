<style>
	.cliente-tabs-header #tabs-cliente {
		margin-bottom: 0;
	}
	.cliente-tabs-header #btn-consulta-arca-padron-crear {
		margin-bottom: 6px;
	}
</style>
<div class="d-flex flex-wrap align-items-end cliente-tabs-header">
	<div class="flex-grow-1" style="min-width: 0; overflow-x: auto;">
		@include('ventas.cliente.partials.tabs_nav', ['mostrarSuitecrm' => $mostrarSuitecrm ?? false])
	</div>
	<button type="button" id="btn-consulta-arca-padron-crear" class="btn btn-outline-secondary btn-sm ml-2 flex-shrink-0" title="Ingresá el CUIT y consultá el padrón ARCA">
		<i class="fa fa-search"></i> Consulta padr&oacute;n ARCA
	</button>
</div>
