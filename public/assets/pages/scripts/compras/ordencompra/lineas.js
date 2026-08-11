/**
 * Líneas de artículos en orden de compra: F1 en consultas, Enter (= Tab) para validar y avanzar.
 * Misma UX de ingreso que requisición (lineas.js), adaptada al orden de columnas de OC.
 */
(function ($) {
	'use strict';

	var SELECTOR_TABLA = '#tabla-articulos-ordencompra';
	var SELECTOR_FILA = 'tr.item-ordencompra-articulo';

	function esPantallaOcLineas() {
		return $(SELECTOR_TABLA).length && !$(SELECTOR_TABLA + ' tbody .codigoarticulo').first().prop('readonly');
	}

	function esTeclaF1(e) {
		return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
	}

	function $filas() {
		return $(SELECTOR_TABLA + ' tbody ' + SELECTOR_FILA);
	}

	function enfocarInput($inp) {
		if (!$inp || !$inp.length || $inp.prop('readonly') || $inp.prop('disabled')) {
			return;
		}
		setTimeout(function () {
			$inp.trigger('focus');
			if ($inp[0] && typeof $inp[0].select === 'function' && $inp.is('input[type="text"], input[type="number"], input[type="date"]')) {
				$inp[0].select();
			}
		}, 0);
	}

	function enfocarCampoLinea($row, nombreCampo) {
		if (!$row || !$row.length) {
			return;
		}
		switch (nombreCampo) {
			case 'articulo':
				enfocarInput($row.find('.codigoarticulo').first());
				break;
			case 'cantidad':
				enfocarInput($row.find('.cantidad-linea').first());
				break;
			case 'pesounitario':
				enfocarInput($row.find('.oc-peso-unitario').first());
				break;
			case 'precio':
				enfocarInput($row.find('.precio-linea').first());
				break;
			case 'moneda':
				enfocarInput($row.find('select[name="moneda_linea_ids[]"]').first());
				break;
			case 'cotizacion':
				enfocarInput($row.find('.oc-cotizacion-linea').first());
				break;
			case 'fechaentrega':
				enfocarInput($row.find('input[name="fechaentrega_articulos[]"]').first());
				break;
			case 'ccdestino':
				enfocarInput($row.find('select[name="centrocostodestino_ids[]"]').first());
				break;
			case 'partidagasto':
				enfocarInput($row.find('.codigopartidagasto').first());
				break;
			case 'capex':
				enfocarInput($row.find('.codigocapex').first());
				break;
			default:
				break;
		}
	}

	function agregarRenglonYEnfocarArticulo() {
		var $btn = $('#agrega_renglon_ordencompra_articulo');
		if ($btn.length) {
			$btn.trigger('click');
			return;
		}
		enfocarCampoLinea($filas().last(), 'articulo');
	}

	function modalAbierto(selector) {
		var $m = $(selector);
		return $m.length && $m.hasClass('show');
	}

	function abrirConsultaDesdeInput($input, selectorBtn) {
		var $row = $input.closest(SELECTOR_FILA);
		var $btn = $row.find(selectorBtn).first();
		if ($btn.length) {
			$btn.trigger('click');
		}
	}

	function validarCodigoYAvanzar($input, $row, campoSiguiente, alValidarOk) {
		$input.one('req:codigo-validado.ocNav', function (_ev, ok) {
			if (!ok) {
				enfocarInput($input);
				return;
			}
			if (typeof alValidarOk === 'function') {
				alValidarOk();
				return;
			}
			enfocarCampoLinea($row, campoSiguiente);
		});
		$input.trigger('blur');
	}

	function validarCantidadLinea($input, $row) {
		if (!$input || !$input.length || !$row || !$row.length) {
			return false;
		}

		var articuloId = ($row.find('.articulo_id').val() || '').trim();
		if (!articuloId) {
			alert('Indique un artículo válido antes de cargar la cantidad.');
			enfocarCampoLinea($row, 'articulo');
			return false;
		}

		var raw = ($input.val() || '').toString().trim();
		if (raw === '') {
			alert('Indique la cantidad del ítem.');
			enfocarInput($input);
			return false;
		}

		var cant = parseFloat(raw.replace(',', '.'));
		if (Number.isNaN(cant) || cant <= 0) {
			alert('La cantidad debe ser mayor a cero.');
			enfocarInput($input);
			return false;
		}

		$input.val(cant);
		return true;
	}

	function registrarAtajosF1() {
		document.addEventListener('keydown', function (e) {
			if (!esTeclaF1(e) || !esPantallaOcLineas()) {
				return;
			}

			var target = e.target;
			if (!target || !target.closest('#form-ordencompra-general')) {
				return;
			}
			if (target.readOnly || target.disabled) {
				return;
			}

			if (target.classList.contains('codigoarticulo') && target.closest(SELECTOR_TABLA)) {
				if (modalAbierto('#consultaarticuloModal')) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				abrirConsultaDesdeInput($(target), '.consultaarticulo');
				return;
			}

			if (target.classList.contains('codigopartidagasto') && target.closest(SELECTOR_TABLA)) {
				if (modalAbierto('#consultapartidagastoModal')) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				abrirConsultaDesdeInput($(target), '.consultapartidagasto');
				return;
			}

			if (target.classList.contains('codigocapex') && target.closest(SELECTOR_TABLA)) {
				if (modalAbierto('#consultacapexModal')) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				abrirConsultaDesdeInput($(target), '.consultacapex');
			}
		}, true);
	}

	function registrarEnterLineas() {
		var tbody = document.querySelector(SELECTOR_TABLA + ' tbody');
		if (!tbody) {
			return;
		}

		tbody.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' && e.which !== 13) {
				return;
			}
			if (!esPantallaOcLineas()) {
				return;
			}

			var target = e.target;
			if (!target || target.tagName === 'TEXTAREA') {
				return;
			}

			var $target = $(target);
			var $row = $target.closest(SELECTOR_FILA);
			if (!$row.length) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();

			if ($target.hasClass('codigoarticulo')) {
				$target.trigger('change');
				return;
			}

			if ($target.hasClass('cantidad-linea')) {
				if (!validarCantidadLinea($target, $row)) {
					return;
				}
				if (($(SELECTOR_TABLA).attr('data-oc-mostrar-peso') || '0') === '1'
					&& $row.find('.oc-peso-unitario').length
					&& !$row.find('.oc-peso-unitario').prop('readonly')) {
					enfocarCampoLinea($row, 'pesounitario');
				} else {
					enfocarCampoLinea($row, 'precio');
				}
				return;
			}

			if ($target.hasClass('oc-peso-unitario')) {
				enfocarCampoLinea($row, 'precio');
				return;
			}

			if ($target.hasClass('precio-linea')) {
				enfocarCampoLinea($row, 'moneda');
				return;
			}

			if ($target.is('select[name="moneda_linea_ids[]"]')) {
				$target.trigger('change');
				enfocarCampoLinea($row, 'cotizacion');
				return;
			}

			if ($target.hasClass('oc-cotizacion-linea')) {
				enfocarCampoLinea($row, 'fechaentrega');
				return;
			}

			if ($target.is('input[name="fechaentrega_articulos[]"]')) {
				enfocarCampoLinea($row, 'ccdestino');
				return;
			}

			if ($target.is('select[name="centrocostodestino_ids[]"]')) {
				$target.trigger('change');
				if (($(SELECTOR_TABLA).attr('data-oc-pedir-partida-capex') || '1') === '1') {
					enfocarCampoLinea($row, 'partidagasto');
				} else {
					agregarRenglonYEnfocarArticulo();
				}
				return;
			}

			if ($target.hasClass('codigopartidagasto')) {
				validarCodigoYAvanzar($target, $row, 'capex');
				return;
			}

			if ($target.hasClass('codigocapex')) {
				validarCodigoYAvanzar($target, $row, null, function () {
					agregarRenglonYEnfocarArticulo();
				});
			}
		}, true);
	}

	function initOcLineas() {
		if (!$(SELECTOR_TABLA).length) {
			return;
		}
		registrarAtajosF1();
		registrarEnterLineas();
	}

	$(initOcLineas);
}(jQuery));
