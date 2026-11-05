/**
 * Centro de costo habilitado en la cuenta de compras/gastos del artículo.
 * Lo usan requisición, orden de compra y el wizard de OC.
 */
(function (window, $) {
	'use strict';

	function url(articuloId) {
		var cfg = window.requisicionLineasConfig || {};
		if (cfg.urlCuentaCompraGasto) {
			return String(cfg.urlCuentaCompraGasto).replace('__ID__', String(articuloId));
		}
		var base = (typeof window.carpetaBase !== 'undefined' && window.carpetaBase)
			? String(window.carpetaBase).replace(/\/$/, '')
			: '';
		return base + '/stock/articulo/' + articuloId + '/cuenta-compra-gasto';
	}

	function idsPermitidos(payload) {
		var raw = payload && payload.centrocosto_ids;
		if (!raw || !raw.length) {
			return [];
		}
		var out = [];
		for (var i = 0; i < raw.length; i++) {
			var n = parseInt(raw[i], 10);
			if (n > 0 && out.indexOf(n) < 0) {
				out.push(n);
			}
		}
		return out;
	}

	function restringe(payload) {
		return !!(payload && (payload.restringe_centrocosto === true || payload.restringe_centrocosto === 1 || payload.restringe_centrocosto === '1'));
	}

	function permitido(payload, ccId) {
		if (!restringe(payload)) {
			return true;
		}
		var id = parseInt(ccId, 10) || 0;
		return idsPermitidos(payload).indexOf(id) >= 0;
	}

	function textoCuenta(payload) {
		var codigo = payload && payload.codigo ? String(payload.codigo).trim() : '';
		var nombre = payload && payload.nombre ? String(payload.nombre).trim() : '';
		if (codigo && nombre) {
			return codigo + ' ' + nombre;
		}
		return codigo || nombre || 'la cuenta del artículo';
	}

	function mensaje(payload, sku, ccTexto) {
		var art = sku ? ('El artículo ' + sku) : 'El artículo';
		var cc = ccTexto ? String(ccTexto).trim() : 'elegido';
		return art + ' no se puede usar con el centro de costo ' + cc
			+ '. La cuenta ' + textoCuenta(payload) + ' no tiene habilitado ese centro de costo. '
			+ 'Habilítelo en la cuenta o elija otro artículo.';
	}

	function consultar(articuloId, empresaId) {
		return $.get(url(articuloId), { empresa_id: empresaId });
	}

	window.ArticuloCentrocostoCuenta = {
		url: url,
		idsPermitidos: idsPermitidos,
		restringe: restringe,
		permitido: permitido,
		mensaje: mensaje,
		consultar: consultar
	};
}(window, jQuery));
