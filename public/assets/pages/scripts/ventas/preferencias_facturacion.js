window.PreferenciasFacturacionUsuario = {
	token: function () {
		return $('meta[name="csrf-token"]').attr('content') || $('#csrf_token').val() || '';
	},
	opcionSelected: function (defaultId, itemId) {
		if (defaultId === undefined || defaultId === null || defaultId === '') {
			return '';
		}
		return String(defaultId) === String(itemId) ? ' selected="selected"' : '';
	},
	esTipoFceSeleccionado: function ($sel) {
		$sel = $sel && $sel.length ? $sel : $('#tipotransaccion_id');
		var $opt = $sel.find('option:selected');
		if (!$opt.length || !$opt.val()) {
			return false;
		}
		var abr = String($opt.attr('data-abreviatura') || '').toUpperCase();
		if (abr === 'FCE' || abr === 'NCE' || abr === 'DCE') {
			return true;
		}
		var txt = String($opt.text() || '').toUpperCase();
		return /(^|[\s\-])FCE([\s\-]|$)/.test(txt)
			|| txt.indexOf('FACTURA DE CRÉDITO') !== -1
			|| txt.indexOf('FACTURA DE CREDITO') !== -1;
	},
	guardar: function () {
		if (!window.FACTURA_URLS || !window.FACTURA_URLS.preferencias) {
			return;
		}
		var tipo = $('#tipotransaccion_id').val() || '';
		if (window.PreferenciasFacturacionUsuario.esTipoFceSeleccionado($('#tipotransaccion_id'))) {
			tipo = '';
		}
		var pv = $('#puntoventa_id').val() || '';
		var pvRemito = $('#puntoventaremito_id').val() || '';
		if (!tipo && !pv && !pvRemito) {
			return;
		}
		$.post(window.FACTURA_URLS.preferencias, {
			_token: window.PreferenciasFacturacionUsuario.token(),
			tipotransaccion_id: tipo,
			puntoventa_id: pv,
			puntoventaremito_id: pvRemito
		});
		if (tipo) {
			$('#tipotransacciondefault_id').val(tipo);
		}
		if (pv) {
			$('#puntoventadefault_id').val(pv);
		}
		if (pvRemito) {
			$('#puntoventaremitodefault_id').val(pvRemito);
		}
	},
	enlazarCambios: function () {
		$(document)
			.off('change.prefFact', '#tipotransaccion_id, #puntoventa_id, #puntoventaremito_id')
			.on('change.prefFact', '#tipotransaccion_id, #puntoventa_id, #puntoventaremito_id', function () {
				window.PreferenciasFacturacionUsuario.guardar();
			});
	}
};

$(function () {
	if (window.PreferenciasFacturacionUsuario) {
		window.PreferenciasFacturacionUsuario.enlazarCambios();
	}
});
