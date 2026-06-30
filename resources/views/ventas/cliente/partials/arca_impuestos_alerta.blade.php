<div id="arca-impuestos-alerta" class="alert alert-danger" style="display: none; margin: 0 0 10px 0;" role="alert">
	<strong><i class="fa fa-exclamation-triangle"></i> ARCA — impuestos</strong>
	<div id="arca-impuestos-alerta-mensaje" class="mt-1"></div>
	<ul id="arca-impuestos-alerta-detalles" class="mb-0 mt-2 small" style="display: none;"></ul>
	<p class="small mb-2 mt-2">El sistema no suspende autom&aacute;ticamente: suspenda o regularice (estado R) manualmente y grabe. No aplica regularizaci&oacute;n con condici&oacute;n IVA Baja de impuestos.</p>
	@if (can('suspender-clientes', false))
	<button type="button" id="btn-regularizar-arca" class="btn btn-warning btn-sm" style="display: none;">
		<i class="fa fa-check-circle"></i> Regularizar cliente (R)
	</button>
	@endif
</div>
