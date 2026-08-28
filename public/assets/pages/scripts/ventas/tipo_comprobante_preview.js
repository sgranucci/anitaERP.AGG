(function (window, $) {
	'use strict';

	window._tipoFceAutoAplicado = false;

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

		var idSugerido = data.tipotransaccion_sugerido_id;
		if (data.es_fce && idSugerido && $sel.find('option[value="' + idSugerido + '"]').length) {
			$sel.val(String(idSugerido));
			window._tipoFceAutoAplicado = true;
		} else if (window._tipoFceAutoAplicado) {
			var idDefault = $('#tipotransacciondefault_id').val();
			if (idDefault && $sel.find('option[value="' + idDefault + '"]').length) {
				$sel.val(String(idDefault));
			}
			window._tipoFceAutoAplicado = false;
		}

		if (data.aviso_fce && $aviso.length) {
			$aviso.removeClass('d-none').text(data.aviso_fce);
		}
	};

	window.resetearTipoComprobanteSugerido = function () {
		window._tipoFceAutoAplicado = false;
	};
})(window, jQuery);
