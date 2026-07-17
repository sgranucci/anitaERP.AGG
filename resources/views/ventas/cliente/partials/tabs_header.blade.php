<style>
	.cliente-tabs-header #tabs-cliente {
		margin-bottom: 0;
	}
	.cliente-tabs-header #btn-consulta-arca-padron-crear {
		margin-bottom: 6px;
	}
	.cliente-tabs-header #tabs-cliente .nav-link {
		color: #17202A;
		border-top: 3px solid transparent;
	}
	.cliente-tabs-header #tabs-cliente .nav-link:hover {
		background-color: #EBF5FB;
		border-color: #EBF5FB #EBF5FB #dee2e6;
	}
	.cliente-tabs-header #tabs-cliente .nav-link.active {
		color: #1B4F72;
		font-weight: 600;
		background-color: #D6EAF8;
		border-color: #85C1E9 #85C1E9 #fff;
		border-top: 3px solid #2471A3;
	}
	.cliente-tabs-header #tabs-cliente .nav-link.active i {
		color: #2471A3;
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
