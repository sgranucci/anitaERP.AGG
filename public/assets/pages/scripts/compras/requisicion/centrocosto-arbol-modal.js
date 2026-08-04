/**
 * Modal compartido para elegir el CC de destino del circuito de árbol
 * (alta/actualización, confirmación de provisorio y retome desde EN COMPRAS).
 * Permite opcionalmente un CC adicional vía consulta (F1 / código / modal).
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

	function $extraCampo() {
		return $('#tm_centrocosto_arbol_extra');
	}

	function $extraId() {
		return $('#centrocosto_arbol_extra_id');
	}

	function $extraRadio() {
		return $('#centrocosto_retome_arbol_extra');
	}

	function $extraRadioWrap() {
		return $('#requisicionCentrocostoRetomeArbolExtraRadioWrap');
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

	function limpiarCampoExtra(opts) {
		opts = opts || {};
		var preservarRadioLista = !!opts.preservarRadioLista;
		var checkedVal = preservarRadioLista
			? ($lista().find('input[name="centrocosto_retome_arbol"]:checked').val() || null)
			: null;

		if (typeof window.limpiarCentrocostoCampo === 'function') {
			window.limpiarCentrocostoCampo('centrocosto_arbol_extra_id');
		} else {
			$extraId().val('').trigger('change');
			$extraCampo().find('.codigocentrocosto').val('');
			$extraCampo().find('.descripcioncentrocosto').val('');
		}

		// sincronizarExtraDesdeCampo ya corrió por el change; restaurar radio de lista si aplica
		if (preservarRadioLista && checkedVal) {
			$lista().find('input[name="centrocosto_retome_arbol"]').prop('checked', false);
			$lista().find('input[name="centrocosto_retome_arbol"][value="' + checkedVal + '"]').prop('checked', true);
			$extraRadio().prop('checked', false);
			actualizarEstiloRadiosLista();
		}
	}

	function sincronizarExtraDesdeCampo() {
		var id = parseInt($extraId().val(), 10) || 0;
		var codigo = ($extraCampo().find('.codigocentrocosto').val() || '').trim();
		var nombre = ($extraCampo().find('.descripcioncentrocosto').val() || '').trim();
		var $radio = $extraRadio();
		var $wrap = $extraRadioWrap();

		if (id > 0) {
			var etiqueta = (codigo + ' ' + nombre).trim() || ('Centro de costo ' + id);
			$radio.val(String(id));
			$('#requisicionCentrocostoRetomeArbolExtraRadioLabel').text('Usar adicional: ' + etiqueta);
			$wrap.removeClass('d-none');
			$lista().find('input[name="centrocosto_retome_arbol"]').prop('checked', false);
			$radio.prop('checked', true);
			$('#requisicionCentrocostoRetomeArbolExtraCard').removeClass('card-secondary').addClass('card-success');
		} else {
			$radio.val('').prop('checked', false);
			$wrap.addClass('d-none');
			$('#requisicionCentrocostoRetomeArbolExtraRadioLabel').text('Usar el centro de costo adicional');
			$('#requisicionCentrocostoRetomeArbolExtraCard').removeClass('card-success').addClass('card-secondary');
			if (!$lista().find('input[name="centrocosto_retome_arbol"]:checked').length) {
				$lista().find('input[name="centrocosto_retome_arbol"]').first().prop('checked', true);
			}
		}
		actualizarEstiloRadiosLista();
	}

	function actualizarEstiloRadiosLista() {
		$lista().find('label.list-group-item').each(function () {
			var $lab = $(this);
			if ($lab.find('input[name="centrocosto_retome_arbol"]').is(':checked')) {
				$lab.addClass('active-cc');
			} else {
				$lab.removeClass('active-cc');
			}
		});
	}

	function renderCentrosCosto(centrosCosto) {
		var html = '<div class="list-group list-group-flush mb-0">';
		(centrosCosto || []).forEach(function (cc, idx) {
			html += '<label class="list-group-item list-group-item-action mb-0 d-flex align-items-start">';
			html += '<input type="radio" name="centrocosto_retome_arbol" class="mt-1 mr-2" value="' + cc.id + '"' + (idx === 0 ? ' checked' : '') + '>';
			html += '<span><strong>' + $('<div>').text(etiquetaCentrocosto(cc)).html() + '</strong>';
			if (cc.codigo || cc.nombre) {
				html += '<br><small class="text-muted">De los renglones de la requisición</small>';
			}
			html += '</span></label>';
		});
		html += '</div>';
		$lista().html(html);
		actualizarEstiloRadiosLista();
	}

	function seleccionadoActual() {
		// El CC adicional tiene prioridad si está cargado (contrato del modal).
		var extraId = parseInt($extraId().val(), 10) || 0;
		if (extraId > 0) {
			return extraId;
		}
		var desdeExtraRadio = parseInt($extraRadio().val(), 10) || 0;
		if (desdeExtraRadio > 0 && $extraRadio().is(':checked')) {
			return desdeExtraRadio;
		}
		return parseInt($lista().find('input[name="centrocosto_retome_arbol"]:checked').val(), 10) || 0;
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
			limpiarCampoExtra();
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
			var seleccionado = seleccionadoActual();
			if (seleccionado <= 0) {
				mostrarError('Seleccione un centro de costo de destino para continuar.');
				return;
			}
			limpiarError();
			var onConfirm = pending.onConfirm;
			var centrosCosto = pending.centrosCosto || [];
			var codigoExtra = ($extraCampo().find('.codigocentrocosto').val() || '').trim();
			var nombreExtra = ($extraCampo().find('.descripcioncentrocosto').val() || '').trim();
			if (parseInt($extraId().val(), 10) === seleccionado && seleccionado > 0) {
				var ya = centrosCosto.some(function (cc) {
					return parseInt(cc.id, 10) === seleccionado;
				});
				if (!ya) {
					centrosCosto = centrosCosto.concat([{
						id: seleccionado,
						codigo: codigoExtra,
						nombre: nombreExtra,
						etiqueta: (codigoExtra + ' ' + nombreExtra).trim() || ('Centro de costo ' + seleccionado)
					}]);
				}
			}
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

		$modal().off('shown.bs.modal.reqCcArbolModal').on('shown.bs.modal.reqCcArbolModal', function () {
			$lista().find('input[name="centrocosto_retome_arbol"]:checked').focus();
		});

		$(document)
			.off('change.reqCcArbolLista', '#requisicionCentrocostoRetomeArbolLista input[name="centrocosto_retome_arbol"]')
			.on('change.reqCcArbolLista', '#requisicionCentrocostoRetomeArbolLista input[name="centrocosto_retome_arbol"]', function () {
				if ($(this).is(':checked')) {
					limpiarCampoExtra({ preservarRadioLista: true });
				}
			});

		$(document)
			.off('change.reqCcArbolExtra', '#centrocosto_arbol_extra_id')
			.on('change.reqCcArbolExtra', '#centrocosto_arbol_extra_id', function () {
				sincronizarExtraDesdeCampo();
			});

		$('#requisicionCentrocostoRetomeArbolExtraLimpiar')
			.off('click.reqCcArbolExtra')
			.on('click.reqCcArbolExtra', function (e) {
				e.preventDefault();
				limpiarCampoExtra();
			});

		$extraRadio()
			.off('change.reqCcArbolExtraRadio')
			.on('change.reqCcArbolExtraRadio', function () {
				if ($(this).is(':checked')) {
					actualizarEstiloRadiosLista();
				}
			});
	});
})(jQuery);
