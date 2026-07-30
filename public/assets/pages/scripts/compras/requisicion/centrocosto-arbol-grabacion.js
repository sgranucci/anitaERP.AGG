/**
 * Intercepta alta/actualización de requisición cuando hay varios CC de destino
 * y pide el mismo modal que el retome desde EN COMPRAS.
 */
(function ($) {
	'use strict';

	function debePedirCcArbolAlGrabar() {
		if (typeof window.requisicionPideCcArbolAlGrabar !== 'undefined') {
			return !!window.requisicionPideCcArbolAlGrabar;
		}
		// Alta normal (no provisorio): sí. Provisorio: no.
		return !window.requisicionModoProvisorio;
	}

	function centrosCostoDistintosDesdeFormulario($form) {
		var mapa = {};
		$form.find('#tabla-articulos-requisicion tbody tr.item-requisicion-articulo').each(function () {
			var $tr = $(this);
			var aid = $tr.find('input[name="articulo_ids[]"]').val();
			var cant = parseFloat($tr.find('input[name="cantidades[]"], .cantidad-linea').val()) || 0;
			if (!aid || cant <= 0) {
				return;
			}
			var $sel = $tr.find('select[name="centrocostodestino_ids[]"]');
			if (!$sel.length) {
				return;
			}
			var id = parseInt($sel.val(), 10) || 0;
			if (id <= 0) {
				return;
			}
			if (!mapa[id]) {
				var texto = ($sel.find('option:selected').text() || '').trim();
				mapa[id] = {
					id: id,
					codigo: '',
					nombre: texto,
					etiqueta: texto || ('Centro de costo ' + id)
				};
			}
		});
		return Object.keys(mapa).map(function (k) {
			return mapa[k];
		});
	}

	function asegurarHiddenCcArbol($form, centrocostoId) {
		var $hidden = $form.find('input[name="centrocostodestino_arbol_id"]');
		if (!$hidden.length) {
			$hidden = $('<input type="hidden" name="centrocostodestino_arbol_id">');
			$form.append($hidden);
		}
		$hidden.val(centrocostoId > 0 ? centrocostoId : '');
	}

	function interceptarSubmitFormulario() {
		$(document).on('submit.reqCcArbolGrabacion', '#form-general', function (e) {
			var $form = $(this);
			if (!debePedirCcArbolAlGrabar()) {
				return;
			}
			if ($form.data('req-cc-arbol-ok')) {
				$form.removeData('req-cc-arbol-ok');
				return;
			}

			var centros = centrosCostoDistintosDesdeFormulario($form);
			if (centros.length <= 1) {
				if (centros.length === 1) {
					asegurarHiddenCcArbol($form, centros[0].id);
				}
				return;
			}

			e.preventDefault();
			e.stopImmediatePropagation();

			if (!window.RequisicionCentrocostoArbolModal) {
				alert('No se pudo abrir la selección de centro de costo.');
				return false;
			}

			window.RequisicionCentrocostoArbolModal.abrir({
				centrosCosto: centros,
				texto: 'La requisición tiene renglones con distintos centros de costo de destino. Elija con cuál enviar al árbol de aprobación.',
				onConfirm: function (centrocostoId) {
					asegurarHiddenCcArbol($form, centrocostoId);
					$form.data('req-cc-arbol-ok', 1);
					$form.trigger('submit');
				}
			});

			return false;
		});
	}

	$(function () {
		interceptarSubmitFormulario();
	});
})(jQuery);
