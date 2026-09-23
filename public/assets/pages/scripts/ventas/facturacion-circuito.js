/**
 * Filtro + autoasignación PV/tipo según circuito exportación vs local.
 * Fact admin / pedido / remito. No POS Ferli local ni gastro/estacionamiento AGG.
 */
(function (window, $) {
	'use strict';

	if (!$) {
		return;
	}

	var CODIGOS_AFIP_EXPORT = { 19: 1, 20: 1, 21: 1 };

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

	function esTipoExportFromData($opt) {
		var abrev = norm($opt.data('abreviatura') || $opt.attr('data-abreviatura'));
		var cod = codigoAfip($opt.data('codigo') || $opt.attr('data-codigo'));
		if (abrev === 'FAE') {
			return true;
		}
		if (CODIGOS_AFIP_EXPORT[cod]) {
			return true;
		}
		if ((abrev === 'NCE' || abrev === 'NDE') && cod > 0 && cod < 200) {
			return true;
		}
		return false;
	}

	function esPvExportFromData($opt) {
		var modo = norm($opt.data('modofacturacion') || $opt.attr('data-modofacturacion'));
		if (modo === 'E') {
			return true;
		}
		return esWsFex($opt.data('webservice') || $opt.attr('data-webservice'));
	}

	function esTipoExportItem(item) {
		var abrev = norm(item.abreviatura);
		var cod = codigoAfip(item.codigo);
		if (abrev === 'FAE') {
			return true;
		}
		if (CODIGOS_AFIP_EXPORT[cod]) {
			return true;
		}
		if ((abrev === 'NCE' || abrev === 'NDE') && cod > 0 && cod < 200) {
			return true;
		}
		return false;
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
			|| c.indexOf('FAE') === 0;
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

	function filtrarSelect($select, circuito, esExportFn) {
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
			var exp = esExportFn($o);
			var ok = circuito === 'exportacion' ? exp : !exp;
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
		filtrarSelect($tipoSelect, circuito, esTipoExportFromData);
		filtrarSelect($pvSelect, circuito, esPvExportFromData);

		if (circuito === 'exportacion') {
			if (ctx.preferTipoId && $tipoSelect.find('option[value="' + ctx.preferTipoId + '"]:not(:disabled)').length) {
				$tipoSelect.val(String(ctx.preferTipoId));
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
			var exp = esTipoExportItem(item);
			return circuito === 'exportacion' ? exp : !exp;
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
		esTipoExportItem: esTipoExportItem,
		esPvExportItem: esPvExportItem,
		documentoEsExport: documentoEsExport
	};
})(window, window.jQuery);
