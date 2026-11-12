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
		// Canónico: WSFEX o modo E (export). No usar E solo como “electrónica” (AGG ya migró a C).
		if (esWsFex($opt.data('webservice') || $opt.attr('data-webservice'))) {
			return true;
		}
		return norm($opt.data('modofacturacion') || $opt.attr('data-modofacturacion')) === 'E';
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
		if (esWsFex(item.webservice)) {
			return true;
		}
		return norm(item.modofacturacion) === 'E';
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

	function tipoActualEsExport(ctx) {
		ctx = ctx || {};
		if (ctx.ignorarTipoActual) {
			return false;
		}
		var abrev = ctx.tipoAbreviatura;
		var codigo = ctx.tipoCodigo;
		if (abrev === undefined && codigo === undefined) {
			var $tipo = $('#tipotransaccion_id');
			if ($tipo.length) {
				abrev = $tipo.attr('data-abreviatura');
				codigo = $tipo.attr('data-codigo');
			}
		}
		return esTipoExportExclusivoItem({ abreviatura: abrev, codigo: codigo });
	}

	function resolverCircuito(ctx) {
		ctx = ctx || {};
		if (documentoEsExport(ctx.codigoDocumento)) {
			return 'exportacion';
		}
		if (norm(ctx.letraCliente) === 'E') {
			return 'exportacion';
		}
		if (ctx.tipoForceExport || tipoActualEsExport(ctx)) {
			return 'exportacion';
		}
		return 'local';
	}

	function formularioPermiteElegirExport() {
		var node = document.querySelector('#datosfactura');
		return !!(node && node.dataset && node.dataset.circuitoElegirExport === '1');
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

	function catalogoTiposPagina() {
		var node = document.querySelector('#datosfactura');
		if (!node || !node.dataset || node.dataset.tipotransaccion === undefined || node.dataset.tipotransaccion === '') {
			return [];
		}
		try {
			var raw = JSON.parse(node.dataset.tipotransaccion || '[]');
			return Array.isArray(raw) ? raw : Object.keys(raw || {}).map(function (k) { return raw[k]; });
		} catch (e) {
			return [];
		}
	}

	function tipoPermitidoEnCircuito(item, circuito) {
		return circuito === 'exportacion'
			? esTipoPermitidoExportItem(item)
			: !esTipoExportExclusivoItem(item);
	}

	function idsPermitidosPagina() {
		var ctx = {
			letraCliente: ($('#letra_cliente_factura').val() || window.facturacionLetraCliente || ''),
			codigoDocumento: (window.facturacionCodigoDocumento || ''),
			ignorarTipoActual: true
		};
		var circuito = resolverCircuito(ctx);
		var clienteExport = norm(ctx.letraCliente) === 'E' || documentoEsExport(ctx.codigoDocumento);
		var elegirExport = formularioPermiteElegirExport() && !clienteExport;
		return catalogoTiposPagina().filter(function (item) {
			if (!item) {
				return false;
			}
			if (tipoPermitidoEnCircuito(item, circuito)) {
				return true;
			}
			// Alta de comprobante: FAF/FAE se puede tipear antes del cliente.
			return elegirExport && esTipoExportExclusivoItem(item);
		}).map(function (item) {
			return parseInt(item.id, 10) || 0;
		});
	}

	function aplicarTipoEnCampoConsulta($campo, circuito, ctx) {
		if (!$campo || !$campo.length || typeof window.aplicarTipotransaccionVentaPorItem !== 'function') {
			return;
		}
		var lista = catalogoTiposPagina().filter(function (item) {
			return item && tipoPermitidoEnCircuito(item, circuito);
		});
		if (!lista.length) {
			return;
		}
		var actual = String($campo.val() || '');
		var elegido = null;
		if (circuito === 'exportacion') {
			if (ctx.preferTipoId) {
				elegido = lista.find(function (item) {
					return String(item.id) === String(ctx.preferTipoId);
				}) || null;
			}
			var preferAbrev = ['FAF', 'FAE', 'NCE', 'NDE', 'NCD'];
			for (var i = 0; i < preferAbrev.length && !elegido; i++) {
				var ab = preferAbrev[i];
				elegido = lista.find(function (item) {
					return norm(item.abreviatura) === ab;
				}) || null;
			}
			if (!elegido) {
				elegido = lista[0];
			}
		} else {
			elegido = lista.find(function (item) {
				return String(item.id) === actual;
			}) || null;
			if (!elegido && ctx.preferTipoId) {
				elegido = lista.find(function (item) {
					return String(item.id) === String(ctx.preferTipoId);
				}) || null;
			}
			if (!elegido) {
				elegido = lista[0];
			}
		}
		if (!elegido || String(elegido.id) === actual) {
			return;
		}
		var $ctx = $campo.closest('.tm-tipotransaccion-venta-campo');
		window.aplicarTipotransaccionVentaPorItem(
			$ctx.length ? $ctx : $campo.parent(),
			elegido,
			!!window.facturaCircuitoListo
		);
	}

	/**
	 * @param {object} ctx { letraCliente, codigoDocumento, preferPvId, preferTipoId }
	 */
	function aplicar($tipoSelect, $pvSelect, ctx) {
		ctx = ctx || {};
		if (window.__aplicandoCircuitoFactura) {
			return resolverCircuito(ctx);
		}
		window.__aplicandoCircuitoFactura = true;
		try {
			return aplicarCircuito($tipoSelect, $pvSelect, ctx);
		} finally {
			window.__aplicandoCircuitoFactura = false;
		}
	}

	function aplicarCircuito($tipoSelect, $pvSelect, ctx) {
		var circuito = resolverCircuito(ctx);
		var esSelect = $tipoSelect && $tipoSelect.length && $tipoSelect.is('select');
		if (esSelect) {
			filtrarSelect($tipoSelect, circuito, function ($o) {
				return circuito === 'exportacion'
					? esTipoPermitidoExportFromData($o)
					: !esTipoExportExclusivoFromData($o);
			});
		} else if ($tipoSelect && $tipoSelect.length) {
			aplicarTipoEnCampoConsulta($tipoSelect, circuito, ctx);
		}
		var pvAntes = $pvSelect && $pvSelect.length ? String($pvSelect.val() || '') : '';
		filtrarSelect($pvSelect, circuito, function ($o) {
			var exp = esPvExportFromData($o);
			return circuito === 'exportacion' ? exp : !exp;
		});

		if (circuito === 'exportacion' && esSelect) {
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
			$tipoSelect.trigger('change');
		}

		if ($pvSelect && $pvSelect.length) {
			var preferOk = ctx.preferPvId
				&& $pvSelect.find('option[value="' + ctx.preferPvId + '"]:not(:disabled)').length;
			if (preferOk) {
				$pvSelect.val(String(ctx.preferPvId));
			} else if (ctx.exigirEleccionPv) {
				var antesOk = pvAntes
					&& $pvSelect.find('option[value="' + pvAntes + '"]:not(:disabled)').length;
				if (!antesOk) {
					$pvSelect.val('');
				}
			}
			if (circuito === 'exportacion' || String($pvSelect.val() || '') !== pvAntes) {
				$pvSelect.trigger('change');
			}
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
		documentoEsExport: documentoEsExport,
		idsPermitidosPagina: idsPermitidosPagina
	};
})(window, window.jQuery);
