/**
 * Líneas de artículos en requisición: F1 en consultas, Enter (= Tab) para validar y avanzar,
 * herencia de CC destino / moneda desde la primera línea, precio última compra Anita.
 */
(function ($) {
	'use strict';

	var SELECTOR_TABLA = '#tabla-articulos-requisicion';
	var SELECTOR_FILA = 'tr.item-requisicion-articulo';
	var URL_ULTIMA_COMPRA = null;
	var URL_CALCULAR_TOTALES = null;
	var reqTotalesTimer = null;

	function esPantallaRequisicionLineas() {
		return $(SELECTOR_TABLA).length && !$('#tabla-articulos-requisicion tbody .codigoarticulo').first().prop('readonly');
	}

	function esTeclaF1(e) {
		return e.key === 'F1' || e.code === 'F1' || e.keyCode === 112;
	}

	function urlUltimaCompra() {
		if (URL_ULTIMA_COMPRA) {
			return URL_ULTIMA_COMPRA;
		}
		var cfg = window.requisicionLineasConfig || {};
		if (cfg.urlPrecioUltimaCompra) {
			URL_ULTIMA_COMPRA = String(cfg.urlPrecioUltimaCompra);
			return URL_ULTIMA_COMPRA;
		}
		var base = (typeof carpetaBase !== 'undefined' && carpetaBase) ? String(carpetaBase).replace(/\/$/, '') : '';
		URL_ULTIMA_COMPRA = base + '/compras/requisicion/precio-ultima-compra-articulo';
		return URL_ULTIMA_COMPRA;
	}

	function normalizarDecimalLinea(val, decimales) {
		decimales = decimales == null ? 4 : decimales;
		var n = parseFloat(String(val == null ? '' : val).replace(',', '.'));
		if (Number.isNaN(n)) {
			return '';
		}
		return Number(n.toFixed(decimales));
	}

	function urlCalcularTotales() {
		if (URL_CALCULAR_TOTALES) {
			return URL_CALCULAR_TOTALES;
		}
		var cfg = window.requisicionLineasConfig || {};
		if (cfg.urlCalcularTotales) {
			URL_CALCULAR_TOTALES = String(cfg.urlCalcularTotales);
			return URL_CALCULAR_TOTALES;
		}
		var base = (typeof carpetaBase !== 'undefined' && carpetaBase) ? String(carpetaBase).replace(/\/$/, '') : '';
		URL_CALCULAR_TOTALES = base + '/compras/requisicion/calcular-totales';
		return URL_CALCULAR_TOTALES;
	}

	function reqFmtEsAr(v) {
		var n = Number(v);
		if (Number.isNaN(n)) {
			n = 0;
		}
		return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function reqActualizarPanelTotales(res) {
		if (!res || typeof res !== 'object') {
			return;
		}
		var abrev = res.moneda_abrev || '—';
		if (abrev === '') {
			abrev = '—';
		}
		$('#req-tot-moneda').text(abrev);
		$('#req-tot-importe').text(reqFmtEsAr(res.total));
	}

	function reqRecalcTotales() {
		if (!$('#req-panel-totales').length || !esPantallaRequisicionLineas()) {
			return;
		}
		var articulo_ids = [];
		var cantidades = [];
		var precios = [];
		var moneda_linea_ids = [];
		$filas().each(function () {
			var $tr = $(this);
			var aid = $tr.find('.articulo_id').val();
			var cant = parseFloat($tr.find('.cantidad-linea').val()) || 0;
			if (!aid || cant <= 0) {
				return;
			}
			articulo_ids.push(aid);
			cantidades.push(cant);
			precios.push(parseFloat($tr.find('.precio-linea').val()) || 0);
			moneda_linea_ids.push(parseInt($tr.find('select[name="moneda_linea_ids[]"]').val(), 10) || 1);
		});
		var token = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val() || '';
		$.post(urlCalcularTotales(), {
			_token: token,
			fecha: ($('#fecha').val() || '').substring(0, 10),
			articulo_ids: articulo_ids,
			cantidades: cantidades,
			precios: precios,
			moneda_linea_ids: moneda_linea_ids
		}).done(function (res) {
			reqActualizarPanelTotales(res);
		});
	}

	function reqScheduleTotales() {
		if (!$('#req-panel-totales').length || !esPantallaRequisicionLineas()) {
			return;
		}
		clearTimeout(reqTotalesTimer);
		reqTotalesTimer = setTimeout(reqRecalcTotales, 320);
	}

	window.reqLineasRecalcTotales = reqRecalcTotales;
	window.reqLineasScheduleTotales = reqScheduleTotales;

	function $tabla() {
		return $(SELECTOR_TABLA);
	}

	function $filas() {
		return $tabla().find('tbody ' + SELECTOR_FILA);
	}

	function $primeraFila() {
		return $filas().first();
	}

	function esPrimeraFila($row) {
		return $row.length && $row.is($primeraFila());
	}

	function patronCcDestino() {
		var $p = $primeraFila();
		if (!$p.length) {
			return $tabla().attr('data-requisicion-cc-destino-default') || '';
		}
		return $p.find('select[name="centrocostodestino_ids[]"]').val() || '';
	}

	function patronMoneda() {
		var $p = $primeraFila();
		if (!$p.length) {
			return $tabla().attr('data-requisicion-moneda-default') || '1';
		}
		return $p.find('select[name="moneda_linea_ids[]"]').val() || '1';
	}

	function sincronizarPatronesTabla() {
		var cc = patronCcDestino();
		var mon = patronMoneda();
		if (cc !== '') {
			$tabla().attr('data-requisicion-cc-destino-default', cc);
		}
		if (mon !== '') {
			$tabla().attr('data-requisicion-moneda-default', mon);
		}
	}

	function filaCcManual($row) {
		return $row.attr('data-req-cc-manual') === '1';
	}

	function filaMonedaManual($row) {
		return $row.attr('data-req-moneda-manual') === '1';
	}

	function marcarCcManual($row) {
		if (!esPrimeraFila($row)) {
			$row.attr('data-req-cc-manual', '1');
		}
	}

	function marcarMonedaManual($row) {
		if (!esPrimeraFila($row)) {
			$row.attr('data-req-moneda-manual', '1');
		}
	}

	function aplicarPatronCcDestino($row) {
		var cc = patronCcDestino();
		if (cc === '') {
			return;
		}
		var $sel = $row.find('select[name="centrocostodestino_ids[]"]');
		if ($sel.find('option[value="' + cc + '"]').length) {
			$sel.val(cc);
		}
	}

	function aplicarPatronMoneda($row) {
		var mon = patronMoneda();
		if (mon === '') {
			return;
		}
		var $sel = $row.find('select[name="moneda_linea_ids[]"]');
		if ($sel.find('option[value="' + mon + '"]').length) {
			$sel.val(mon);
		}
	}

	function propagarPatronCcDesdePrimera(valorAnterior) {
		var ccNuevo = patronCcDestino();
		$tabla().attr('data-requisicion-cc-destino-default', ccNuevo);
		$filas().each(function () {
			var $row = $(this);
			if (esPrimeraFila($row)) {
				return;
			}
			if (filaCcManual($row)) {
				return;
			}
			var $sel = $row.find('select[name="centrocostodestino_ids[]"]');
			var actual = $sel.val();
			if (valorAnterior === undefined || actual === valorAnterior || actual === '' || actual == null) {
				if ($sel.find('option[value="' + ccNuevo + '"]').length) {
					$sel.val(ccNuevo);
				}
			}
		});
	}

	function propagarPatronMonedaDesdePrimera(valorAnterior) {
		var monNuevo = patronMoneda();
		$tabla().attr('data-requisicion-moneda-default', monNuevo);
		$filas().each(function () {
			var $row = $(this);
			if (esPrimeraFila($row)) {
				return;
			}
			if (filaMonedaManual($row)) {
				return;
			}
			var $sel = $row.find('select[name="moneda_linea_ids[]"]');
			var actual = $sel.val();
			if (valorAnterior === undefined || actual === valorAnterior || actual === '' || actual == null) {
				if ($sel.find('option[value="' + monNuevo + '"]').length) {
					$sel.val(monNuevo);
				}
			}
		});
	}

	function enfocarInput($inp) {
		if (!$inp || !$inp.length || $inp.prop('readonly') || $inp.prop('disabled')) {
			return;
		}
		setTimeout(function () {
			$inp.trigger('focus');
			if ($inp[0] && typeof $inp[0].select === 'function' && $inp.is('input[type="text"], input[type="number"]')) {
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
			case 'precio':
				enfocarInput($row.find('.precio-linea').first());
				break;
			case 'moneda':
				enfocarInput($row.find('select[name="moneda_linea_ids[]"]').first());
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
		var $btn = $('#agrega_renglon_requisicion_articulo');
		if ($btn.length) {
			$btn.trigger('click');
			return;
		}
		var $last = $filas().last();
		enfocarCampoLinea($last, 'articulo');
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

	function resolverDatosUltimaCompra(datos, sku) {
		if (!datos || !sku) {
			return null;
		}
		if (datos[sku]) {
			return datos[sku];
		}
		var skuTrim = String(sku).trim();
		if (datos[skuTrim]) {
			return datos[skuTrim];
		}
		var keys = Object.keys(datos);
		if (keys.length === 1) {
			return datos[keys[0]];
		}
		return null;
	}

	function aplicarPrecioUltimaCompra($row, sku, callback) {
		sku = (sku || '').trim();
		if (!sku || !$row || !$row.length) {
			if (typeof callback === 'function') {
				callback(false);
			}
			return;
		}

		$.get(urlUltimaCompra(), { 'skus[]': sku })
			.done(function (resp) {
				var datos = (resp && resp.datos) ? resp.datos : {};
				var info = resolverDatosUltimaCompra(datos, sku);
				if (!info) {
					if (typeof callback === 'function') {
						callback(false);
					}
					return;
				}

				if (info.precio !== null && info.precio !== undefined && info.precio !== '') {
					$row.find('.precio-linea').val(normalizarDecimalLinea(info.precio, 4));
				}

				if (info.moneda_id) {
					var $mon = $row.find('select[name="moneda_linea_ids[]"]');
					var mid = String(info.moneda_id);
					if ($mon.find('option[value="' + mid + '"]').length) {
						if (esPrimeraFila($row) || !filaMonedaManual($row)) {
							$mon.val(mid);
							if (esPrimeraFila($row)) {
								propagarPatronMonedaDesdePrimera($mon.data('req-valor-anterior'));
								sincronizarPatronesTabla();
								propagarPatronMonedaDesdePrimera();
							}
						}
					}
				}

				if (typeof callback === 'function') {
					callback(true);
				}
			})
			.fail(function () {
				if (typeof callback === 'function') {
					callback(false);
				}
			});
	}

	function despuesDeArticuloCargado($row, dataArticulo) {
		if (!$row || !$row.length || !dataArticulo) {
			return;
		}
		if (!($row.find('.articulo_id').val() || '').trim()) {
			return;
		}
		var sku = String(dataArticulo.sku || $row.find('.codigoarticulo').val() || '').trim();
		if (!esPrimeraFila($row) && !filaCcManual($row)) {
			aplicarPatronCcDestino($row);
		}
		if (!esPrimeraFila($row) && !filaMonedaManual($row)) {
			aplicarPatronMoneda($row);
		}
		if (sku) {
			aplicarPrecioUltimaCompra($row, sku, function () {
				reqScheduleTotales();
			});
		}
	}

	window.reqLineasAplicarPatronFila = function ($row) {
		if (!$row || !$row.length) {
			return;
		}
		if (!esPrimeraFila($row)) {
			if (!filaCcManual($row)) {
				aplicarPatronCcDestino($row);
			}
			if (!filaMonedaManual($row)) {
				aplicarPatronMoneda($row);
			}
		}
	};

	window.reqLineasAplicarPrecioUltimaCompra = function ($row, sku, callback) {
		aplicarPrecioUltimaCompra($row, sku, callback);
	};

	function registrarCambiosPatron() {
		$(document)
			.off('change.reqPatronCc', SELECTOR_TABLA + ' ' + SELECTOR_FILA + ':first select[name="centrocostodestino_ids[]"]')
			.on('change.reqPatronCc', SELECTOR_TABLA + ' ' + SELECTOR_FILA + ':first select[name="centrocostodestino_ids[]"]', function () {
				var valorAnterior = $(this).data('req-valor-anterior');
				$(this).data('req-valor-anterior', $(this).val());
				propagarPatronCcDesdePrimera(valorAnterior);
			});

		$(document)
			.off('change.reqPatronCcManual', SELECTOR_TABLA + ' ' + SELECTOR_FILA + ':not(:first) select[name="centrocostodestino_ids[]"]')
			.on('change.reqPatronCcManual', SELECTOR_TABLA + ' ' + SELECTOR_FILA + ':not(:first) select[name="centrocostodestino_ids[]"]', function () {
				marcarCcManual($(this).closest(SELECTOR_FILA));
			});

		$(document)
			.off('change.reqPatronMon', SELECTOR_TABLA + ' ' + SELECTOR_FILA + ':first select[name="moneda_linea_ids[]"]')
			.on('change.reqPatronMon', SELECTOR_TABLA + ' ' + SELECTOR_FILA + ':first select[name="moneda_linea_ids[]"]', function () {
				var valorAnterior = $(this).data('req-valor-anterior');
				$(this).data('req-valor-anterior', $(this).val());
				propagarPatronMonedaDesdePrimera(valorAnterior);
				reqScheduleTotales();
			});

		$(document)
			.off('change.reqPatronMonManual', SELECTOR_TABLA + ' ' + SELECTOR_FILA + ':not(:first) select[name="moneda_linea_ids[]"]')
			.on('change.reqPatronMonManual', SELECTOR_TABLA + ' ' + SELECTOR_FILA + ':not(:first) select[name="moneda_linea_ids[]"]', function () {
				marcarMonedaManual($(this).closest(SELECTOR_FILA));
				reqScheduleTotales();
			});

		$(document)
			.off('input.reqTotales change.reqTotales', SELECTOR_TABLA + ' .cantidad-linea, ' + SELECTOR_TABLA + ' .precio-linea, ' + SELECTOR_TABLA + ' .articulo_id')
			.on('input.reqTotales change.reqTotales', SELECTOR_TABLA + ' .cantidad-linea, ' + SELECTOR_TABLA + ' .precio-linea, ' + SELECTOR_TABLA + ' .articulo_id', function () {
				reqScheduleTotales();
			});

		$(document)
			.off('blur.reqNormalizarDecimal', SELECTOR_TABLA + ' .cantidad-linea, ' + SELECTOR_TABLA + ' .precio-linea')
			.on('blur.reqNormalizarDecimal', SELECTOR_TABLA + ' .cantidad-linea, ' + SELECTOR_TABLA + ' .precio-linea', function () {
				var raw = $(this).val();
				if (raw === '' || raw == null) {
					return;
				}
				var normalizado = normalizarDecimalLinea(raw, 4);
				if (normalizado !== '') {
					$(this).val(normalizado);
				}
			});

		$(document)
			.off('change.reqTotalesMon', SELECTOR_TABLA + ' select[name="moneda_linea_ids[]"]')
			.on('change.reqTotalesMon', SELECTOR_TABLA + ' select[name="moneda_linea_ids[]"]', function () {
				reqScheduleTotales();
			});

		$(document)
			.off('change.reqTotalesFecha', '#fecha')
			.on('change.reqTotalesFecha', '#fecha', function () {
				reqScheduleTotales();
			});
	}

	function registrarEventoArticuloCargado() {
		$(document)
			.off('req:articulo-linea-cargado', SELECTOR_FILA)
			.on('req:articulo-linea-cargado', SELECTOR_FILA, function (_e, dataArticulo) {
				despuesDeArticuloCargado($(this), dataArticulo);
				reqScheduleTotales();
			});
	}

	function registrarAtajosF1() {
		document.addEventListener('keydown', function (e) {
			if (!esTeclaF1(e) || !esPantallaRequisicionLineas()) {
				return;
			}

			var target = e.target;
			if (!target || !target.closest('#form-general')) {
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

	function validarCodigoYAvanzar($input, $row, campoSiguiente, alValidarOk) {
		$input.one('req:codigo-validado.reqNav', function (_ev, ok) {
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
		reqScheduleTotales();
		return true;
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
			if (!esPantallaRequisicionLineas()) {
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
				enfocarCampoLinea($row, 'precio');
				return;
			}

			if ($target.hasClass('precio-linea')) {
				enfocarCampoLinea($row, 'moneda');
				return;
			}

			if ($target.is('select[name="moneda_linea_ids[]"]')) {
				$target.trigger('change');
				enfocarCampoLinea($row, 'ccdestino');
				return;
			}

			if ($target.is('select[name="centrocostodestino_ids[]"]')) {
				$target.trigger('change');
				enfocarCampoLinea($row, 'partidagasto');
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

	function initPatronesIniciales() {
		var $p = $primeraFila();
		if (!$p.length) {
			return;
		}
		var $cc = $p.find('select[name="centrocostodestino_ids[]"]');
		var $mon = $p.find('select[name="moneda_linea_ids[]"]');
		$cc.data('req-valor-anterior', $cc.val());
		$mon.data('req-valor-anterior', $mon.val());
		sincronizarPatronesTabla();
	}

	var ptrReqDetalleLineaRow = null;

	function reqSubRowArticulo($mainTr) {
		var $s = $mainTr.next('tr.item-requisicion-articulo-sub');
		return $s.length ? $s : $();
	}

	function reqRefreshDetalleLineaBadge($row) {
		var $ta = $row.find('.req-ta-detalle-linea');
		var t = ($ta.val() || '').trim();
		var $sub = reqSubRowArticulo($row);
		var $bd = $sub.find('.req-detalle-linea-badge');
		if ($sub.length) {
			$sub.toggleClass('d-none', !t.length);
		}
		if (!$bd.length) {
			return;
		}
		if (!t.length) {
			$bd.text('—').removeAttr('title');
			return;
		}
		$bd.text(t).attr('title', t);
	}

	window.reqRefreshDetalleLineaBadge = reqRefreshDetalleLineaBadge;

	function registrarDetalleLineaModal() {
		var $modal = $('#modalReqDetalleLinea');
		if ($modal.length && $modal.parent()[0] !== document.body) {
			$modal.appendTo('body');
		}

		$(document).on('click', '.req-abrir-detalle-linea', function () {
			ptrReqDetalleLineaRow = $(this).closest('tr.item-requisicion-articulo');
			var v = ptrReqDetalleLineaRow.find('.req-ta-detalle-linea').val() || '';
			$('#req_detalle_linea_editor').val(v);
			// data-toggle/data-target abre el modal; si no están, forzamos show.
			if (!$(this).attr('data-toggle')) {
				$modal.modal('show');
			}
		});

		$(document).on('click', '.req-detalle-linea-badge', function () {
			var $sub = $(this).closest('tr.item-requisicion-articulo-sub');
			var $row = $sub.prev('tr.item-requisicion-articulo');
			if (!$row.length) {
				return;
			}
			$row.find('.req-abrir-detalle-linea').trigger('click');
		});

		$modal.on('show.bs.modal', function () {
			var ro = !$('#req_detalle_linea_guardar').length;
			$('#req_detalle_linea_editor').prop('readonly', ro);
			if (ptrReqDetalleLineaRow && ptrReqDetalleLineaRow.length) {
				var v = ptrReqDetalleLineaRow.find('.req-ta-detalle-linea').val() || '';
				$('#req_detalle_linea_editor').val(v);
			}
		});

		$modal.on('shown.bs.modal', function () {
			$('#req_detalle_linea_editor').trigger('focus');
		});

		$(document).on('click', '#req_detalle_linea_guardar', function () {
			if (!ptrReqDetalleLineaRow || !ptrReqDetalleLineaRow.length) {
				return;
			}
			ptrReqDetalleLineaRow.find('.req-ta-detalle-linea').val($('#req_detalle_linea_editor').val() || '');
			reqRefreshDetalleLineaBadge(ptrReqDetalleLineaRow);
			$modal.modal('hide');
		});
	}

	function initDetalleLineaBadges() {
		$filas().each(function () {
			reqRefreshDetalleLineaBadge($(this));
		});
	}

	function initRequisicionLineas() {
		if (!$(SELECTOR_TABLA).length) {
			return;
		}
		registrarCambiosPatron();
		registrarEventoArticuloCargado();
		registrarAtajosF1();
		registrarEnterLineas();
		registrarDetalleLineaModal();
		initPatronesIniciales();
		initDetalleLineaBadges();
		if (esPantallaRequisicionLineas()) {
			reqScheduleTotales();
		}
	}

	$(initRequisicionLineas);
}(jQuery));
