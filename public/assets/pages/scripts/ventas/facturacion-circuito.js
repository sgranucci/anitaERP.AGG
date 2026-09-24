/**
 * Filtro + autoasignación PV/tipo según circuito exportación vs local.
 * Fact admin / pedido / remito. No POS Ferli local ni gastro/estacionamiento AGG.
 *
 * Export: PV modo E/WSFEX + FAE/FAF (factura) + NCE/NDE/NCD (NC; NCD también local Ferli).
 */
(function (window, $) {
	'use strict';

	if (!$) {
		return;
	}

	var CODIGOS_AFIP_EXPORT = { 19: 1, 20: 1, 21: 1 };
	var ABREV_EXPORT_FACTURA = { FAE: 1, FAF: 1 };
	var ABREV_EXPORT_NC_ND = { NCE: 1, NDE: 1 };
	var ABREV_NC_COMPATIBLE_EXPORT = { NCD: 1 };

	function norm(s) {
		return String(s || '').trim().toUpperCase();
	}

	function codigoAfip(codigo) {
		var d = String(codigo || '').replace(/\D+/g, '');
		return d ? parseInt(d, 10) : 0;
	}

	function esWsFex(ws) {
		var w = String(ws || '').toLowerCase().trim();
		return w === 'wsfex_v1' || w === 'wsfex' || w === 'wsfexv1';
	}

	/** Exclusivo export (se oculta en circuito local). */
	function esTipoExportExclusivoFromData($opt) {
		var abrev = norm($opt.data('abreviatura') || $opt.attr('data-abreviatura'));
		var cod = codigoAfip($opt.data('codigo') || $opt.attr('data-codigo'));
		if (ABREV_EXPORT_FACTURA[abrev]) {
			return true;
		}
		if (CODIGOS_AFIP_EXPORT[cod]) {
			return true;
		}
		if (ABREV_EXPORT_NC_ND[abrev] && cod > 0 && cod < 200) {
			return true;
		}
		return false;
	}

	function esTipoPermitidoExportFromData($opt) {
		if (esTipoExportExclusivoFromData($opt)) {
			return true;
		}
		var abrev = norm($opt.data('abreviatura') || $opt.attr('data-abreviatura'));
		return !!ABREV_NC_COMPATIBLE_EXPORT[abrev];
	}

	function esPvExportFromData($opt) {
		var modo = norm($opt.data('modofacturacion') || $opt.attr('data-modofacturacion'));
		if (modo === 'E') {
			return true;
		}
		return esWsFex($opt.data('webservice') || $opt.attr('data-webservice'));
	}

	function esTipoExportExclusivoItem(item) {
		var abrev = norm(item.abreviatura);
		var cod = codigoAfip(item.codigo);
		if (ABREV_EXPORT_FACTURA[abrev]) {
			return true;
		}
		if (CODIGOS_AFIP_EXPORT[cod]) {
			return true;
		}
		if (ABREV_EXPORT_NC_ND[abrev] && cod > 0 && cod < 200) {
			return true;
		}
		return false;
	}

	function esTipoPermitidoExportItem(item) {
		if (esTipoExportExclusivoItem(item)) {
			return true;
		}
		return !!ABREV_NC_COMPATIBLE_EXPORT[norm(item.abreviatura)];
	}

	function esPvExportItem(item) {
		if (norm(item.modofacturacion) === 'E') {
			return true;
		}
		return esWsFex(item.webservice);
	}

	function documentoEsExport(codigo) {
		var c = norm(codigo);
		if (!c) {
			return false;
		}
		return c.indexOf('PEX') === 0 || c.indexOf('PEX') >= 0
			|| c.indexOf('REX') === 0 || c.indexOf('-REX') >= 0
			|| c.indexOf('FAE') === 0
			|| c.indexOf('FAF') === 0;
	}

	function resolverCircuito(ctx) {
		ctx = ctx || {};
		if (documentoEsExport(ctx.codigoDocumento)) {
			return 'exportacion';
		}
		if (norm(ctx.letraCliente) === 'E') {
			return 'exportacion';
		}
		if (ctx.tipoForceExport) {
			return 'exportacion';
		}
		return 'local';
	}

	function filtrarSelect($select, circuito, esOkFn) {
		if (!$select || !$select.length) {
			return;
		}
		var valActual = $select.val();
		var primeroVisible = '';
		$select.find('option').each(function () {
			var $o = $(this);
			var v = $o.attr('value');
			if (!v) {
				$o.prop('disabled', false).show();
				return;
			}
			var ok = esOkFn($o);
			$o.prop('disabled', !ok);
			if (ok) {
				$o.show();
				if (!primeroVisible) {
					primeroVisible = v;
				}
			} else {
				$o.hide();
			}
		});
		var $sel = $select.find('option:selected');
		if ($sel.length && $sel.prop('disabled')) {
			$select.val(primeroVisible || '');
		} else if (!valActual && primeroVisible) {
			$select.val(primeroVisible);
		}
		$select.trigger('change.select2');
	}

	/**
	 * @param {object} ctx { letraCliente, codigoDocumento, preferPvId, preferTipoId }
	 */
	function aplicar($tipoSelect, $pvSelect, ctx) {
		ctx = ctx || {};
		var circuito = resolverCircuito(ctx);
		filtrarSelect($tipoSelect, circuito, function ($o) {
			return circuito === 'exportacion'
				? esTipoPermitidoExportFromData($o)
				: !esTipoExportExclusivoFromData($o);
		});
		filtrarSelect($pvSelect, circuito, function ($o) {
			var exp = esPvExportFromData($o);
			return circuito === 'exportacion' ? exp : !exp;
		});

		if (circuito === 'exportacion') {
			var tipoOk = ctx.preferTipoId
				&& $tipoSelect.find('option[value="' + ctx.preferTipoId + '"]:not(:disabled)').length;
			if (tipoOk) {
				$tipoSelect.val(String(ctx.preferTipoId));
			} else {
				// Ferli FAF / Interforming FAE antes que NCD (dual)
				var preferAbrev = ['FAF', 'FAE', 'NCE', 'NDE', 'NCD'];
				var elegido = '';
				for (var i = 0; i < preferAbrev.length && !elegido; i++) {
					$tipoSelect.find('option:not(:disabled)').each(function () {
						if (elegido) {
							return;
						}
						if (norm($(this).data('abreviatura') || $(this).attr('data-abreviatura')) === preferAbrev[i]) {
							elegido = $(this).attr('value');
						}
					});
				}
				if (elegido) {
					$tipoSelect.val(elegido);
				}
			}
			if (ctx.preferPvId && $pvSelect.find('option[value="' + ctx.preferPvId + '"]:not(:disabled)').length) {
				$pvSelect.val(String(ctx.preferPvId));
			}
			$tipoSelect.trigger('change');
			$pvSelect.trigger('change');
		}

		return circuito;
	}

	/** Filtra arrays usados al armar options del modal (antes del append). */
	function filtrarListas(selTipos, selPvs, ctx) {
		var circuito = resolverCircuito(ctx);
		var tipos = (selTipos || []).filter(function (item) {
			return circuito === 'exportacion'
				? esTipoPermitidoExportItem(item)
				: !esTipoExportExclusivoItem(item);
		});
		var pvs = (selPvs || []).filter(function (item) {
			var exp = esPvExportItem(item);
			return circuito === 'exportacion' ? exp : !exp;
		});
		return { circuito: circuito, tipos: tipos, puntoventas: pvs };
	}

	function attrsTipoOption(item) {
		return ' data-abreviatura="' + (item.abreviatura || '') + '"'
			+ ' data-codigo="' + (item.codigo || '') + '"';
	}

	function attrsPvOption(item) {
		return ' data-modofacturacion="' + (item.modofacturacion || '') + '"'
			+ ' data-webservice="' + (item.webservice || '') + '"';
	}

	window.FacturacionCircuitoAfip = {
		resolverCircuito: resolverCircuito,
		aplicar: aplicar,
		filtrarListas: filtrarListas,
		attrsTipoOption: attrsTipoOption,
		attrsPvOption: attrsPvOption,
		esTipoExportItem: esTipoExportExclusivoItem,
		esTipoPermitidoExportItem: esTipoPermitidoExportItem,
		esPvExportItem: esPvExportItem,
		documentoEsExport: documentoEsExport
	};
})(window, window.jQuery);
