/* global carpetaBase, $ */
/**
 * Wizard "Generar múltiples OC desde requisición" — vista dedicada.
 *
 * Layout: ítems-arriba + cabecera por OC abajo. Por cada ítem se elige el
 * origen de precio (lista, presupuesto o requisición). En base a la
 * combinación (proveedor + condicioncompra + condicionentrega) el sistema
 * detecta las OC a generar, una por grupo. Cada grupo tiene su propia
 * cabecera, comprobantes a venir y archivos asociados.
 */
(function ($) {
	'use strict';

	if (typeof carpetaBase === 'undefined' || carpetaBase === '') {
		window.carpetaBase = window.location.pathname.split('/public')[0] + '/public';
	}

	function wzCarpetaBase() {
		var base = String(typeof carpetaBase !== 'undefined' ? carpetaBase : '').replace(/\/$/, '');
		if (base) {
			return base;
		}
		var m = (window.location.pathname || '').match(
			/^(.+)\/(ventas|caja|stock|compras|contable|seguridad|presupuesto|ticket|admin|uif)\//
		);
		return m && m[1] ? m[1] : '';
	}

	function wzApiPath(relativePath) {
		var path = String(relativePath || '').replace(/^\//, '');
		var base = wzCarpetaBase();
		return base ? base + '/' + path : '/' + path;
	}

	function wzMetaEndpoint(key) {
		if (!META) {
			return '';
		}
		if (META[key + '_path']) {
			return wzApiPath(META[key + '_path']);
		}
		var legacy = META[key + '_url'];
		if (!legacy) {
			return '';
		}
		var path = String(legacy);
		if (/^https?:\/\//i.test(path)) {
			try {
				path = new URL(path).pathname;
			} catch (e) {
				return path;
			}
		}
		var base = wzCarpetaBase();
		while (base && path.indexOf(base + base) === 0) {
			path = path.slice(base.length);
		}
		if (base && (path === base || path.indexOf(base + '/') === 0)) {
			return path;
		}
		if (/^\/(compras|ventas|stock|caja|contable|presupuesto|admin|uif)\//.test(path)) {
			return base ? base + path : path;
		}
		return path;
	}

	function readJsonScript(id) {
		var el = document.getElementById(id);
		if (!el) {
			return null;
		}
		var raw = el.textContent || el.innerText || '';
		if (!String(raw).trim()) {
			return null;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	var META = null;
	var MONEDAS = [];
	var PROVEEDORES = [];
	var CC_COMPRA = [];
	var CC_ENTREGA = [];
	var CC_PAGO = [];
	var TRANSPORTES = [];
	var FORMAPAGOS = [];
	var TRATAMIENTOS = [];

	function initWizardConfig() {
		if (META) {
			return true;
		}
		META = readJsonScript('oc-wizard-meta');
		MONEDAS = readJsonScript('oc-wizard-monedas') || [];
		PROVEEDORES = readJsonScript('oc-wizard-proveedores') || [];
		CC_COMPRA = readJsonScript('oc-wizard-condicionescompra') || [];
		CC_ENTREGA = readJsonScript('oc-wizard-condicionesentrega') || [];
		CC_PAGO = readJsonScript('oc-wizard-condicionespago') || [];
		TRANSPORTES = readJsonScript('oc-wizard-transportes') || [];
		FORMAPAGOS = readJsonScript('oc-wizard-formapagos') || [];
		TRATAMIENTOS = readJsonScript('oc-wizard-tratamientos') || [];
		return !!META;
	}

	function wizardMostrarErrorCarga(mensaje) {
		$('#wizard-oc-tabla-articulos-body').html(
			'<tr><td colspan="12" class="text-danger text-center">' + htmlEsc(mensaje) + '</td></tr>'
		);
	}

	function wizardFechaInput(valor) {
		if (valor == null || valor === '') {
			return '';
		}
		return String(valor).substring(0, 10);
	}

	// ---------------------------------------------------------------------
	// Estado
	// ---------------------------------------------------------------------
	var lineas = []; // [{rowKey, requisicion_articulo_id, articulo_id, sku, descripcion, cantidad, precio, moneda_id, cotizacion, fechaentrega, centrocostodestino_id, partidagasto_id, codigopartidagasto, descripcionpartidagasto, capex_id, codigocapex, descripcioncapex, detalle, origen: null|{tipo, ref_id, etiqueta, precio, moneda_id, proveedor_id, condicioncompra_id, condicionentrega_id, condicionpago_id}}]
	var grupos = []; // [{ key, proveedor_id, condicioncompra_id, condicionentrega_id, condicionpago_id, transporte_id, lugarentrega, comentario, comprobantes: [], archivos: [File], lineasIdx: [int] }]
	var compEditCtx = { grupoIdx: -1, compIdx: -1 }; // contexto al abrir modal de comprobantes
	var pendingPrecioRow = null;
	var requisicionProveedorId = 0; // proveedor sugerido por la requisición (para consultas de lista de precio)
	var wzTotalesCabTimer = null;
	var wzTotalesCabReqId = 0;

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------
	function htmlEsc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function normId(v) {
		var n = parseInt(String(v == null ? '' : v), 10);
		return Number.isFinite(n) && n > 0 ? n : 0;
	}

	function countLineasSinOrigen() {
		var n = 0;
		lineas.forEach(function (l) {
			if (!l.origen) {
				n++;
			}
		});
		return n;
	}

	function lineasSinOrigenParaResumen() {
		return lineas.filter(function (l) {
			return !l.origen;
		});
	}

	function todosGruposTienenProveedor() {
		for (var i = 0; i < grupos.length; i++) {
			if (normId(grupos[i].proveedor_id) <= 0) {
				return false;
			}
		}
		return true;
	}

	function indiceLineaDesdeFila($tr) {
		var idx = parseInt($tr.attr('data-lin-idx'), 10);
		if (!Number.isFinite(idx)) {
			idx = parseInt($tr.data('linIdx'), 10);
		}
		return Number.isFinite(idx) ? idx : -1;
	}

	function sincronizarLineaDesdeDom(idx) {
		var $tr = $('#wizard-oc-tabla-articulos-body tr.wizard-oc-fila-item[data-lin-idx="' + idx + '"]');
		if (!$tr.length || !lineas[idx]) {
			return lineas[idx] || null;
		}
		var lin = lineas[idx];
		lin.cantidad = $tr.find('.wz-lin-cantidad').val();
		lin.precio = $tr.find('.wz-lin-precio').val();
		lin.moneda_id = parseInt($tr.find('.wz-lin-moneda').val(), 10) || lin.moneda_id;
		lin.cotizacion = $tr.find('.wz-lin-cotiz').val();
		lin.fechaentrega = $tr.find('.wz-lin-fechaentrega').val();
		lin.centrocostodestino_id = parseInt($tr.find('.wz-lin-cc-destino').val(), 10) || lin.centrocostodestino_id;
		return lin;
	}

	function construirLineaDesdeFilaDom($tr, idx) {
		return {
			requisicion_articulo_id: parseInt($tr.attr('data-requisicion-articulo-id'), 10) || 0,
			articulo_id: parseInt($tr.attr('data-articulo-id'), 10) || 0,
			sku: $tr.attr('data-sku') || '',
			descripcion: $tr.attr('data-descripcion') || '',
			color_id: parseInt($tr.attr('data-color-id'), 10) || 0,
			talle_id: parseInt($tr.attr('data-talle-id'), 10) || 0,
			color_nombre: $tr.attr('data-color-nombre') || '',
			talle_nombre: $tr.attr('data-talle-nombre') || '',
			cantidad: $tr.find('.wz-lin-cantidad').val(),
			precio: $tr.find('.wz-lin-precio').val(),
			moneda_id: parseInt($tr.find('.wz-lin-moneda').val(), 10) || (META ? META.moneda_peso_id : 1),
			cotizacion: $tr.find('.wz-lin-cotiz').val() || 1,
			fechaentrega: $tr.find('.wz-lin-fechaentrega').val() || '',
			centrocostodestino_id: parseInt($tr.find('.wz-lin-cc-destino').val(), 10) || (META ? META.centrocosto_default_id : 1),
			partidagasto_id: '',
			codigopartidagasto: '',
			descripcionpartidagasto: '',
			capex_id: '',
			codigocapex: '',
			descripcioncapex: '',
			detalle: '',
			proveedor_id: parseInt($tr.attr('data-proveedor-id'), 10) || 0,
			articulo_proveedor_id: parseInt($tr.attr('data-articulo-proveedor-id'), 10) || 0,
			origen: null,
		};
	}

	function asegurarLineaEnIndice($tr, idx) {
		if (idx < 0) {
			return null;
		}
		if (!lineas[idx]) {
			lineas[idx] = construirLineaDesdeFilaDom($tr, idx);
		} else {
			sincronizarLineaDesdeDom(idx);
		}
		return lineas[idx];
	}

	function ensureWizardHidratado() {
		if (lineas.length > 0) {
			window.__WZ_LINEAS_HIDRATADAS__ = true;
			window.__WZ_PLANTILLA_APLICADA__ = true;
			return true;
		}
		if (!initWizardConfig()) {
			return false;
		}
		var plEmbebida = readJsonScript('oc-wizard-plantilla');
		if (!plEmbebida) {
			return false;
		}
		try {
			if (tablaArticulosRenderizadaEnServidor()) {
				hidratarPlantillaEmbebida(plEmbebida);
			} else {
				aplicarPlantilla(plEmbebida);
			}
			window.__WZ_LINEAS_HIDRATADAS__ = lineas.length > 0;
			return lineas.length > 0;
		} catch (err) {
			lineas = construirLineasDesdePlantilla(plEmbebida);
			window.__WZ_LINEAS_HIDRATADAS__ = lineas.length > 0;
			window.__WZ_PLANTILLA_APLICADA__ = lineas.length > 0;
			try {
				recalcGruposYRender();
			} catch (e2) {
				// La grilla SSR sigue usable aunque falle el resumen de grupos.
			}
			return lineas.length > 0;
		}
	}

	function precargarHandlersConsultaProveedor() {
		if (typeof window.activa_eventos_consultaproveedor === 'function') {
			window.activa_eventos_consultaproveedor();
		}
	}

	function abrirModalConsultaProveedor() {
		precargarHandlersConsultaProveedor();
		if (typeof window.buscar_datos_proveedor === 'function') {
			window.buscar_datos_proveedor('');
		}
		$('#consultaproveedorModal').modal('show');
	}

	function leerProveedorIdDesdeFilaConsulta($link) {
		if (typeof window.leerFilaProveedorConsulta === 'function') {
			var fila = window.leerFilaProveedorConsulta($link);
			return parseInt(String(fila.id || '0'), 10) || 0;
		}
		var $trProv = $link.closest('tr');
		return parseInt(
			$trProv.find('td.proveedor_id').first().text().trim() ||
				$trProv.children().first().text().trim(),
			10
		) || 0;
	}

	function abrirElegirProveedorLinea($tr) {
		if (!ensureWizardHidratado()) {
			alert('No se pudo inicializar el wizard. Recargue la página (Ctrl+F5).');
			return;
		}
		var idx = indiceLineaDesdeFila($tr);
		var lin = asegurarLineaEnIndice($tr, idx);
		if (!lin || !lin.requisicion_articulo_id) {
			alert('No se pudo identificar la línea de requisición.');
			return;
		}
		pendingPrecioRow = idx;
		window.wzCambiarProveedorLinea = idx;
		window.wzCambiarProveedorGrupo = null;
		precargarHandlersConsultaProveedor();
		if (typeof window.buscar_datos_proveedor === 'function') {
			window.buscar_datos_proveedor('');
		}
		$('#consultaproveedorModal').modal('show');
	}

	function precioLineaNumerico(lin) {
		var p = parseFloat(String(lin.precio == null ? '' : lin.precio).replace(',', '.'));
		return Number.isFinite(p) && p > 0 ? p : 0;
	}

	/** Precio ya cargado en la requisición: origen REQUISICION sin obligar al usuario a abrir el modal de origen. */
	function origenFallbackDesdeRequisicion(lin) {
		if (precioLineaNumerico(lin) <= 0) {
			return null;
		}
		var prov = normId(lin.proveedor_id) > 0
			? normId(lin.proveedor_id)
			: (requisicionProveedorId > 0 ? requisicionProveedorId : 0);
		return {
			tipo: 'REQUISICION',
			ref_id: lin.requisicion_articulo_id,
			etiqueta: 'Precio cargado en la requisición',
			precio: lin.precio,
			moneda_id: lin.moneda_id,
			proveedor_id: prov,
			condicioncompra_id: 0,
			condicionentrega_id: 0,
			condicionpago_id: 0,
		};
	}

	function aplicarFallbackOrigenEnLineas() {
		lineas.forEach(function (lin) {
			if (!lin.origen) {
				var fb = origenFallbackDesdeRequisicion(lin);
				if (fb) {
					lin.origen = fb;
				}
			}
		});
	}

	function asignarProveedorAGrupo(gidx, pid) {
		var g = grupos[gidx];
		if (!g || normId(pid) <= 0) {
			return;
		}
		g.lineasIdx.forEach(function (linIdx) {
			if (lineas[linIdx] && lineas[linIdx].origen) {
				lineas[linIdx].origen.proveedor_id = pid;
			}
		});
		recalcGruposYRender();
		renderArticulos();
		if ($('#modalWizardProveedorFaltante').hasClass('show')) {
			renderModalProveedorFaltante();
		}
		$.get(carpetaBase + '/compras/leerproveedor/' + pid, function (data) {
			if (!data || !grupos[gidx]) {
				return;
			}
			var grupo = grupos[gidx];
			if (data.condicioncompra_id && normId(grupo.condicioncompra_id) <= 0) {
				grupo.condicioncompra_id = parseInt(data.condicioncompra_id, 10);
			}
			if (data.condicionentrega_id && normId(grupo.condicionentrega_id) <= 0) {
				grupo.condicionentrega_id = parseInt(data.condicionentrega_id, 10);
			}
			if (data.condicionpago_id && normId(grupo.condicionpago_id) <= 0) {
				grupo.condicionpago_id = parseInt(data.condicionpago_id, 10);
			}
			grupo.lineasIdx.forEach(function (linIdx) {
				if (lineas[linIdx] && lineas[linIdx].origen) {
					if (normId(lineas[linIdx].origen.condicioncompra_id) <= 0 && grupo.condicioncompra_id) {
						lineas[linIdx].origen.condicioncompra_id = grupo.condicioncompra_id;
					}
					if (normId(lineas[linIdx].origen.condicionentrega_id) <= 0 && grupo.condicionentrega_id) {
						lineas[linIdx].origen.condicionentrega_id = grupo.condicionentrega_id;
					}
					if (normId(lineas[linIdx].origen.condicionpago_id) <= 0 && grupo.condicionpago_id) {
						lineas[linIdx].origen.condicionpago_id = grupo.condicionpago_id;
					}
				}
			});
			renderGruposResumen([]);
			renderTabsGrupos();
		});
	}

	function fmtNum(n, dec) {
		var x = parseFloat(n);
		if (!Number.isFinite(x)) {
			return '—';
		}
		return x.toLocaleString('es-AR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
	}

	function monedaAbrev(id) {
		var m = MONEDAS.find(function (x) { return parseInt(x.id, 10) === parseInt(id, 10); });
		return m ? m.abrev : '';
	}

	function proveedorNombre(id) {
		var p = PROVEEDORES.find(function (x) { return parseInt(x.id, 10) === parseInt(id, 10); });
		if (p && p.nombre) {
			return (p.codigo ? '(' + p.codigo + ') ' : '') + p.nombre;
		}
		var n = parseInt(id, 10);
		return n > 0 ? 'Proveedor #' + n : '';
	}

	function condicionCompraNombre(id) {
		var p = CC_COMPRA.find(function (x) { return parseInt(x.id, 10) === parseInt(id, 10); });
		return p ? p.nombre : '';
	}

	function condicionEntregaNombre(id) {
		var p = CC_ENTREGA.find(function (x) { return parseInt(x.id, 10) === parseInt(id, 10); });
		return p ? p.nombre : '';
	}

	function condicionPagoNombre(id) {
		var p = CC_PAGO.find(function (x) { return parseInt(x.id, 10) === parseInt(id, 10); });
		return p ? p.nombre : '';
	}

	function tamanioHumano(bytes) {
		if (!Number.isFinite(bytes)) {
			return '—';
		}
		var u = ['B', 'KB', 'MB', 'GB'];
		var i = 0;
		while (bytes >= 1024 && i < u.length - 1) {
			bytes = bytes / 1024;
			i++;
		}
		return bytes.toFixed(i === 0 ? 0 : 2) + ' ' + u[i];
	}

	function selectOptionsHtml(rows, valKey, labelKey, selectedId, allowVacio) {
		var html = '';
		if (allowVacio) {
			html += '<option value="">—</option>';
		}
		rows.forEach(function (r) {
			var sel = parseInt(r[valKey], 10) === parseInt(selectedId, 10) ? ' selected' : '';
			html += '<option value="' + htmlEsc(r[valKey]) + '"' + sel + '>' + htmlEsc(r[labelKey]) + '</option>';
		});
		return html;
	}

	// ---------------------------------------------------------------------
	// Render: tabla de ítems
	// ---------------------------------------------------------------------
	function renderArticulos() {
		var $b = $('#wizard-oc-tabla-articulos-body').empty();
		if (!lineas.length) {
			$b.append('<tr><td colspan="14" class="text-center text-muted py-3">La requisición no tiene ítems.</td></tr>');
			$('#wizard-oc-articulos-resumen').text('0 ítems');
			return;
		}
		$('#wizard-oc-articulos-resumen').text(lineas.length + ' ítems');

		lineas.forEach(function (lin, idx) {
			var tieneOrigen = !!lin.origen;
			var pillClass = tieneOrigen ? 'con-origen' : 'sin-origen';
			var pillTexto = tieneOrigen
				? htmlEsc(lin.origen.etiqueta || lin.origen.tipo) + (lin.origen.proveedor_id ? '<br><small>' + htmlEsc(proveedorNombre(lin.origen.proveedor_id) || '—') + '</small>' : '')
				: '<em>Sin origen</em>';

			var $tr = $('<tr class="wizard-oc-fila-item" data-lin-idx="' + idx + '"></tr>');
			$tr.append('<td class="text-center">' + (idx + 1) + '</td>');
			$tr.append('<td>' + htmlEsc(lin.sku) + '</td>');
			$tr.append('<td>' + htmlEsc(lin.descripcion) + '</td>');
			$tr.append('<td>' + htmlEsc(lin.color_nombre || (lin.color_id ? String(lin.color_id) : '—')) + '</td>');
			$tr.append('<td>' + htmlEsc(lin.talle_nombre || (lin.talle_id ? String(lin.talle_id) : '—')) + '</td>');
			$tr.append(
				'<td><input type="number" step="0.0001" class="form-control form-control-sm wz-lin-cantidad" value="' + htmlEsc(lin.cantidad) + '"></td>'
			);
			$tr.append(
				'<td><input type="number" step="0.0001" class="form-control form-control-sm wz-lin-precio" value="' + htmlEsc(lin.precio) + '"></td>'
			);
			$tr.append(
				'<td><select class="form-control form-control-sm wz-lin-moneda">' + selectOptionsHtml(MONEDAS, 'id', 'abrev', lin.moneda_id, false) + '</select></td>'
			);
			$tr.append(
				'<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm wz-lin-cotiz" value="' + htmlEsc(lin.cotizacion || 1) + '"></td>'
			);
			$tr.append(
				'<td><input type="date" class="form-control form-control-sm wz-lin-fechaentrega" value="' + htmlEsc((lin.fechaentrega || '').substring(0, 10)) + '"></td>'
			);
			$tr.append(
				'<td>' +
					'<select class="form-control form-control-sm wz-lin-cc-destino">' +
					selectOptionsHtml(
						(window.wzCentrocostos || []).length ? window.wzCentrocostos : [{ id: lin.centrocostodestino_id, codigo: '', nombre: '(actual)' }],
						'id',
						'label',
						lin.centrocostodestino_id,
						false
					) +
					'</select>' +
					'</td>'
			);
			$tr.append(
				'<td><span class="small">' + htmlEsc(lin.codigopartidagasto || '—') + (lin.descripcionpartidagasto ? '<br><span class="text-muted small">' + htmlEsc(lin.descripcionpartidagasto) + '</span>' : '') + '</span></td>'
			);
			$tr.append(
				'<td><span class="small">' + htmlEsc(lin.codigocapex || '—') + (lin.descripcioncapex ? '<br><span class="text-muted small">' + htmlEsc(lin.descripcioncapex) + '</span>' : '') + '</span></td>'
			);
			$tr.append(
				'<td class="text-center">' +
					'<button type="button" class="btn btn-sm btn-outline-primary wz-lin-btn-origen mb-1" title="Elegir origen del precio"><i class="fa fa-tags"></i> Origen</button><br>' +
					'<button type="button" class="btn btn-sm btn-outline-secondary wz-lin-btn-proveedor mb-1" title="Elegir proveedor manualmente"><i class="fa fa-truck"></i> Proveedor</button><br>' +
					'<span class="badge badge-pill wizard-oc-origen-pill ' + pillClass + '">' + pillTexto + '</span>' +
					'</td>'
			);
			$b.append($tr);
		});

		// Re-bind centrocosto options según cache (los CC los traemos del plantilla)
		if (window.wzCentrocostos && window.wzCentrocostos.length) {
			$('#wizard-oc-tabla-articulos-body .wz-lin-cc-destino').each(function () {
				var $sel = $(this);
				var idxRow = parseInt($sel.closest('tr').data('linIdx'), 10);
				var cur = lineas[idxRow] ? lineas[idxRow].centrocostodestino_id : '';
				$sel.html(selectOptionsHtml(window.wzCentrocostos, 'id', 'label', cur, false));
			});
		}
	}

	// ---------------------------------------------------------------------
	// Recalcular grupos
	// ---------------------------------------------------------------------
	function recalcGruposYRender() {
		aplicarFallbackOrigenEnLineas();

		var sigueGrupos = grupos.map(function (g) {
			return {
				key: g.key,
				transporte_id: g.transporte_id,
				lugarentrega: g.lugarentrega,
				comentario: g.comentario,
				comprobantes: g.comprobantes || [],
				archivos: g.archivos || [],
			};
		});

		var nuevos = {};
		var sinOrigen = [];

		lineas.forEach(function (lin, idx) {
			if (!lin.origen) {
				sinOrigen.push(idx);
				return;
			}
			var prov = normId(lin.origen.proveedor_id);
			if (prov <= 0) {
				prov = normId(lin.proveedor_id);
			}
			var cc = normId(lin.origen.condicioncompra_id);
			var ce = normId(lin.origen.condicionentrega_id);
			var cp = normId(lin.origen.condicionpago_id);
			var k = prov + '|' + cc + '|' + ce;
			if (!nuevos[k]) {
				var prev = sigueGrupos.find(function (s) { return s.key === k; });
				nuevos[k] = {
					key: k,
					proveedor_id: prov,
					condicioncompra_id: cc,
					condicionentrega_id: ce,
					condicionpago_id: prev ? normId(prev.condicionpago_id) : cp,
					transporte_id: prev ? prev.transporte_id : 0,
					lugarentrega: prev ? prev.lugarentrega : '',
					comentario: prev ? prev.comentario : '',
					comprobantes: prev ? prev.comprobantes : [],
					archivos: prev ? prev.archivos : [],
					lineasIdx: [],
				};
			} else if (normId(nuevos[k].condicionpago_id) <= 0 && cp > 0) {
				nuevos[k].condicionpago_id = cp;
			}
			nuevos[k].lineasIdx.push(idx);
		});

		grupos = Object.keys(nuevos).map(function (k) { return nuevos[k]; });

		renderGruposResumen(sinOrigen);
		renderTabsGrupos();
		actualizarBotonGenerar();
	}

	function renderGruposResumen(sinOrigen) {
		var $resumen = $('#wizard-oc-grupos-resumen tbody').empty();
		$('#wizard-oc-grupos-cantidad').text(grupos.length + ' órdenes');

		if (!grupos.length) {
			$('#wizard-oc-grupos-vacio').show();
			$('#wizard-oc-grupos-resumen-wrap').addClass('d-none');
		} else {
			$('#wizard-oc-grupos-vacio').hide();
			$('#wizard-oc-grupos-resumen-wrap').removeClass('d-none');
			grupos.forEach(function (g, idx) {
				var sinProv = normId(g.proveedor_id) <= 0;
				var $tr = $('<tr class="grupo-resumen-fila' + (sinProv ? ' table-warning' : '') + '" data-gidx="' + idx + '"></tr>');
				$tr.append('<td class="text-center">' + (idx + 1) + '</td>');
				$tr.append(
					'<td>' +
						htmlEsc(proveedorNombre(g.proveedor_id) || '<sin proveedor>') +
						(sinProv ? ' <span class="badge badge-danger">Falta proveedor</span>' : '') +
						'</td>'
				);
				$tr.append('<td>' + htmlEsc(condicionCompraNombre(g.condicioncompra_id) || '—') + '</td>');
				$tr.append('<td>' + htmlEsc(condicionEntregaNombre(g.condicionentrega_id) || '—') + '</td>');
				$tr.append('<td>' + htmlEsc(condicionPagoNombre(g.condicionpago_id) || '—') + '</td>');
				$tr.append('<td class="text-right">' + g.lineasIdx.length + '</td>');
				$tr.append('<td class="text-right">' + g.comprobantes.length + '</td>');
				$tr.append('<td class="text-right">' + g.archivos.length + '</td>');
				$resumen.append($tr);
			});
		}

		var $av = $('#wizard-oc-lineas-sin-origen-aviso');
		if (sinOrigen.length && grupos.length) {
			$av.find('.cant').text(sinOrigen.length);
			$av.removeClass('d-none');
		} else {
			$av.addClass('d-none');
		}
	}

	function renderTabsGrupos() {
		var $tabs = $('#wizard-oc-cabeceras-tabs').empty();
		var $panes = $('#wizard-oc-cabeceras').empty();

		if (!grupos.length) {
			$('#wizard-oc-cabeceras-card').addClass('d-none');
			return;
		}
		$('#wizard-oc-cabeceras-card').removeClass('d-none');

		var tpl = document.getElementById('wizard-oc-tab-template');
		if (!tpl || !tpl.content) {
			return;
		}

		grupos.forEach(function (g, idx) {
			var tabId = 'wz-oc-tab-' + idx;
			var paneId = 'wz-oc-pane-' + idx;
			var act = idx === 0 ? ' active' : '';
			var $li = $(
				'<li class="nav-item"><a class="nav-link' + act + '" data-toggle="tab" href="#' + paneId + '" id="' + tabId + '" role="tab">' +
					'OC ' + (idx + 1) + ' — ' + htmlEsc(proveedorNombre(g.proveedor_id) || '<sin prov.>') +
					'</a></li>'
			);
			$tabs.append($li);

			var node = document.importNode(tpl.content, true).firstElementChild;
			var $pane = $(node);
			$pane.attr('id', paneId);
			$pane.attr('data-gidx', idx);
			if (idx === 0) {
				$pane.addClass('show active');
			}

			$pane.find('.wz-grupo-proveedor-nombre').val(proveedorNombre(g.proveedor_id) || '');
			$pane.find('.wz-grupo-condicioncompra').html(selectOptionsHtml(CC_COMPRA, 'id', 'nombre', g.condicioncompra_id, true));
			$pane.find('.wz-grupo-condicionentrega').html(selectOptionsHtml(CC_ENTREGA, 'id', 'nombre', g.condicionentrega_id, true));
			$pane.find('.wz-grupo-condicionpago').html(selectOptionsHtml(CC_PAGO, 'id', 'nombre', g.condicionpago_id, true));
			$pane.find('.wz-grupo-transporte').html(selectOptionsHtml(TRANSPORTES, 'id', 'nombre', g.transporte_id, true));
			$pane.find('.wz-grupo-lugarentrega').val(g.lugarentrega || '');
			$pane.find('.wz-grupo-comentario').val(g.comentario || '');

			$panes.append($pane);
			pintarComprobantesGrupo(idx);
			pintarArchivosGrupo(idx);
		});
		wzScheduleRefrescarTotalesCabeceras();
	}

	function pintarComprobantesGrupo(gidx) {
		var $body = $('#wizard-oc-cabeceras [data-gidx="' + gidx + '"] .wz-grupo-comprobantes-body').empty();
		var g = grupos[gidx];
		if (!g || !g.comprobantes.length) {
			$body.append('<tr><td colspan="8" class="text-center text-muted small">Sin comprobantes a venir.</td></tr>');
			return;
		}
		g.comprobantes.forEach(function (c, idx) {
			var totalCuotas = (c.cuotas || []).reduce(function (acc, q) { return acc + (parseFloat(q.monto) || 0); }, 0);
			var $tr = $('<tr></tr>');
			$tr.append('<td>' + (idx + 1) + '</td>');
			$tr.append('<td>' + htmlEsc(c.tipocomprobante || '—') + '</td>');
			$tr.append('<td>' + htmlEsc(c.fechavencimiento || '—') + '</td>');
			$tr.append('<td class="text-right">' + fmtNum(c.monto, 2) + '</td>');
			$tr.append('<td>' + htmlEsc(monedaAbrev(c.moneda_id)) + '</td>');
			$tr.append('<td><span class="small">' + htmlEsc((c.detalle || '').substring(0, 80)) + '</span></td>');
			$tr.append('<td class="text-right small">' + (c.cuotas ? c.cuotas.length : 0) + ' (' + fmtNum(totalCuotas, 2) + ')</td>');
			$tr.append(
				'<td class="text-nowrap">' +
					'<button type="button" class="btn btn-sm btn-outline-primary wz-grupo-comp-editar" data-cidx="' + idx + '" title="Editar comprobante"><i class="fa fa-edit"></i></button> ' +
					'<button type="button" class="btn btn-sm btn-outline-info wz-grupo-comp-cuotas" data-cidx="' + idx + '" title="Editar cuotas"><i class="fa fa-list-ol"></i></button> ' +
					'<button type="button" class="btn btn-sm btn-outline-danger wz-grupo-comp-quitar" data-cidx="' + idx + '" title="Quitar comprobante"><i class="fa fa-times"></i></button>' +
					'</td>'
			);
			$body.append($tr);
		});
	}

	function pintarArchivosGrupo(gidx) {
		var $body = $('#wizard-oc-cabeceras [data-gidx="' + gidx + '"] .wz-grupo-archivos-body').empty();
		var g = grupos[gidx];
		if (!g || !g.archivos.length) {
			$body.append('<tr class="wz-grupo-archivos-vacio"><td colspan="4" class="text-center text-muted small">No hay archivos adjuntos.</td></tr>');
			return;
		}
		g.archivos.forEach(function (f, idx) {
			var $tr = $('<tr></tr>');
			$tr.append('<td>' + (idx + 1) + '</td>');
			$tr.append('<td>' + htmlEsc(f.name) + '</td>');
			$tr.append('<td class="text-right">' + tamanioHumano(f.size) + '</td>');
			$tr.append('<td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-danger wz-grupo-archivo-quitar" data-aidx="' + idx + '" title="Quitar archivo"><i class="fa fa-times"></i></button></td>');
			$body.append($tr);
		});
	}

	function actualizarBotonGenerar() {
		var $btn = $('#wizard-oc-btn-generar');
		var $hint = $('#wizard-oc-btn-generar-hint');
		var cantOcs = grupos.length;
		var puede = cantOcs > 0;

		$('#wizard-oc-btn-generar-cantidad').text(cantOcs);
		$btn.prop('disabled', !puede);

		if (cantOcs === 0) {
			$hint.text('Indique origen de precio o cargue un precio en al menos un ítem para detectar una OC.');
		} else if (!todosGruposTienenProveedor()) {
			$hint.text('Al generar se le pedirá el proveedor para la(s) OC que usen precio de la requisición.');
		} else {
			$hint.text('');
		}
	}

	// ---------------------------------------------------------------------
	// Cargar plantilla desde requisición
	// ---------------------------------------------------------------------
	function construirLineasDesdePlantilla(pl) {
		return (pl.articulos || []).map(function (a) {
			return {
				requisicion_articulo_id: a.requisicion_articulo_id,
				articulo_id: a.articulo_id,
				sku: a.sku || '',
				descripcion: (a.nombre_articulo_proveedor && String(a.nombre_articulo_proveedor).trim())
					? a.nombre_articulo_proveedor
					: (a.descripcion_articulo || ''),
				color_id: a.color_id || 0,
				talle_id: a.talle_id || 0,
				color_nombre: a.color_nombre || '',
				talle_nombre: a.talle_nombre || '',
				cantidad: a.cantidad,
				precio: a.precio,
				moneda_id: a.moneda_id,
				cotizacion: a.cotizacion || 1,
				fechaentrega: wizardFechaInput(a.fechaentrega),
				centrocostodestino_id: a.centrocostodestino_id || META.centrocosto_default_id,
				partidagasto_id: a.partidagasto_id || '',
				codigopartidagasto: a.codigopartidagasto || '',
				descripcionpartidagasto: a.descripcionpartidagasto || '',
				capex_id: a.capex_id || '',
				codigocapex: a.codigocapex || '',
				descripcioncapex: a.descripcioncapex || '',
				detalle: a.detalle || '',
				proveedor_id: parseInt(a.proveedor_id || 0, 10) || 0,
				articulo_proveedor_id: parseInt(a.articulo_proveedor_id || 0, 10) || 0,
				origen: null,
			};
		});
	}

	function aplicarCabeceraDesdePlantilla(pl) {
		if (pl.empresa_id) {
			$('#wz_empresa_id').val(pl.empresa_id);
		}
		if (pl.fecha) {
			$('#wz_fecha').val(wizardFechaInput(pl.fecha));
		}
		if (pl.fechaentrega) {
			$('#wz_fechaentrega').val(wizardFechaInput(pl.fechaentrega));
		}
		if (pl.centrocosto_id) {
			$('#wz_centrocosto_id').val(pl.centrocosto_id);
		}
		if (pl.comentario !== undefined) {
			$('#wz_comentario').val(pl.comentario);
		}
		if (pl.detalle !== undefined) {
			$('#wz_detalle').val(pl.detalle);
		}
		if (pl.tratamiento) {
			$('#wz_tratamiento').val(pl.tratamiento);
		}
		requisicionProveedorId = parseInt(pl.proveedor_id || 0, 10);

		window.wzCentrocostos = [];
		$('#wz_centrocosto_id option').each(function () {
			var v = parseInt($(this).val(), 10);
			if (Number.isFinite(v) && v > 0) {
				window.wzCentrocostos.push({ id: v, label: $(this).text().trim() });
			}
		});
	}

	function tablaArticulosRenderizadaEnServidor() {
		var tb = document.getElementById('wizard-oc-tabla-articulos-body');
		return !!(tb && tb.getAttribute('data-wz-ssr') === '1' && tb.querySelectorAll('.wizard-oc-fila-item').length > 0);
	}

	function hidratarPlantillaEmbebida(pl) {
		aplicarCabeceraDesdePlantilla(pl);
		lineas = construirLineasDesdePlantilla(pl);
		try {
			recalcGruposYRender();
		} catch (err) {
			// Mantener filas SSR aunque falle el panel de grupos.
		}
		window.__WZ_PLANTILLA_APLICADA__ = true;
		window.__WZ_LINEAS_HIDRATADAS__ = lineas.length > 0;
		return true;
	}

	function aplicarPlantilla(pl) {
		if (!pl || typeof pl !== 'object' || pl.message) {
			var msg = (pl && pl.message) ? pl.message : 'Respuesta inválida al cargar la requisición.';
			wizardMostrarErrorCarga(msg);
			return false;
		}
		aplicarCabeceraDesdePlantilla(pl);
		lineas = construirLineasDesdePlantilla(pl);

		renderArticulos();
		recalcGruposYRender();
		renderArticulos();
		window.__WZ_PLANTILLA_APLICADA__ = true;
		return true;
	}

	function cargarPlantilla() {
		var plEmbebida = readJsonScript('oc-wizard-plantilla');
		if (plEmbebida) {
			try {
				if (tablaArticulosRenderizadaEnServidor()) {
					return hidratarPlantillaEmbebida(plEmbebida);
				}
				if (aplicarPlantilla(plEmbebida)) {
					return;
				}
			} catch (err) {
				if (tablaArticulosRenderizadaEnServidor() && ensureWizardHidratado()) {
					return;
				}
				wizardMostrarErrorCarga('Error al mostrar ítems: ' + (err && err.message ? err.message : String(err)));
				return;
			}
		}

		if (tablaArticulosRenderizadaEnServidor()) {
			return ensureWizardHidratado();
		}

		var plantillaUrl = wzMetaEndpoint('plantilla');
		if (!plantillaUrl) {
			wizardMostrarErrorCarga('No se configuró la URL de plantilla del wizard.');
			return;
		}
		$.ajax({
			url: plantillaUrl,
			method: 'GET',
			data: { requisicion_id: META.requisicion_id },
			dataType: 'json',
			timeout: 120000,
		})
			.done(function (pl) {
				try {
					aplicarPlantilla(pl);
				} catch (err) {
					wizardMostrarErrorCarga('Error al mostrar ítems: ' + (err && err.message ? err.message : String(err)));
				}
			})
			.fail(function (xhr, status) {
				var detalle = '';
				if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
					detalle = ': ' + xhr.responseJSON.message;
				} else if (xhr && xhr.status) {
					detalle = ' (HTTP ' + xhr.status + ')';
				} else if (status === 'timeout') {
					detalle = ' (tiempo de espera agotado)';
				}
				wizardMostrarErrorCarga('No se pudo cargar la plantilla de la requisición' + detalle + '.');
			});
	}

	// ---------------------------------------------------------------------
	// Edición inline de cantidades / precios / fechas en cada línea
	// ---------------------------------------------------------------------
	$(document).on('input', '#wizard-oc-tabla-articulos-body .wz-lin-cantidad', function () {
		var idx = parseInt($(this).closest('tr').data('linIdx'), 10);
		if (lineas[idx]) {
			lineas[idx].cantidad = $(this).val();
		}
		wzScheduleRefrescarTotalesCabeceras();
	});
	$(document).on('input', '#wizard-oc-tabla-articulos-body .wz-lin-precio', function () {
		var idx = parseInt($(this).closest('tr').data('linIdx'), 10);
		if (lineas[idx]) {
			lineas[idx].precio = $(this).val();
		}
		wzScheduleRefrescarTotalesCabeceras();
	});
	$(document).on('input', '#wizard-oc-tabla-articulos-body .wz-lin-cotiz', function () {
		var idx = parseInt($(this).closest('tr').data('linIdx'), 10);
		if (lineas[idx]) {
			lineas[idx].cotizacion = $(this).val();
		}
		wzScheduleRefrescarTotalesCabeceras();
	});
	$(document).on('change', '#wizard-oc-tabla-articulos-body .wz-lin-moneda', function () {
		var idx = parseInt($(this).closest('tr').data('linIdx'), 10);
		if (lineas[idx]) {
			lineas[idx].moneda_id = parseInt($(this).val(), 10);
		}
		wzScheduleRefrescarTotalesCabeceras();
	});
	$(document).on('change', '#wizard-oc-tabla-articulos-body .wz-lin-fechaentrega', function () {
		var idx = parseInt($(this).closest('tr').data('linIdx'), 10);
		if (lineas[idx]) {
			lineas[idx].fechaentrega = $(this).val();
		}
	});
	$(document).on('change', '#wizard-oc-tabla-articulos-body .wz-lin-cc-destino', function () {
		var idx = parseInt($(this).closest('tr').data('linIdx'), 10);
		if (lineas[idx]) {
			lineas[idx].centrocostodestino_id = parseInt($(this).val(), 10);
		}
	});

	// ---------------------------------------------------------------------
	// Modal de origen de precio por línea
	// ---------------------------------------------------------------------
	$(document).on('click', '.wz-lin-btn-origen', function () {
		if (!ensureWizardHidratado()) {
			alert('No se pudo inicializar el wizard. Recargue la página (Ctrl+F5).');
			return;
		}
		var $tr = $(this).closest('tr');
		var idx = indiceLineaDesdeFila($tr);
		var lin = asegurarLineaEnIndice($tr, idx);
		if (!lin || !lin.requisicion_articulo_id || !lin.articulo_id) {
			alert('No se pudo identificar la línea de requisición.');
			return;
		}
		pendingPrecioRow = idx;

		var $modal = $('#modalOcOrigenPrecio');
		$('#modalOcOrigenPrecioError').addClass('d-none').text('');
		$('#modalOcOrigenPrecioOpciones').empty();
		$('#modalOcOrigenPrecioManual').addClass('d-none');
		$('#modalOcOrigenPrecioCargando').removeClass('d-none');
		$('#modalOcOrigenPrecioSubtitulo').text(lin.sku + ' — ' + lin.descripcion);
		$modal.modal('show');

		$.get(wzMetaEndpoint('opciones'), {
			requisicion_id: META.requisicion_id,
			requisicion_articulo_id: lin.requisicion_articulo_id,
			articulo_id: lin.articulo_id,
			fecha_referencia: $('#wz_fecha').val() || '',
			proveedor_id: requisicionProveedorId > 0 ? requisicionProveedorId : '',
		})
			.done(function (data) {
				$('#modalOcOrigenPrecioCargando').addClass('d-none');
				if (!data || data.message) {
					$('#modalOcOrigenPrecioError').removeClass('d-none').text((data && data.message) || 'Sin datos.');
					$('#modalOcOrigenPrecioManual').removeClass('d-none');
					return;
				}
				var $box = $('#modalOcOrigenPrecioOpciones');
				var cantOpciones = 0;

				function btnOrigen(opt) {
					cantOpciones++;
					var mid = opt.moneda_id || META.moneda_peso_id;
					var sub =
						'Precio: ' + fmtNum(opt.precio, 4) + ' ' + monedaAbrev(mid) +
						(opt.proveedor_id ? ' · Prov: ' + htmlEsc(proveedorNombre(opt.proveedor_id) || ('#' + opt.proveedor_id)) : '');
					var $b = $('<button type="button" class="btn btn-outline-primary btn-block text-left mb-2"/>');
					$b.html(
						'<strong>' + htmlEsc(opt.etiqueta || opt.tipo) + '</strong><br>' +
							'<span class="small text-muted">' + sub + '</span>'
					);
					$b.on('click', function () {
						aplicarOrigenPrecio({
							tipo: opt.tipo,
							ref_id: opt.ref_id,
							etiqueta: opt.etiqueta || '',
							precio: opt.precio,
							moneda_id: mid,
							proveedor_id: opt.proveedor_id || 0,
							condicioncompra_id: opt.condicioncompra_id || 0,
							condicionentrega_id: opt.condicionentrega_id || 0,
							condicionpago_id: opt.condicionpago_id || 0,
						});
					});
					$box.append($b);
				}

				if (data.opcion_requisicion) {
					var oq = data.opcion_requisicion;
					var precioReq = parseFloat(oq.precio);
					btnOrigen({
						tipo: oq.origen || 'REQUISICION',
						ref_id: oq.ref_id,
						etiqueta: precioReq > 0
							? (oq.etiqueta || 'Precio cargado en la requisición')
							: 'Precio en requisición (cero) — indique proveedor después',
						precio: oq.precio,
						moneda_id: oq.moneda_id,
						proveedor_id: oq.proveedor_id || 0,
						condicioncompra_id: 0,
						condicionentrega_id: 0,
						condicionpago_id: 0,
					});
				}
				(data.opciones_lista || []).forEach(function (L) {
					btnOrigen({
						tipo: L.origen || 'LISTA_PROVEEDOR',
						ref_id: L.ref_id,
						etiqueta: L.etiqueta || 'Lista proveedor',
						precio: L.precio,
						moneda_id: L.moneda_id,
						proveedor_id: L.proveedor_id,
						condicioncompra_id: L.condicioncompra_id,
						condicionentrega_id: L.condicionentrega_id,
						condicionpago_id: L.condicionpago_id,
					});
				});
				(data.opciones_presupuesto || []).forEach(function (P) {
					btnOrigen({
						tipo: P.origen || 'PRESUPUESTO',
						ref_id: P.ref_id,
						etiqueta: P.etiqueta || 'Presupuesto',
						precio: P.precio,
						moneda_id: P.moneda_id,
						proveedor_id: P.proveedor_id,
						condicioncompra_id: P.condicioncompra_id,
						condicionentrega_id: P.condicionentrega_id,
						condicionpago_id: P.condicionpago_id,
					});
				});

				$('#modalOcOrigenPrecioManual').removeClass('d-none');
				if (cantOpciones === 0) {
					$('#modalOcOrigenPrecioError').removeClass('d-none').text(
						'No hay listas de precio ni presupuestos para este ítem. Use el botón inferior para elegir proveedor.'
					);
				}
			})
			.fail(function (xhr) {
				$('#modalOcOrigenPrecioCargando').addClass('d-none');
				$('#modalOcOrigenPrecioManual').removeClass('d-none');
				$('#modalOcOrigenPrecioError').removeClass('d-none').text((xhr.responseJSON && xhr.responseJSON.message) || 'Error al cargar opciones.');
			});
	});

	$(document).on('click', '.wz-lin-btn-proveedor', function () {
		abrirElegirProveedorLinea($(this).closest('tr'));
	});

	$(document).on('click', '#modalOcOrigenPrecioBtnProveedor', function () {
		if (pendingPrecioRow == null || !lineas[pendingPrecioRow]) {
			return;
		}
		window.wzCambiarProveedorLinea = pendingPrecioRow;
		window.wzCambiarProveedorGrupo = null;
		$('#modalOcOrigenPrecio').modal('hide');
		abrirModalConsultaProveedor();
	});

	function aplicarOrigenPrecio(origen, idxOverride) {
		var idx = typeof idxOverride === 'number' ? idxOverride : pendingPrecioRow;
		if (idx == null || !lineas[idx]) {
			return;
		}
		var lin = lineas[idx];
		lin.origen = origen;
		if (origen.precio != null && origen.precio !== '') {
			lin.precio = origen.precio;
		}
		if (origen.moneda_id) {
			lin.moneda_id = origen.moneda_id;
		}
		pendingPrecioRow = null;
		$('#modalOcOrigenPrecio').modal('hide');
		actualizarFilaOrigenEnDom(idx);
		recalcGruposYRender();
	}

	function actualizarFilaOrigenEnDom(idx) {
		var lin = lineas[idx];
		if (!lin) {
			return;
		}
		var $tr = $('#wizard-oc-tabla-articulos-body tr.wizard-oc-fila-item[data-lin-idx="' + idx + '"]');
		if (!$tr.length) {
			renderArticulos();
			return;
		}
		$tr.find('.wz-lin-precio').val(lin.precio);
		$tr.find('.wz-lin-moneda').val(lin.moneda_id);
		var tieneOrigen = !!lin.origen;
		var pillClass = tieneOrigen ? 'con-origen' : 'sin-origen';
		var pillTexto = tieneOrigen
			? htmlEsc(lin.origen.etiqueta || lin.origen.tipo) +
				(lin.origen.proveedor_id
					? '<br><small>' + htmlEsc(proveedorNombre(lin.origen.proveedor_id) || '—') + '</small>'
					: '')
			: '<em>Sin origen</em>';
		$tr.find('.wizard-oc-origen-pill')
			.removeClass('sin-origen con-origen')
			.addClass(pillClass)
			.html(pillTexto);
	}

	// ---------------------------------------------------------------------
	// Edición de cabecera por grupo
	// ---------------------------------------------------------------------
	$(document).on('change', '.wz-grupo-condicioncompra', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		if (grupos[gidx]) {
			grupos[gidx].condicioncompra_id = parseInt($(this).val() || '0', 10);
		}
	});
	$(document).on('change', '.wz-grupo-condicionentrega', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		if (grupos[gidx]) {
			grupos[gidx].condicionentrega_id = parseInt($(this).val() || '0', 10);
		}
	});
	$(document).on('change', '.wz-grupo-condicionpago', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		if (grupos[gidx]) {
			grupos[gidx].condicionpago_id = parseInt($(this).val() || '0', 10);
		}
	});
	$(document).on('change', '.wz-grupo-transporte', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		if (grupos[gidx]) {
			grupos[gidx].transporte_id = parseInt($(this).val() || '0', 10);
		}
	});
	$(document).on('input', '.wz-grupo-lugarentrega', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		if (grupos[gidx]) {
			grupos[gidx].lugarentrega = $(this).val();
		}
	});
	$(document).on('input', '.wz-grupo-comentario', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		if (grupos[gidx]) {
			grupos[gidx].comentario = $(this).val();
		}
	});

	/*
	 * Cambiar proveedor de un grupo.
	 *
	 * El modal de consulta de proveedores existente (`#consultaproveedorModal`)
	 * fue diseñado para escribir en los inputs globales `#proveedor_id`,
	 * `#codigoproveedor` y `#nombreproveedor`. Aprovechamos eso: dejamos esos
	 * inputs en el DOM (ocultos) y, al detectar cambio, asignamos el valor al
	 * grupo en contexto.
	 */
	$(document).on('click', '.wz-grupo-proveedor-buscar', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		window.wzCambiarProveedorGrupo = gidx;
		window.wzCambiarProveedorLinea = null;
		abrirModalConsultaProveedor();
	});

	$(document).on('click', '#consultaproveedorModal .eligeconsultaproveedor', function (e) {
		if (typeof window.wzCambiarProveedorLinea !== 'number' && typeof window.wzCambiarProveedorGrupo !== 'number') {
			return;
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		var pid = leerProveedorIdDesdeFilaConsulta($(this));
		if (!Number.isFinite(pid) || pid <= 0) {
			return;
		}

		if (typeof window.wzCambiarProveedorLinea === 'number') {
			var idxLinea = window.wzCambiarProveedorLinea;
			var linProv = lineas[idxLinea];
			if (!linProv) {
				return;
			}
			sincronizarLineaDesdeDom(idxLinea);
			aplicarOrigenPrecio({
				tipo: 'REQUISICION',
				ref_id: linProv.requisicion_articulo_id,
				etiqueta: 'Proveedor elegido manualmente',
				precio: linProv.precio,
				moneda_id: linProv.moneda_id,
				proveedor_id: pid,
				condicioncompra_id: 0,
				condicionentrega_id: 0,
				condicionpago_id: 0,
			}, idxLinea);
			window.wzCambiarProveedorLinea = null;
			$('#consultaproveedorModal').modal('hide');
			return;
		}

		if (typeof window.wzCambiarProveedorGrupo !== 'number') {
			return;
		}
		var gidx = window.wzCambiarProveedorGrupo;
		if (!grupos[gidx]) {
			return;
		}
		asignarProveedorAGrupo(gidx, pid);
		window.wzCambiarProveedorGrupo = null;
		$('#consultaproveedorModal').modal('hide');
	});

	// ---------------------------------------------------------------------
	// Comprobantes a venir (modal compartido)
	// ---------------------------------------------------------------------
	$(document).on('click', '.wz-grupo-btn-agregar-comprobante', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		abrirModalComprobante(gidx, -1);
	});

	$(document).on('click', '.wz-grupo-comp-editar', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		var cidx = parseInt($(this).data('cidx'), 10);
		abrirModalComprobante(gidx, cidx);
	});

	$(document).on('click', '.wz-grupo-comp-quitar', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		var cidx = parseInt($(this).data('cidx'), 10);
		if (!grupos[gidx]) {
			return;
		}
		if (!window.confirm('¿Quitar este comprobante del grupo?')) {
			return;
		}
		grupos[gidx].comprobantes.splice(cidx, 1);
		pintarComprobantesGrupo(gidx);
		renderGruposResumen(lineasSinOrigenParaResumen());
	});

	function wzScheduleRefrescarTotalesCabeceras() {
		if (wzTotalesCabTimer) {
			clearTimeout(wzTotalesCabTimer);
		}
		wzTotalesCabTimer = setTimeout(function () {
			wzTotalesCabTimer = null;
			wzRefrescarTotalesCabecerasOcsImpl();
		}, 350);
	}

	function wzTextoTotalesUnaLinea(tot) {
		var m = tot.moneda_abrev || monedaAbrev(tot.moneda_id);
		return (
			'Bruto ' +
			fmtNum(tot.subtotal_bruto_sin_iva, 2) +
			' ' +
			m +
			' · Desc. ' +
			fmtNum(tot.importe_descuento, 2) +
			' ' +
			m +
			' · IVA ' +
			fmtNum(tot.iva_total, 2) +
			' ' +
			m +
			' · Total ' +
			fmtNum(tot.total, 2) +
			' ' +
			m
		);
	}

	function wzRefrescarTotalesCabecerasOcsImpl() {
		var myReq = ++wzTotalesCabReqId;
		if (!$('#wizard-oc-cabeceras').length) {
			return;
		}
		grupos.forEach(function (g, gidx) {
			var $text = $('#wizard-oc-cabeceras [data-gidx="' + gidx + '"] .wz-grupo-totales-text');
			if (!$text.length) {
				return;
			}
			$text.text('Actualizando…');
			wzFetchTotalesGrupo(gidx, function (tot) {
				if (myReq !== wzTotalesCabReqId) {
					return;
				}
				if (!tot) {
					$text.text('Sin importes (revise cantidad/precio o moneda de las líneas de esta OC).');
					return;
				}
				if (tot._fail) {
					$text.addClass('text-danger').text('No se pudo calcular importes.');
					return;
				}
				$text.removeClass('text-danger').text(wzTextoTotalesUnaLinea(tot));
			});
		});
	}

	function wzRound2(x) {
		var n = Number(x);
		if (!Number.isFinite(n)) {
			return 0;
		}
		return Math.round(n * 100) / 100;
	}

	function wzFetchTotalesGrupo(gidx, done) {
		var g = grupos[gidx];
		if (!g || (!META.calcular_totales_path && !META.calcular_totales_url)) {
			done(null);
			return;
		}
		var liDel = g.lineasIdx.map(function (i) {
			return lineas[i];
		}).filter(Boolean);
		var articulo_ids = [];
		var cantidades = [];
		var precios = [];
		var moneda_linea_ids = [];
		var cotizaciones_linea = [];
		liDel.forEach(function (l) {
			if (!l.articulo_id || !(parseFloat(l.cantidad) > 0)) {
				return;
			}
			articulo_ids.push(l.articulo_id);
			cantidades.push(l.cantidad);
			precios.push(parseFloat(l.precio) || 0);
			moneda_linea_ids.push(parseInt(l.moneda_id, 10) || 1);
			var c = parseFloat(l.cotizacion);
			cotizaciones_linea.push(c > 0 ? c : 1);
		});
		if (!articulo_ids.length) {
			done(null);
			return;
		}
		$.post(wzMetaEndpoint('calcular_totales'), {
			_token: META.csrf,
			fecha: $('#wz_fecha').val() || '',
			descuento: $('#wz_descuento').val() || '',
			descuento_tipo: $('#wz_descuento_tipo').val() || 'porcentaje',
			articulo_ids: articulo_ids,
			cantidades: cantidades,
			precios: precios,
			moneda_linea_ids: moneda_linea_ids,
			cotizaciones_linea: cotizaciones_linea
		})
			.done(function (res) {
				if (res && typeof res === 'object' && res.total != null) {
					var mid = parseInt(res.moneda_id, 10) || 1;
					done({
						total: wzRound2(parseFloat(res.total) || 0),
						moneda_id: mid,
						moneda_abrev: (res.moneda_abrev && String(res.moneda_abrev)) || monedaAbrev(mid),
						subtotal_bruto_sin_iva: wzRound2(parseFloat(res.subtotal_bruto_sin_iva) || 0),
						importe_descuento: wzRound2(parseFloat(res.importe_descuento) || 0),
						neto_sin_iva: wzRound2(parseFloat(res.neto_sin_iva) || 0),
						iva_total: wzRound2(parseFloat(res.iva_total) || 0)
					});
				} else {
					done(null);
				}
			})
			.fail(function () {
				done({ _fail: true });
			});
	}

	function wzMontoPendienteCompVenir(g, monedaRefId, totalRef) {
		var sum = 0;
		(g.comprobantes || []).forEach(function (c) {
			if (parseInt(c.moneda_id, 10) === monedaRefId && c.monto != null) {
				sum += parseFloat(c.monto) || 0;
			}
		});
		return wzRound2(Math.max(0, totalRef - sum));
	}

	function abrirModalComprobante(gidx, cidx) {
		compEditCtx = { grupoIdx: gidx, compIdx: cidx };
		var g = grupos[gidx];
		var c = cidx >= 0 ? g.comprobantes[cidx] : null;
		$('#oc_comp_cab_idx').val(cidx);
		$('#oc_comp_cab_tipo').val(c ? c.tipocomprobante : 'FACTURA');
		$('#oc_comp_cab_fecha').val(c ? (c.fechavencimiento || '') : $('#wz_fechaentrega').val());
		$('#oc_comp_cab_detalle').val(c ? (c.detalle || '') : '');

		function aplicarMonedaMontoDefecto(montoVal, monedaVal) {
			$('#oc_comp_cab_moneda').val(String(monedaVal));
			if (montoVal != null && montoVal > 0) {
				$('#oc_comp_cab_monto').val(montoVal);
			} else {
				$('#oc_comp_cab_monto').val('');
			}
		}

		if (c) {
			aplicarMonedaMontoDefecto(c.monto, c.moneda_id);
			$('#modalOcComprobanteCabecera').modal('show');
			return;
		}

		wzFetchTotalesGrupo(gidx, function (tot) {
			if (tot && tot.total > 0) {
				var pend = wzMontoPendienteCompVenir(g, tot.moneda_id, tot.total);
				if (pend > 0) {
					aplicarMonedaMontoDefecto(pend, tot.moneda_id);
				} else {
					aplicarMonedaMontoDefecto(null, META.moneda_peso_id);
				}
			} else {
				aplicarMonedaMontoDefecto(null, META.moneda_peso_id);
			}
			$('#modalOcComprobanteCabecera').modal('show');
		});
	}

	$(document).on('click', '#oc_comp_cab_guardar', function () {
		var ctx = compEditCtx;
		if (ctx.grupoIdx < 0 || !grupos[ctx.grupoIdx]) {
			return;
		}
		var monto = parseFloat($('#oc_comp_cab_monto').val());
		if (!Number.isFinite(monto) || monto <= 0) {
			alert('Indique un monto válido.');
			return;
		}
		var obj = {
			tipocomprobante: $('#oc_comp_cab_tipo').val(),
			fechavencimiento: $('#oc_comp_cab_fecha').val(),
			monto: monto,
			moneda_id: parseInt($('#oc_comp_cab_moneda').val(), 10),
			cotizacion: 1,
			detalle: $('#oc_comp_cab_detalle').val() || '',
			cantidadcuota: null,
			condicionpago_id: null,
			cuotas: [],
		};
		var g = grupos[ctx.grupoIdx];
		if (ctx.compIdx >= 0 && g.comprobantes[ctx.compIdx]) {
			// Conservar las cuotas previas si las hay
			obj.cuotas = g.comprobantes[ctx.compIdx].cuotas || [];
			obj.cantidadcuota = g.comprobantes[ctx.compIdx].cantidadcuota;
			obj.condicionpago_id = g.comprobantes[ctx.compIdx].condicionpago_id;
			g.comprobantes[ctx.compIdx] = obj;
		} else {
			g.comprobantes.push(obj);
			compEditCtx.compIdx = g.comprobantes.length - 1;
		}
		$('#modalOcComprobanteCabecera').modal('hide');
		pintarComprobantesGrupo(ctx.grupoIdx);
		renderGruposResumen(lineasSinOrigenParaResumen());
	});

	// Editor de cuotas (botón "Editar cuotas" en cada fila)
	$(document).on('click', '.wz-grupo-comp-cuotas', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		var cidx = parseInt($(this).data('cidx'), 10);
		var g = grupos[gidx];
		if (!g || !g.comprobantes[cidx]) {
			return;
		}
		compEditCtx = { grupoIdx: gidx, compIdx: cidx };
		abrirEditorCuotas(g.comprobantes[cidx]);
	});

	function abrirEditorCuotas(c) {
		$('#oc_cuotas_comp_idx').val(compEditCtx.compIdx);
		$('#oc_cuotas_comp_detalle_text').text(
			(c.tipocomprobante || '—') + ' · ' + (c.fechavencimiento || '') + ' · ' + fmtNum(c.monto, 2) + ' ' + monedaAbrev(c.moneda_id)
		);
		$('#oc_cuotas_monto_calc').val(c.monto);
		$('#oc_cuotas_moneda_calc').val(c.moneda_id);
		$('#oc_cuotas_fecha_base').val(c.fechavencimiento || $('#wz_fechaentrega').val());
		$('#oc_cuotas_condicionpago_id').val(c.condicionpago_id || '');
		$('#oc_cuotas_cantidad_manual').val(1);
		$('#oc_cuotas_fecha_primera_manual').val(c.fechavencimiento || $('#wz_fechaentrega').val());
		pintarCuotasTabla(c.cuotas || []);
		actualizarResumenCuotas(c);
		$('#modalOcComprobanteCuotas').modal('show');
	}

	function pintarCuotasTabla(cuotas) {
		var $b = $('#oc_cuotas_tbody').empty();
		cuotas.forEach(function (q, i) {
			var $tr = $('<tr></tr>');
			$tr.append('<td>' + (i + 1) + '</td>');
			$tr.append('<td><input type="date" class="form-control form-control-sm wz-cuota-fecha" value="' + htmlEsc(q.fechavencimiento || '') + '"></td>');
			$tr.append('<td><input type="number" step="0.01" class="form-control form-control-sm wz-cuota-monto" value="' + htmlEsc(q.monto || '0') + '"></td>');
			$tr.append('<td><select class="form-control form-control-sm wz-cuota-moneda">' + selectOptionsHtml(MONEDAS, 'id', 'abrev', q.moneda_id, false) + '</select></td>');
			$tr.append('<td><select class="form-control form-control-sm wz-cuota-formapago">' + selectOptionsHtml(FORMAPAGOS, 'id', 'nombre', q.formapago_id || 0, true) + '</select></td>');
			$tr.append('<td><input type="text" class="form-control form-control-sm wz-cuota-detalle" maxlength="255" value="' + htmlEsc(q.detalle || '') + '"></td>');
			$tr.append('<td><button type="button" class="btn btn-sm btn-outline-danger wz-cuota-quitar"><i class="fa fa-times"></i></button></td>');
			$b.append($tr);
		});
	}

	$(document).on('click', '.wz-cuota-quitar', function () {
		$(this).closest('tr').remove();
	});

	$(document).on('click', '#oc_cuotas_agregar_fila', function () {
		var $b = $('#oc_cuotas_tbody');
		var i = $b.children().length;
		var q = { fechavencimiento: $('#wz_fechaentrega').val(), monto: 0, moneda_id: parseInt($('#oc_cuotas_moneda_calc').val(), 10), formapago_id: null, detalle: '' };
		pintarCuotasTabla(leerCuotasModal().concat([q]));
		$b.find('tr').eq(i).find('input').first().trigger('focus');
	});

	function leerCuotasModal() {
		var out = [];
		$('#oc_cuotas_tbody tr').each(function () {
			var $tr = $(this);
			out.push({
				fechavencimiento: $tr.find('.wz-cuota-fecha').val() || '',
				monto: parseFloat($tr.find('.wz-cuota-monto').val()) || 0,
				moneda_id: parseInt($tr.find('.wz-cuota-moneda').val(), 10),
				cotizacion: 1,
				formapago_id: parseInt($tr.find('.wz-cuota-formapago').val() || '0', 10) || null,
				detalle: $tr.find('.wz-cuota-detalle').val() || '',
			});
		});
		return out;
	}

	function actualizarResumenCuotas(c) {
		var cuotas = leerCuotasModal();
		var total = cuotas.reduce(function (acc, q) { return acc + (parseFloat(q.monto) || 0); }, 0);
		var falta = (parseFloat(c.monto) || 0) - total;
		$('#oc_cuotas_resumen_monto_ref').text(fmtNum(c.monto, 2) + ' ' + monedaAbrev(c.moneda_id));
		$('#oc_cuotas_resumen_total_cuotas').text(fmtNum(total, 2));
		$('#oc_cuotas_resumen_falta').text(fmtNum(falta, 2));
	}

	$(document).on('input change', '#oc_cuotas_tbody input, #oc_cuotas_tbody select', function () {
		var ctx = compEditCtx;
		if (ctx.grupoIdx < 0 || !grupos[ctx.grupoIdx] || !grupos[ctx.grupoIdx].comprobantes[ctx.compIdx]) {
			return;
		}
		actualizarResumenCuotas(grupos[ctx.grupoIdx].comprobantes[ctx.compIdx]);
	});

	$(document).on('click', '#oc_cuotas_btn_generar_cond', function () {
		var cpId = parseInt($('#oc_cuotas_condicionpago_id').val() || '0', 10);
		var monto = parseFloat($('#oc_cuotas_monto_calc').val()) || 0;
		var monedaId = parseInt($('#oc_cuotas_moneda_calc').val(), 10);
		var fechaBase = $('#oc_cuotas_fecha_base').val();
		if (!cpId || monto <= 0 || !fechaBase) {
			alert('Indique condición de pago, fecha base y monto.');
			return;
		}
		$.post(wzMetaEndpoint('sugerir_cuotas'), {
			_token: META.csrf,
			condicionpago_id: cpId,
			fecha_base: fechaBase,
			monto: monto,
			moneda_id: monedaId,
		})
			.done(function (res) {
				pintarCuotasTabla(res.cuotas || []);
				var ctx = compEditCtx;
				if (ctx.grupoIdx >= 0 && grupos[ctx.grupoIdx] && grupos[ctx.grupoIdx].comprobantes[ctx.compIdx]) {
					actualizarResumenCuotas(grupos[ctx.grupoIdx].comprobantes[ctx.compIdx]);
				}
			})
			.fail(function (xhr) {
				alert((xhr.responseJSON && xhr.responseJSON.message) || 'Error al sugerir cuotas.');
			});
	});

	$(document).on('click', '#oc_cuotas_btn_crear_manual, #oc_cuotas_btn_mensual', function () {
		var n = parseInt($('#oc_cuotas_cantidad_manual').val() || '1', 10);
		if (n < 1) {
			n = 1;
		}
		var monto = parseFloat($('#oc_cuotas_monto_calc').val()) || 0;
		var fechaPrimera = $('#oc_cuotas_fecha_primera_manual').val() || $('#wz_fechaentrega').val();
		var monedaId = parseInt($('#oc_cuotas_moneda_calc').val(), 10);
		var monto_unitario = n > 0 ? Math.round((monto / n) * 100) / 100 : 0;
		var mensual = $(this).attr('id') === 'oc_cuotas_btn_mensual';
		var nuevas = [];
		for (var i = 0; i < n; i++) {
			var fv = fechaPrimera;
			if (mensual && fechaPrimera) {
				var d = new Date(fechaPrimera + 'T00:00:00');
				d.setMonth(d.getMonth() + i);
				fv = d.toISOString().substring(0, 10);
			}
			nuevas.push({ fechavencimiento: fv, monto: monto_unitario, moneda_id: monedaId, cotizacion: 1, formapago_id: null, detalle: 'Cuota ' + (i + 1) });
		}
		pintarCuotasTabla(nuevas);
		var ctx = compEditCtx;
		if (ctx.grupoIdx >= 0 && grupos[ctx.grupoIdx] && grupos[ctx.grupoIdx].comprobantes[ctx.compIdx]) {
			actualizarResumenCuotas(grupos[ctx.grupoIdx].comprobantes[ctx.compIdx]);
		}
	});

	$(document).on('click', '#oc_cuotas_guardar', function () {
		var ctx = compEditCtx;
		if (ctx.grupoIdx < 0 || !grupos[ctx.grupoIdx] || !grupos[ctx.grupoIdx].comprobantes[ctx.compIdx]) {
			return;
		}
		var c = grupos[ctx.grupoIdx].comprobantes[ctx.compIdx];
		c.cuotas = leerCuotasModal();
		c.cantidadcuota = c.cuotas.length;
		c.condicionpago_id = parseInt($('#oc_cuotas_condicionpago_id').val() || '0', 10) || null;
		$('#modalOcComprobanteCuotas').modal('hide');
		pintarComprobantesGrupo(ctx.grupoIdx);
		renderGruposResumen(lineasSinOrigenParaResumen());
	});

	// ---------------------------------------------------------------------
	// Archivos a subir por grupo
	// ---------------------------------------------------------------------
	$(document).on('click', '.wz-grupo-btn-agregar-archivo', function () {
		var $input = $(this).closest('.card-header').find('.wz-grupo-archivo-input');
		$input.trigger('click');
	});

	$(document).on('change', '.wz-grupo-archivo-input', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		if (!grupos[gidx]) {
			return;
		}
		var files = this.files;
		for (var i = 0; i < files.length; i++) {
			grupos[gidx].archivos.push(files[i]);
		}
		this.value = '';
		pintarArchivosGrupo(gidx);
		renderGruposResumen(lineasSinOrigenParaResumen());
	});

	$(document).on('click', '.wz-grupo-archivo-quitar', function () {
		var gidx = parseInt($(this).closest('.tab-pane').data('gidx'), 10);
		var aidx = parseInt($(this).data('aidx'), 10);
		if (!grupos[gidx]) {
			return;
		}
		grupos[gidx].archivos.splice(aidx, 1);
		pintarArchivosGrupo(gidx);
		renderGruposResumen(lineasSinOrigenParaResumen());
	});

	// ---------------------------------------------------------------------
	// Generar OCs: confirmación + POST multipart + resultado
	// ---------------------------------------------------------------------
	function armarPayloadGrupo(gidx) {
		var g = grupos[gidx];
		if (!g) {
			return null;
		}
		var liDel = g.lineasIdx.map(function (i) { return lineas[i]; });
		var p = {
			_token: META.csrf,
			fecha: $('#wz_fecha').val(),
			fechaentrega: $('#wz_fechaentrega').val(),
			empresa_id: $('#wz_empresa_id').val(),
			centrocosto_id: $('#wz_centrocosto_id').val(),
			tratamiento: $('#wz_tratamiento').val(),
			detalle: $('#wz_detalle').val() || '',
			descuento: $('#wz_descuento').val() || '',
			descuento_tipo: $('#wz_descuento_tipo').val() || 'porcentaje',
			comentario: g.comentario || $('#wz_comentario').val() || '',
			requisicion_id: String(META.requisicion_id),
			proveedor_id: g.proveedor_id ? String(g.proveedor_id) : '',
			codigoproveedor: '',
			nombreproveedor: '',
			condicioncompra_id: g.condicioncompra_id ? String(g.condicioncompra_id) : '',
			condicionentrega_id: g.condicionentrega_id ? String(g.condicionentrega_id) : '',
			condicionpago_id: g.condicionpago_id ? String(g.condicionpago_id) : '',
			transporte_id: g.transporte_id ? String(g.transporte_id) : '',
			lugarentrega: g.lugarentrega || '',
			comprobantes_json: JSON.stringify(g.comprobantes || []),
			// Arrays de líneas
			articulo_ids: liDel.map(function (l) { return l.articulo_id; }),
			colores_id: liDel.map(function (l) { return l.color_id || ''; }),
			talles_id: liDel.map(function (l) { return l.talle_id || ''; }),
			cantidades: liDel.map(function (l) { return l.cantidad; }),
			precios: liDel.map(function (l) { return l.precio; }),
			moneda_linea_ids: liDel.map(function (l) { return l.moneda_id; }),
			cotizaciones_linea: liDel.map(function (l) { return l.cotizacion || 1; }),
			fechaentrega_articulos: liDel.map(function (l) { return l.fechaentrega || $('#wz_fechaentrega').val(); }),
			centrocostodestino_ids: liDel.map(function (l) { return l.centrocostodestino_id; }),
			partidagasto_ids: liDel.map(function (l) { return l.partidagasto_id; }),
			codigopartidagastos: liDel.map(function (l) { return l.codigopartidagasto; }),
			descripcionpartidagastos: liDel.map(function (l) { return l.descripcionpartidagasto; }),
			capex_ids: liDel.map(function (l) { return l.capex_id; }),
			codigocapexs: liDel.map(function (l) { return l.codigocapex; }),
			descripcioncapexs: liDel.map(function (l) { return l.descripcioncapex; }),
			detalle_articulos: liDel.map(function (l) { return l.detalle || ''; }),
			requisicion_articulo_ids: liDel.map(function (l) { return l.requisicion_articulo_id; }),
			articulo_proveedor_ids: liDel.map(function (l) { return l.articulo_proveedor_id || ''; }),
			precio_origen_tipos: liDel.map(function (l) { return l.origen ? l.origen.tipo : ''; }),
			precio_origen_ref_ids: liDel.map(function (l) { return l.origen && l.origen.ref_id != null ? l.origen.ref_id : ''; }),
			precio_origen_etiquetas: liDel.map(function (l) { return l.origen ? l.origen.etiqueta : ''; }),
			ordencompra_articulo_ids: liDel.map(function () { return ''; }),
			descuentos_linea: liDel.map(function () { return ''; }),
			cantidadalternativas: liDel.map(function () { return ''; }),
			codigoarticulos: liDel.map(function (l) { return l.sku || ''; }),
			descripcionarticulos: liDel.map(function (l) { return l.descripcion || ''; }),
		};
		return p;
	}

	function renderModalProveedorFaltante() {
		var $body = $('#wz-proveedor-faltante-lista').empty();
		var pendientes = 0;
		grupos.forEach(function (g, idx) {
			if (normId(g.proveedor_id) > 0) {
				return;
			}
			pendientes++;
			var $tr = $('<tr></tr>');
			$tr.append('<td class="text-center">' + (idx + 1) + '</td>');
			$tr.append('<td class="text-right">' + g.lineasIdx.length + '</td>');
			$tr.append(
				'<td class="wz-prov-faltante-nombre">' +
					(proveedorNombre(g.proveedor_id) || '<span class="text-danger">Sin asignar</span>') +
					'</td>'
			);
			$tr.append(
				'<td class="text-nowrap">' +
					'<button type="button" class="btn btn-sm btn-outline-primary wz-prov-faltante-buscar" data-gidx="' + idx + '" title="Elegir proveedor">' +
					'<i class="fa fa-search"></i> Elegir</button></td>'
			);
			$body.append($tr);
		});
		$('#wz-proveedor-faltante-continuar').prop('disabled', pendientes > 0);
	}

	function abrirConfirmGenerar() {
		var sinOrigen = [];
		lineas.forEach(function (l) {
			if (!l.origen) {
				sinOrigen.push(l.requisicion_articulo_id);
			}
		});
		var cantOcs = grupos.length;

		$('#wz-confirm-cantidad').text(cantOcs);
		var $det = $('#wz-confirm-detalle').empty();
		grupos.forEach(function (g, i) {
			$det.append(
				'<li>OC ' + (i + 1) + ': ' + htmlEsc(proveedorNombre(g.proveedor_id)) +
					' — ' + g.lineasIdx.length + ' ítem(s), ' + g.comprobantes.length + ' comprobante(s), ' + g.archivos.length + ' archivo(s)</li>'
			);
		});
		if (sinOrigen.length) {
			$('#wz-confirm-sin-origen-aviso').show().find('.cant').text(sinOrigen.length);
		} else {
			$('#wz-confirm-sin-origen-aviso').hide();
		}
		$('#modalWizardConfirmGenerar').modal('show');
	}

	$(document).on('click', '#wizard-oc-btn-generar', function () {
		aplicarFallbackOrigenEnLineas();
		recalcGruposYRender();

		var cantOcs = grupos.length;
		if (cantOcs === 0) {
			alert('Debe haber al menos un ítem con precio para generar una orden de compra. Elija el origen del precio o cargue un precio en la línea.');
			return;
		}
		if (!$('#wz_empresa_id').val()) { alert('Indique la empresa.'); return; }
		if (!$('#wz_fecha').val() || !$('#wz_fechaentrega').val()) { alert('Indique las fechas de documento y entrega.'); return; }
		if (!$('#wz_centrocosto_id').val()) { alert('Indique el centro de costo.'); return; }
		if (!$('#wz_detalle').val()) { alert('Indique el detalle compartido.'); return; }

		if (!todosGruposTienenProveedor()) {
			renderModalProveedorFaltante();
			$('#modalWizardProveedorFaltante').modal('show');
			return;
		}

		abrirConfirmGenerar();
	});

	$(document).on('click', '.wz-prov-faltante-buscar', function () {
		var gidx = parseInt($(this).data('gidx'), 10);
		window.wzCambiarProveedorGrupo = gidx;
		if (typeof window.activa_eventos_consultaproveedor === 'function') {
			window.activa_eventos_consultaproveedor();
		}
		if (typeof window.buscar_datos_proveedor === 'function') {
			window.buscar_datos_proveedor('');
		}
		$('#consultaproveedorModal').modal('show');
	});

	$(document).on('click', '#wz-proveedor-faltante-continuar', function () {
		if (!todosGruposTienenProveedor()) {
			return;
		}
		$('#modalWizardProveedorFaltante').modal('hide');
		abrirConfirmGenerar();
	});

	$(document).on('click', '#wz-confirm-aceptar', function () {
		var $btn = $(this);
		$btn.prop('disabled', true);

		var sinOrigen = [];
		lineas.forEach(function (l) { if (!l.origen) { sinOrigen.push(l.requisicion_articulo_id); } });

		var fd = new FormData();
		fd.append('_token', META.csrf);
		fd.append('requisicion_id', String(META.requisicion_id));
		var ordenes = grupos.map(function (g, i) { return armarPayloadGrupo(i); });
		fd.append('ordenes_json', JSON.stringify(ordenes));
		fd.append('lineas_sin_orden_json', JSON.stringify(sinOrigen));
		grupos.forEach(function (g, i) {
			(g.archivos || []).forEach(function (f) {
				fd.append('archivos_grupo_' + i + '[]', f, f.name);
			});
		});

		$.ajax({
			url: wzMetaEndpoint('post'),
			method: 'POST',
			data: fd,
			processData: false,
			contentType: false,
			headers: { 'X-CSRF-TOKEN': META.csrf, Accept: 'application/json' },
		})
			.done(function (res) {
				$('#modalWizardConfirmGenerar').modal('hide');
				if (res && res.mensaje === 'ok') {
					mostrarResultadoGeneracion(res);
				} else {
					alert((res && res.message) || 'Error al generar las OCs.');
				}
			})
			.fail(function (xhr) {
				$('#modalWizardConfirmGenerar').modal('hide');
				var msg = 'Error al generar las órdenes de compra.';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					msg = xhr.responseJSON.message;
				}
				alert(msg);
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	function mostrarResultadoGeneracion(res) {
		var $b = $('#wz-resultados-body').empty();
		var ordenes = res.ordenes || [];
		var puedeEnviar = !!(META && META.puede_enviar_proveedor);
		if (!ordenes.length) {
			$b.append(
				'<tr><td colspan="2" class="text-muted small">No se generaron órdenes de compra. Los ítems sin origen de precio quedaron cerrados en la requisición y el estado pasó a <strong>GENERO ORDEN COMPRA</strong> (si estaba aprobada).</td></tr>'
			);
		}
		ordenes.forEach(function (o) {
			var $tr = $('<tr></tr>');
			$tr.append('<td>' + htmlEsc(String(o.numeroordencompra != null ? o.numeroordencompra : o.id)) + '</td>');
			var verUrl = carpetaBase + '/compras/ordencompra/' + parseInt(o.id, 10) + '/editar';
			var html = '';
			html += '<a class="btn btn-sm btn-outline-primary mr-1" href="' + verUrl + '" target="_blank" rel="noopener noreferrer" title="Visualizar OC"><i class="fa fa-eye"></i> Visualizar</a>';
			html += '<a class="btn btn-sm btn-primary mr-1" href="' + (o.url_imprimir || '#') + '" target="_blank" rel="noopener noreferrer" title="PDF vertical"><i class="fa fa-file-pdf"></i> PDF</a>';
			html += '<a class="btn btn-sm btn-outline-primary mr-1" href="' + (o.url_imprimir_apaisado || '#') + '" target="_blank" rel="noopener noreferrer" title="PDF apaisado"><i class="fa fa-file-pdf"></i> Apaisado</a>';
			if (puedeEnviar && o.puede_enviar_proveedor) {
				html += '<button type="button" class="btn btn-sm btn-success mr-1 js-oc-enviar-proveedor" data-ordencompra-id="' + parseInt(o.id, 10) + '" title="Enviar al proveedor"><i class="fa fa-envelope"></i> Email</button>';
			}
			$tr.append('<td class="text-nowrap">' + html + '</td>');
			$b.append($tr);
		});
		var $adv = $('#wz-resultados-advertencias').addClass('d-none').empty();
		if (res.advertencias && res.advertencias.length) {
			var ul = '<ul class="mb-0">';
			res.advertencias.forEach(function (a) { ul += '<li>' + htmlEsc(a) + '</li>'; });
			ul += '</ul>';
			$adv.removeClass('d-none').html(ul);
		}
		var pendientes = res.envios_pendientes || [];
		var $env = $('#wz-resultados-envio-proveedor').addClass('d-none').empty();
		var $btnEnv = $('#wz-resultados-btn-envios').addClass('d-none');
		if (puedeEnviar && pendientes.length) {
			var ids = pendientes.map(function (p) { return parseInt(p.id, 10); });
			var lista = '<ul class="mb-0 pl-3">';
			pendientes.forEach(function (p) {
				lista += '<li>OC ' + htmlEsc(String(p.numeroordencompra != null ? p.numeroordencompra : p.id));
				if (p.proveedor_nombre) {
					lista += ' — ' + htmlEsc(p.proveedor_nombre);
				}
				if (p.email) {
					lista += ' <span class="text-muted">(' + htmlEsc(p.email) + ')</span>';
				}
				lista += '</li>';
			});
			lista += '</ul>';
			$env.removeClass('d-none').html(
				'<strong><i class="fa fa-envelope"></i> ' + pendientes.length + ' orden(es) con email de proveedor.</strong> ' +
				'Puede revisar y confirmar el envío ahora o hacerlo más tarde desde el listado o la edición de cada OC.' +
				lista
			);
			$btnEnv.removeClass('d-none')
				.attr('data-envio-ids', JSON.stringify(ids))
				.html('<i class="fa fa-envelope"></i> Enviar al proveedor (' + pendientes.length + ')');
		}
		$('#modalWizardResultados').modal('show');
		$('#modalWizardResultados').one('shown.bs.modal', function () {
			if (typeof window.ocWizardOfrecerEnvioProveedor === 'function') {
				window.ocWizardOfrecerEnvioProveedor(res, { resultadosModal: '#modalWizardResultados' });
			}
		});
	}

	// ---------------------------------------------------------------------
	// Boot
	// ---------------------------------------------------------------------
	function bootOrdencompraWizard() {
		if (window.__WZ_BOOT_DONE__ && lineas.length > 0) {
			return;
		}
		if (!ensureWizardHidratado()) {
			if (!initWizardConfig()) {
				return;
			}
		}
		if (lineas.length > 0) {
			window.__WZ_BOOT_DONE__ = true;
		}
	}

	window.wzBootOrdencompraWizard = bootOrdencompraWizard;
	window.wzEnsureWizardHidratado = ensureWizardHidratado;

	$(function () {
		bootOrdencompraWizard();
		precargarHandlersConsultaProveedor();

		function wzActualizarAyudaDescuento() {
			var tipo = $('#wz_descuento_tipo').val() || 'porcentaje';
			var $ayuda = $('#wz_descuento_ayuda');
			if (!$ayuda.length) {
				return;
			}
			if (tipo === 'importe') {
				$ayuda.text('Monto fijo sobre el neto antes del IVA por OC.');
			} else {
				$ayuda.text('Porcentaje sobre el neto antes del IVA por OC.');
			}
		}

		$('#wz_descuento_tipo').on('change', function () {
			wzActualizarAyudaDescuento();
			wzScheduleRefrescarTotalesCabeceras();
		});
		wzActualizarAyudaDescuento();

		$('#wz_descuento, #wz_fecha').on('change input', function () {
			wzScheduleRefrescarTotalesCabeceras();
		});

		$('#consultaproveedorModal').on('hidden.bs.modal', function () {
			window.wzCambiarProveedorGrupo = null;
			window.wzCambiarProveedorLinea = null;
		});

		if (typeof window.consultarProveedor !== 'function' && typeof window.activaModalConsultaProveedor === 'function') {
			window.consultarProveedor = window.activaModalConsultaProveedor;
		}
	});
})(jQuery);
