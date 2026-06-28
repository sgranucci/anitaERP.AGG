/* global carpetaBase, $ */
(function ($) {
	'use strict';

	function readMeta() {
		var $m = $('#wizard-requisicion-multiples-meta');
		if (!$m.length) {
			return null;
		}
		try {
			return JSON.parse($m.text());
		} catch (e) {
			return null;
		}
	}

	function normId(v) {
		var n = parseInt(String(v == null ? '0' : v), 10);
		return Number.isFinite(n) && n > 0 ? n : 0;
	}

	function readCabeceraBase(meta) {
		return {
			_token: meta.csrf,
			fecha: $('#fecha').val(),
			fechaentrega: $('#fechaentrega').val(),
			empresa_id: $('#empresa_id').val(),
			centrocosto_id: $('#centrocosto_id').val(),
			comentario: $('input[name="comentario"]').val() || '',
			detalle: $('textarea[name="detalle"]').val() || '',
			tratamiento: $('select[name="tratamiento"]').val(),
			requisicion_id: String(meta.requisicion_id),
			transporte_id: $('#transporte_id').val() || '',
			lugarentrega: $('#lugarentrega').val() || '',
			descuento: $('input[name="descuento"]').val() || '',
			comprobantes_json: $('#comprobantes_json').val() || '[]'
		};
	}

	function readLineArraysFromRows($rows) {
		var articulo_ids = [];
		var cantidades = [];
		var precios = [];
		var moneda_linea_ids = [];
		var cotizaciones_linea = [];
		var fechaentrega_articulos = [];
		var centrocostodestino_ids = [];
		var partidagasto_ids = [];
		var codigopartidagastos = [];
		var descripcionpartidagastos = [];
		var capex_ids = [];
		var codigocapexs = [];
		var descripcioncapexs = [];
		var detalle_articulos = [];
		var requisicion_articulo_ids = [];
		var precio_origen_tipos = [];
		var precio_origen_ref_ids = [];
		var precio_origen_etiquetas = [];
		var ordencompra_articulo_ids = [];
		var descuentos_linea = [];
		var cantidadalternativas = [];
		var codigoarticulos = [];
		var descripcionarticulos = [];

		$rows.each(function () {
			var $tr = $(this);
			articulo_ids.push($tr.find('.articulo_id').val() || '');
			cantidades.push($tr.find('.cantidad-linea').val() || '');
			precios.push($tr.find('.precio-linea').val() || '');
			moneda_linea_ids.push($tr.find('select[name="moneda_linea_ids[]"]').val() || '');
			cotizaciones_linea.push($tr.find('.oc-cotizacion-linea').val() || '1');
			fechaentrega_articulos.push($tr.find('input[name="fechaentrega_articulos[]"]').val() || '');
			centrocostodestino_ids.push($tr.find('select[name="centrocostodestino_ids[]"]').val() || '');
			partidagasto_ids.push($tr.find('.partidagasto_id').val() || '');
			codigopartidagastos.push($tr.find('input[name="codigopartidagastos[]"]').val() || '');
			descripcionpartidagastos.push($tr.find('input[name="descripcionpartidagastos[]"]').val() || '');
			capex_ids.push($tr.find('.capex_id').val() || '');
			codigocapexs.push($tr.find('input[name="codigocapexs[]"]').val() || '');
			descripcioncapexs.push($tr.find('input[name="descripcioncapexs[]"]').val() || '');
			detalle_articulos.push($tr.find('textarea[name="detalle_articulos[]"]').first().val() || '');
			requisicion_articulo_ids.push($tr.find('.oc-requisicion-articulo-id').val() || '');
			precio_origen_tipos.push($tr.find('.oc-precio-origen-tipo').val() || '');
			precio_origen_ref_ids.push($tr.find('.oc-precio-origen-ref-id').val() || '');
			precio_origen_etiquetas.push($tr.find('.oc-precio-origen-etiqueta').val() || '');
			ordencompra_articulo_ids.push($tr.find('.ordencompra_articulo_id').val() || '');
			descuentos_linea.push($tr.find('input[name="descuentos_linea[]"]').val() || '');
			cantidadalternativas.push($tr.find('input[name="cantidadalternativas[]"]').val() || '');
			codigoarticulos.push($tr.find('input[name="codigoarticulos[]"]').val() || '');
			descripcionarticulos.push($tr.find('input[name="descripcionarticulos[]"]').val() || '');
		});

		return {
			articulo_ids: articulo_ids,
			cantidades: cantidades,
			precios: precios,
			moneda_linea_ids: moneda_linea_ids,
			cotizaciones_linea: cotizaciones_linea,
			fechaentrega_articulos: fechaentrega_articulos,
			centrocostodestino_ids: centrocostodestino_ids,
			partidagasto_ids: partidagasto_ids,
			codigopartidagastos: codigopartidagastos,
			descripcionpartidagastos: descripcionpartidagastos,
			capex_ids: capex_ids,
			codigocapexs: codigocapexs,
			descripcioncapexs: descripcioncapexs,
			detalle_articulos: detalle_articulos,
			requisicion_articulo_ids: requisicion_articulo_ids,
			precio_origen_tipos: precio_origen_tipos,
			precio_origen_ref_ids: precio_origen_ref_ids,
			precio_origen_etiquetas: precio_origen_etiquetas,
			ordencompra_articulo_ids: ordencompra_articulo_ids,
			descuentos_linea: descuentos_linea,
			cantidadalternativas: cantidadalternativas,
			codigoarticulos: codigoarticulos,
			descripcionarticulos: descripcionarticulos
		};
	}

	function mergePayload(cab, lineas, gm) {
		var p = $.extend({}, cab);
		p.proveedor_id = normId(gm.proveedor_id) > 0 ? String(normId(gm.proveedor_id)) : '';
		p.codigoproveedor = '';
		p.nombreproveedor = '';
		p.condicioncompra_id = normId(gm.condicioncompra_id) > 0 ? String(normId(gm.condicioncompra_id)) : '';
		p.condicionentrega_id = normId(gm.condicionentrega_id) > 0 ? String(normId(gm.condicionentrega_id)) : '';
		$.extend(p, lineas);
		return p;
	}

	function mostrarModalResultado(res, volverUrl) {
		var metaLocal = readMeta();
		var puedeEnviar = !!(metaLocal && metaLocal.puede_enviar_proveedor);
		var html = '<div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Nº OC</th><th>Acciones</th></tr></thead><tbody>';
		(res.ordenes || []).forEach(function (o) {
			var num = $('<div/>').text(String(o.numeroordencompra != null ? o.numeroordencompra : o.id)).html();
			var u1 = String(o.url_imprimir || '');
			var u2 = String(o.url_imprimir_apaisado || '');
			var verUrl = (typeof carpetaBase !== 'undefined' ? carpetaBase : '') + '/compras/ordencompra/' + parseInt(o.id, 10) + '/editar';
			html +=
				'<tr><td>' +
				num +
				'</td><td class="text-nowrap">' +
				'<a class="btn btn-sm btn-outline-primary mr-1" target="_blank" rel="noopener noreferrer" href="' +
				verUrl.replace(/"/g, '&quot;') +
				'"><i class="fa fa-eye"></i></a> ' +
				'<a class="btn btn-sm btn-primary mr-1" target="_blank" rel="noopener noreferrer" href="' +
				u1.replace(/"/g, '&quot;') +
				'">PDF</a> <a class="btn btn-sm btn-outline-primary mr-1" target="_blank" rel="noopener noreferrer" href="' +
				u2.replace(/"/g, '&quot;') +
				'">Apaisado</a>';
			if (puedeEnviar && o.puede_enviar_proveedor) {
				html +=
					' <button type="button" class="btn btn-sm btn-success js-oc-enviar-proveedor" data-ordencompra-id="' +
					parseInt(o.id, 10) +
					'"><i class="fa fa-envelope"></i></button>';
			}
			html += '</td></tr>';
		});
		html += '</tbody></table></div>';
		if (res.advertencias && res.advertencias.length) {
			html += '<div class="alert alert-warning mt-2"><ul class="mb-0">';
			res.advertencias.forEach(function (a) {
				html += '<li>' + $('<div/>').text(a).html() + '</li>';
			});
			html += '</ul></div>';
		}
		var pendientes = res.envios_pendientes || [];
		var idsEnvio = pendientes.map(function (p) { return parseInt(p.id, 10); });
		var btnEnvio = '';
		if (puedeEnviar && pendientes.length) {
			html += '<div class="alert alert-info mt-2"><strong><i class="fa fa-envelope"></i> ' + pendientes.length +
				' orden(es) con email de proveedor.</strong> Puede revisar el envío ahora o más tarde desde el listado.</div>';
			btnEnvio =
				'<button type="button" class="btn btn-success js-oc-wizard-iniciar-envios mr-2" data-resultados-modal="#modalWizardMultiplesOcResultado" data-envio-ids="' +
				$('<div/>').text(JSON.stringify(idsEnvio)).html() +
				'"><i class="fa fa-envelope"></i> Enviar al proveedor (' + pendientes.length + ')</button>';
		}
		var vu = String(volverUrl || '');
		var $modal = $(
			'<div class="modal fade" id="modalWizardMultiplesOcResultado" tabindex="-1" role="dialog" data-backdrop="static">' +
				'<div class="modal-dialog modal-lg" role="document"><div class="modal-content">' +
				'<div class="modal-header bg-success text-white"><h5 class="modal-title">Órdenes de compra generadas</h5>' +
				'<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>' +
				'<div class="modal-body">' +
				html +
				'</div><div class="modal-footer">' +
				btnEnvio +
				'<a class="btn btn-outline-secondary" href="' +
				vu.replace(/"/g, '&quot;') +
				'">Volver a la requisición</a>' +
				'<button type="button" class="btn btn-primary" data-dismiss="modal">Cerrar</button>' +
				'</div></div></div></div>'
		);
		$('body').append($modal);
		$modal.modal('show');
		$modal.one('shown.bs.modal', function () {
			if (typeof window.ocWizardOfrecerEnvioProveedor === 'function') {
				window.ocWizardOfrecerEnvioProveedor(res, { resultadosModal: '#modalWizardMultiplesOcResultado' });
			}
		});
		$modal.on('hidden.bs.modal', function () {
			$modal.remove();
		});
	}

	window.reqOcWizardInterceptSubmit = function (e, form) {
		var meta = readMeta();
		if (!meta) {
			return true;
		}

		var sinOrigen = [];
		var conOrigen = [];
		var errGrupo = null;
		$('#tabla-articulos-ordencompra tbody tr.item-ordencompra-articulo').each(function () {
			var $tr = $(this);
			var ra = parseInt($tr.find('.oc-requisicion-articulo-id').val(), 10);
			var aid = parseInt($tr.find('.articulo_id').val(), 10);
			var cant = parseFloat($tr.find('.cantidad-linea').val()) || 0;
			if (!ra || !aid || cant <= 0) {
				return;
			}
			var tipo = ($tr.find('.oc-precio-origen-tipo').val() || '').trim();
			if (!tipo) {
				sinOrigen.push({ $tr: $tr, ra: ra });
				return;
			}
			var gm0 = $tr.data('ocGrupoMeta');
			if (!gm0 || typeof gm0 !== 'object') {
				errGrupo = 'Falta el criterio de agrupación en una línea con precio. Vuelva a elegir origen de precio (lista, presupuesto o requisición).';
				return false;
			}
			var gm = $.extend({}, gm0);
			if (normId(gm.proveedor_id) <= 0) {
				var fpCab = normId($('#proveedor_id').val());
				if (fpCab > 0) {
					gm.proveedor_id = fpCab;
				}
			}
			if (normId(gm.proveedor_id) <= 0) {
				errGrupo = 'Una línea tiene precio de requisición pero la requisición no tiene proveedor sugerido. Indique proveedor en la cabecera o elija lista/presupuesto.';
				return false;
			}
			var key = [normId(gm.proveedor_id), normId(gm.condicioncompra_id), normId(gm.condicionentrega_id)].join('|');
			conOrigen.push({ $tr: $tr, gm: gm, key: key });
		});
		if (errGrupo) {
			alert(errGrupo);
			return false;
		}

		if (sinOrigen.length) {
			var msg =
				'Hay ' +
				sinOrigen.length +
				' ítem(es) sin origen de precio. Se registrarán como línea cerrada sin orden de compra y la requisición pasará a GENERO ORDEN COMPRA. ¿Continuar?';
			if (!window.confirm(msg)) {
				return false;
			}
		}

		var grupos = {};
		conOrigen.forEach(function (row) {
			if (!grupos[row.key]) {
				grupos[row.key] = { gm: row.gm, $rows: $() };
			}
			grupos[row.key].$rows = grupos[row.key].$rows.add(row.$tr);
		});

		var ordenes = [];
		var cab = readCabeceraBase(meta);
		Object.keys(grupos).forEach(function (k) {
			var g = grupos[k];
			var lineas = readLineArraysFromRows(g.$rows);
			ordenes.push(mergePayload(cab, lineas, g.gm));
		});

		var lineasSin = sinOrigen.map(function (x) {
			return x.ra;
		});

		if (ordenes.length === 0 && lineasSin.length === 0) {
			alert('No hay líneas válidas ni ítems a cerrar.');
			return false;
		}

		var $btn = $('#form-ordencompra-general').find('[type="submit"]');
		$btn.prop('disabled', true);

		$.ajax({
			url: meta.post_url,
			method: 'POST',
			contentType: 'application/json',
			dataType: 'json',
			data: JSON.stringify({
				_token: meta.csrf,
				requisicion_id: meta.requisicion_id,
				ordenes: ordenes,
				lineas_sin_orden: lineasSin
			}),
			headers: {
				'X-CSRF-TOKEN': meta.csrf,
				Accept: 'application/json'
			}
		})
			.done(function (res) {
				if (res && res.mensaje === 'ok') {
					mostrarModalResultado(res, meta.volver_url);
				} else {
					alert((res && res.message) || 'Error al generar.');
				}
			})
			.fail(function (xhr) {
				var msg = 'Error al generar las órdenes de compra.';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					msg = xhr.responseJSON.message;
				}
				alert(msg);
			})
			.always(function () {
				$btn.prop('disabled', false);
			});

		return false;
	};

	$(function () {
		var meta = readMeta();
		if (!meta) {
			return;
		}
		if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
			window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
		}
		if (typeof window.ocAplicarPlantillaRequisicionDesdeId === 'function') {
			$('#requisicion_id').val(String(meta.requisicion_id));
			window.ocAplicarPlantillaRequisicionDesdeId(meta.requisicion_id);
		}
	});
})(jQuery);
