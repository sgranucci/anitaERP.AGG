(function (window, $) {
	'use strict';

	function esFacOFceOption($opt) {
		if (!$opt.length || !$opt.val()) {
			return true;
		}
		var abr = String($opt.attr('data-abreviatura') || '').toUpperCase();
		if (abr) {
			return abr === 'FAC' || abr === 'FCE';
		}
		var txt = String($opt.is('option') ? $opt.text() : ($opt.closest('.tm-tipotransaccion-venta-campo').find('.nombretipotransaccionventa').val() || '')).toUpperCase();
		if (/N[CD][EB]|NOTA DE|D[EÉ]BITO/.test(txt) && txt.indexOf('FACTURA') === -1) {
			return false;
		}
		if (/NOTA DE/.test(txt)) {
			return false;
		}
		return true;
	}

	window.aplicarTipoComprobanteSugerido = function (data, $modal) {
		$modal = $modal && $modal.length ? $modal : $(document);
		var $aviso = $modal.find('#aviso-tipo-fce');
		var $sel = $modal.find('#tipotransaccion_id');

		if ($aviso.length) {
			$aviso.addClass('d-none').text('');
		}
		if (!data || data.error) {
			return;
		}

		var codigoExistente = String($('#codigofactura').val() || '').trim();
		if (codigoExistente) {
			return;
		}

		var $meta = (window.metaTipotransaccionVenta)
			? window.metaTipotransaccionVenta($sel)
			: $sel.find('option:selected');
		if (!esFacOFceOption($meta.length ? $meta : $sel)) {
			return;
		}

		var idSugerido = data.tipotransaccion_sugerido_id;
		if ($sel.is('select')) {
			if (idSugerido && $sel.find('option[value="' + idSugerido + '"]').length) {
				$sel.val(String(idSugerido));
			}
		} else if (idSugerido && typeof window.aplicarTipotransaccionVentaPorId === 'function') {
			window.aplicarTipotransaccionVentaPorId(idSugerido, $sel.closest('.tm-tipotransaccion-venta-campo'));
		}

		if (data.es_fce && data.aviso_fce && $aviso.length) {
			$aviso.removeClass('d-none').text(data.aviso_fce);
		}
	};

	window.resetearTipoComprobanteSugerido = function () {
	};
})(window, jQuery);
