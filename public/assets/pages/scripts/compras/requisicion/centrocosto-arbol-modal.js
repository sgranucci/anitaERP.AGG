/**
 * Modal compartido para elegir el CC de destino del circuito de árbol
 * (alta/actualización, confirmación de provisorio y retome desde EN COMPRAS).
 */
(function ($) {
	'use strict';

	var pending = null;

	function $modal() {
		return $('#modalRequisicionCentrocostoRetomeArbol');
	}

	function $lista() {
		return $('#requisicionCentrocostoRetomeArbolLista');
	}

	function $error() {
		return $('#requisicionCentrocostoRetomeArbolError');
	}

	function $texto() {
		return $('#requisicionCentrocostoRetomeArbolTexto');
	}

	function limpiarError() {
		$error().addClass('d-none').text('');
	}

	function mostrarError(msg) {
		$error().removeClass('d-none').text(msg || 'Seleccione un centro de costo de destino para continuar.');
	}

	function etiquetaCentrocosto(cc) {
		if (!cc) {
			return '';
		}
		return cc.etiqueta || ((cc.codigo || '') + ' ' + (cc.nombre || '')).trim() || ('Centro de costo ' + cc.id);
	}

	function renderCentrosCosto(centrosCosto) {
		var html = '<div class="list-group">';
		(centrosCosto || []).forEach(function (cc, idx) {
			html += '<label class="list-group-item list-group-item-action mb-0">';
			html += '<input type="radio" name="centrocosto_retome_arbol" class="mr-2" value="' + cc.id + '"' + (idx === 0 ? ' checked' : '') + '>';
			html += '<strong>' + $('<div>').text(etiquetaCentrocosto(cc)).html() + '</strong>';
			html += '</label>';
		});
		html += '</div>';
		$lista().html(html);
	}

	function cerrarPendiente(cancelado) {
		var ctx = pending;
		pending = null;
		if (cancelado && ctx && typeof ctx.onCancel === 'function') {
			ctx.onCancel();
		}
	}

	window.RequisicionCentrocostoArbolModal = {
		etiquetaCentrocosto: etiquetaCentrocosto,
		abrir: function (opts) {
			if (!$modal().length) {
				if (opts && typeof opts.onCancel === 'function') {
					opts.onCancel();
				}
				alert('No se pudo abrir la selección de centro de costo.');
				return;
			}
			pending = opts || {};
			$texto().text(pending.texto || 'La requisición tiene renglones con distintos centros de costo de destino. Elija con cuál continuar el árbol de aprobación.');
			renderCentrosCosto(pending.centrosCosto || []);
			limpiarError();
			$modal().modal('show');
		},
		estaAbierto: function () {
			return pending !== null;
		}
	};

	$(function () {
		$('#requisicionCentrocostoRetomeArbolConfirmar').off('click.reqCcArbolModal').on('click.reqCcArbolModal', function () {
			if (!pending) {
				return;
			}
			var seleccionado = parseInt($lista().find('input[name="centrocosto_retome_arbol"]:checked').val(), 10) || 0;
			if (seleccionado <= 0) {
				mostrarError('Seleccione un centro de costo de destino para continuar.');
				return;
			}
			limpiarError();
			var onConfirm = pending.onConfirm;
			var centrosCosto = pending.centrosCosto || [];
			pending = null;
			$modal().modal('hide');
			if (typeof onConfirm === 'function') {
				onConfirm(seleccionado, centrosCosto);
			}
		});

		$modal().off('hidden.bs.modal.reqCcArbolModal').on('hidden.bs.modal.reqCcArbolModal', function () {
			if (pending) {
				cerrarPendiente(true);
			}
		});
	});
})(jQuery);
