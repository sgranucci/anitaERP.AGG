/* global carpetaBase */
$(function () {
	if (typeof window.carpetaBase === 'undefined') {
		var __locCb = window.location.pathname || '';
		var __mCb = __locCb.match(/^(.*\/public)(?:\/|$)/);
		window.carpetaBase = __mCb ? __mCb[1] : '';
	}

	if (!$('#form-ordencompra-general').length) {
		return;
	}

	var ocMonedaPesoId = parseInt($('#tabla-articulos-ordencompra').attr('data-oc-moneda-peso-id') || '1', 10);
	var ocMostrarPeso = ($('#tabla-articulos-ordencompra').attr('data-oc-mostrar-peso') || '0') === '1';
	var ocPedirPartidaCapex = ($('#tabla-articulos-ordencompra').attr('data-oc-pedir-partida-capex') || '1') === '1';

	function ocFormatearCantAlt(num) {
		if (!isFinite(num)) {
			return '';
		}
		var t = parseFloat(num.toFixed(4));
		if (t === 0) {
			return '0';
		}
		return String(t);
	}

	function ocFormatearPeso(num) {
		if (!isFinite(num)) {
			return '';
		}
		var t = parseFloat(num.toFixed(6));
		if (t === 0) {
			return '';
		}
		return String(t);
	}

	function ocLimpiarCantidadAlternativaHint($tr) {
		$tr.find('.oc-unidadesxenvase').val('');
		$tr.find('.oc-um-alt-abrev').val('');
		$tr.find('.oc-cantidadalternativa').val('');
		$tr.find('.oc-cant-alt-valor').addClass('text-muted').text('—');
		$tr.find('.oc-cant-alt-um').text('');
	}

	function ocLimpiarPesoLinea($tr) {
		if (!ocMostrarPeso || !$tr || !$tr.length) {
			return;
		}
		$tr.find('.oc-peso-unitario').val('');
		$tr.find('.oc-peso-total').val('');
	}

	function ocAplicarPesoDesdeArticulo($tr, dataArticulo) {
		if (!ocMostrarPeso || !$tr || !$tr.length) {
			return;
		}
		var peso = dataArticulo ? (parseFloat(dataArticulo.peso) || 0) : 0;
		$tr.find('.oc-peso-unitario').val(peso > 0 ? ocFormatearPeso(peso) : '');
		ocActualizarPesoTotalHint($tr);
	}

	function ocActualizarPesoTotalHint($tr) {
		if (!ocMostrarPeso || !$tr || !$tr.length) {
			return;
		}
		var pesoU = parseFloat($tr.find('.oc-peso-unitario').val()) || 0;
		var cant = parseFloat($tr.find('.cantidad-linea').val()) || 0;
		if (pesoU <= 0 || cant <= 0) {
			$tr.find('.oc-peso-total').val('');
			return;
		}
		$tr.find('.oc-peso-total').val(ocFormatearPeso(pesoU * cant));
	}

	function ocEnriquecerUmAltDesdeArticulo($tr, dataArticulo) {
		if (!$tr || !$tr.length || !dataArticulo) {
			return;
		}
		var umdAlt = (dataArticulo.unidadesdemedidasalternativas && dataArticulo.unidadesdemedidasalternativas.abreviatura)
			|| dataArticulo.um_alternativa_abreviatura
			|| '';
		var uxenv = parseFloat(dataArticulo.unidadesxenvase) || 0;
		$tr.find('.oc-unidadesxenvase').val(uxenv > 0 ? uxenv : '');
		$tr.find('.oc-um-alt-abrev').val(umdAlt || '');
		ocActualizarCantidadAlternativaHint($tr);
		ocAplicarPesoDesdeArticulo($tr, dataArticulo);
	}

	function ocActualizarCantidadAlternativaHint($tr) {
		if (!$tr || !$tr.length) {
			return;
		}
		var uxenv = parseFloat($tr.find('.oc-unidadesxenvase').val()) || 0;
		var abrev = ($tr.find('.oc-um-alt-abrev').val() || '').trim();
		var cant = parseFloat($tr.find('.cantidad-linea').val()) || 0;
		var $valor = $tr.find('.oc-cant-alt-valor');
		var $um = $tr.find('.oc-cant-alt-um');
		var $hidden = $tr.find('.oc-cantidadalternativa');

		if (uxenv <= 0) {
			$hidden.val('');
			$valor.addClass('text-muted').text('—');
			$um.text('');
			return;
		}

		var alt = cant * uxenv;
		var altTxt = ocFormatearCantAlt(alt);
		$hidden.val(altTxt);
		$valor.removeClass('text-muted').text(altTxt);
		$um.text(abrev || '');
	}

	var ocOnArticuloSeleccionadoPrev = window.onArticuloSeleccionado;
	window.onArticuloSeleccionado = function (dataArticulo, ctx) {
		if (typeof ocOnArticuloSeleccionadoPrev === 'function') {
			ocOnArticuloSeleccionadoPrev(dataArticulo, ctx);
		}
		if (!dataArticulo || !ctx || !ctx.row) {
			return;
		}
		var $tr = $(ctx.row);
		if (!$tr.closest('#tabla-articulos-ordencompra').length) {
			return;
		}
		var provCab = parseInt($('#proveedor_id').val(), 10) || 0;
		if (provCab <= 0) {
			alert('Debe indicar el proveedor de la orden de compra antes de cargar artículos.');
			$tr.find('.articulo_id').val('');
			$tr.find('.codigoarticulo').val('');
			$tr.find('.descripcionarticulo').val('');
			ocLimpiarCantidadAlternativaHint($tr);
			ocLimpiarPesoLinea($tr);
			if (window.ArticuloProveedorOperativo) {
				window.ArticuloProveedorOperativo.aplicarAFila($tr, null, dataArticulo);
			}
			$('#codigoproveedor').focus();
			return;
		}
		if (typeof window.msAplicarExclusividadColorTalle === 'function') {
			if (!window.msAplicarExclusividadColorTalle(dataArticulo, $tr)) {
				return;
			}
		}
		ocEnriquecerUmAltDesdeArticulo($tr, dataArticulo);
		if (window.ArticuloProveedorOperativo && typeof window.ArticuloProveedorOperativo.resolverTrasArticulo === 'function') {
			window.ArticuloProveedorOperativo.resolverTrasArticulo({
				$tr: $tr,
				dataArticulo: dataArticulo,
				proveedorCabeceraId: provCab,
				restrictivo: true,
				onSinMatchCabecera: function () {
					if (typeof toastr !== 'undefined') {
						toastr.info('El artículo no tiene vínculo activo con este proveedor en el catálogo; se usan datos del maestro.');
					}
				}
			});
		}
	};

	function mostrarSolapa(sel) {
		$('.oc-solapa').hide();
		$(sel).show();
	}

	function ocMarcarTabActivo(btnDomId) {
		$('.oc-tab-solapa').removeClass('font-weight-bold');
		var $b = $('#' + btnDomId);
		if ($b.length) {
			$b.addClass('font-weight-bold');
		}
	}

	function ocMostrarSolapaDelElemento(domEl) {
		var $el = $(domEl);
		if ($el.closest('#oc-solapa-articulos').length) {
			mostrarSolapa('#oc-solapa-articulos');
			ocMarcarTabActivo('oc-boton-articulos');
		} else if ($el.closest('#oc-solapa-comprobantes').length) {
			mostrarSolapa('#oc-solapa-comprobantes');
			ocMarcarTabActivo('oc-boton-comprobantes');
		} else if ($el.closest('#oc-solapa-archivos').length) {
			mostrarSolapa('#oc-solapa-archivos');
			ocMarcarTabActivo('oc-boton-archivos');
		} else if ($el.closest('#oc-solapa-historia-legajo').length) {
			mostrarSolapa('#oc-solapa-historia-legajo');
			ocMarcarTabActivo('oc-boton-historia-legajo');
		} else if ($el.closest('#oc-solapa-historia-estados').length) {
			mostrarSolapa('#oc-solapa-historia-estados');
			ocMarcarTabActivo('oc-boton-historia-estados');
		} else if ($el.closest('#oc-solapa-historia-precios').length) {
			mostrarSolapa('#oc-solapa-historia-precios');
			ocMarcarTabActivo('oc-boton-historia-precios');
		} else if ($el.closest('#oc-solapa-recepciones').length) {
			mostrarSolapa('#oc-solapa-recepciones');
			ocMarcarTabActivo('oc-boton-recepciones');
		} else if ($el.closest('#oc-solapa-arbol').length) {
			mostrarSolapa('#oc-solapa-arbol');
			ocMarcarTabActivo('oc-boton-arbol');
		} else if ($el.closest('#oc-solapa-ingresos').length) {
			mostrarSolapa('#oc-solapa-ingresos');
			ocMarcarTabActivo('oc-boton-ingresos');
		} else {
			mostrarSolapa('#oc-solapa-principal');
			ocMarcarTabActivo('oc-boton-principal');
		}
		if (domEl && domEl.scrollIntoView) {
			domEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}

	function ocRadioGrupoTieneSeleccion(form, name) {
		var inputs = form.getElementsByTagName('input');
		for (var i = 0; i < inputs.length; i++) {
			if (inputs[i].type === 'radio' && inputs[i].name === name && inputs[i].checked) {
				return true;
			}
		}
		return false;
	}

	function ocEsCampoRequiredVacio(campo, form) {
		var tag = campo.tagName ? campo.tagName.toLowerCase() : '';
		var type = (campo.type || '').toLowerCase();
		if (tag === 'select') {
			return campo.value === '' || campo.value === null;
		}
		if (tag === 'textarea') {
			return String(campo.value || '').trim() === '';
		}
		if (tag === 'input') {
			if (type === 'checkbox') {
				return !campo.checked;
			}
			if (type === 'radio') {
				if (!campo.name) {
					return !campo.checked;
				}
				return !ocRadioGrupoTieneSeleccion(form, campo.name);
			}
			if (type === 'file') {
				return !campo.files || campo.files.length === 0;
			}
			if (type === 'hidden') {
				return String(campo.value || '').trim() === '';
			}
			return String(campo.value || '').trim() === '';
		}
		return String(campo.value || '').trim() === '';
	}

	function ocLimpiarMarcasRequiredVacios(form) {
		var marcas = form.querySelectorAll('.oc-required-vacio');
		for (var i = 0; i < marcas.length; i++) {
			marcas[i].style.borderColor = '';
			marcas[i].classList.remove('oc-required-vacio');
		}
	}

	function ocRecopilarCamposRequiredVacios(form) {
		var vacios = [];
		var radiosProcesados = {};
		var lista = form.querySelectorAll('[required]');
		for (var i = 0; i < lista.length; i++) {
			var campo = lista[i];
			if (campo.closest && campo.closest('.modal')) {
				continue;
			}
			if (campo.matches && campo.matches(':disabled')) {
				continue;
			}
			if (campo.readOnly) {
				continue;
			}
			var type = (campo.type || '').toLowerCase();
			if (type === 'radio' && campo.name) {
				if (radiosProcesados[campo.name]) {
					continue;
				}
				radiosProcesados[campo.name] = true;
			}
			if (ocEsCampoRequiredVacio(campo, form)) {
				vacios.push(campo);
			}
		}
		return vacios;
	}

	function ocActualizarCotizacionLinea($row) {
		var mid = parseInt($row.find('.oc-moneda-linea').val(), 10);
		if (!mid) {
			mid = ocMonedaPesoId;
		}
		var $cotIn = $row.find('.oc-cotizacion-linea');
		if (mid === ocMonedaPesoId) {
			$cotIn.val('1');
			ocScheduleTotales();
			return;
		}
		var fecha = $('#fecha').val();
		if (!fecha) {
			return;
		}
		$.get(carpetaBase + '/compras/ordencompra/cotizacion-moneda-fecha', {
			fecha: fecha,
			moneda_id: mid
		}).done(function (res) {
			if (!res || res.cotizacion == null) {
				return;
			}
			$cotIn.val(res.cotizacion);
			ocScheduleTotales();
		});
	}

	function ocRefrescarCotizacionesExtranjerasPorFecha() {
		$('#tabla-articulos-ordencompra tbody tr.item-ordencompra-articulo').each(function () {
			var mid = parseInt($(this).find('.oc-moneda-linea').val(), 10) || ocMonedaPesoId;
			if (mid !== ocMonedaPesoId) {
				ocActualizarCotizacionLinea($(this));
			}
		});
	}

	if (typeof activa_eventos_consultaarticulo === 'function') {
		activa_eventos_consultaarticulo();
	}
	if (typeof activa_eventos_consultapartidagasto === 'function') {
		activa_eventos_consultapartidagasto();
	}
	if (typeof activa_eventos_consultacapex === 'function') {
		activa_eventos_consultacapex();
	}
	if (typeof activa_eventos_consultaproveedor === 'function') {
		activa_eventos_consultaproveedor();
	}

	var ptrOcDetalleLineaRow = null;
	var ptrOcOrigenPrecioRow = null;

	function ocSubRowArticulo($mainTr) {
		var $s = $mainTr.next('tr.item-ordencompra-articulo-sub');
		return $s.length ? $s : $();
	}

	function ocRefreshOrigenPrecioResumen($row) {
		var et = ($row.find('.oc-precio-origen-etiqueta').val() || '').trim();
		var $div = ocSubRowArticulo($row).find('.oc-origen-precio-resumen');
		if (!$div.length) {
			return;
		}
		if (!et) {
			$div.text('—').attr('title', '');
			return;
		}
		$div.text(et).attr('title', et);
	}

	function ocHtmlEsc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function ocFmtPrecio(n) {
		var x = parseFloat(n);
		if (isNaN(x)) {
			return '—';
		}
		return x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
	}

	function ocMonedaLabel(monedaId) {
		var id = parseInt(monedaId, 10);
		var m = ocMonedas.find(function (x) { return parseInt(x.id, 10) === id; });
		return m && m.abrev ? m.abrev : String(monedaId || '');
	}

	function ocAplicarSeleccionOrigenPrecio(tipo, precio, monedaId, refId, etiqueta) {
		if (!ptrOcOrigenPrecioRow || !ptrOcOrigenPrecioRow.length) {
			return;
		}
		ptrOcOrigenPrecioRow.find('.precio-linea').val(precio);
		if (monedaId) {
			ptrOcOrigenPrecioRow.find('.oc-moneda-linea').val(String(monedaId));
		}
		ptrOcOrigenPrecioRow.find('.oc-precio-origen-tipo').val(tipo);
		ptrOcOrigenPrecioRow.find('.oc-precio-origen-ref-id').val(refId != null && refId !== '' ? String(refId) : '');
		ptrOcOrigenPrecioRow.find('.oc-precio-origen-etiqueta').val(etiqueta || '');
		ocRefreshOrigenPrecioResumen(ptrOcOrigenPrecioRow);
		ocActualizarCotizacionLinea(ptrOcOrigenPrecioRow);
		$('#modalOcOrigenPrecio').modal('hide');
		ocScheduleTotales();
	}

	function ocNormCabeceraId(v) {
		if (v === undefined || v === null || v === '') {
			return 0;
		}
		var n = parseInt(String(v), 10);
		return Number.isFinite(n) && n > 0 ? n : 0;
	}

	function ocCabeceraPrincipalVacia() {
		if (ocNormCabeceraId($('#proveedor_id').val()) > 0) {
			return false;
		}
		if (ocNormCabeceraId($('#condicioncompra_id').val()) > 0) {
			return false;
		}
		if (ocNormCabeceraId($('#condicionentrega_id').val()) > 0) {
			return false;
		}
		if ($('#condicionpago_id').length && ocNormCabeceraId($('#condicionpago_id').val()) > 0) {
			return false;
		}
		return true;
	}

	function ocPresupuestoPermitidoParaCabeceraActual(meta) {
		if (ocCabeceraPrincipalVacia()) {
			return true;
		}
		var msgs = [];
		var ocP = ocNormCabeceraId($('#proveedor_id').val());
		if (ocP > 0 && ocNormCabeceraId(meta.proveedor_id) !== ocP) {
			msgs.push('El proveedor del presupuesto no coincide con el proveedor cargado en la orden.');
		}
		var occc = ocNormCabeceraId($('#condicioncompra_id').val());
		if (occc > 0 && occc !== ocNormCabeceraId(meta.condicioncompra_id)) {
			msgs.push('La condición de compra del presupuesto no coincide con la de la orden.');
		}
		var occe = ocNormCabeceraId($('#condicionentrega_id').val());
		if (occe > 0 && occe !== ocNormCabeceraId(meta.condicionentrega_id)) {
			msgs.push('La condición de entrega del presupuesto no coincide con la de la orden.');
		}
		if ($('#condicionpago_id').length) {
			var occp = ocNormCabeceraId($('#condicionpago_id').val());
			if (occp > 0 && occp !== ocNormCabeceraId(meta.condicionpago_id)) {
				msgs.push('La condición de pago del presupuesto no coincide con la de la orden.');
			}
		}
		if (msgs.length) {
			alert(msgs.join('\n'));
			return false;
		}
		return true;
	}

	function ocAplicarCabeceraDesdePresupuestoMeta(meta) {
		if (!meta || typeof meta !== 'object') {
			return;
		}
		var pid = ocNormCabeceraId(meta.proveedor_id);
		$('#proveedor_id').val(pid > 0 ? String(pid) : '');
		$('#codigoproveedor').val(meta.proveedor_codigo || '');
		$('#nombreproveedor').val(meta.proveedor_nombre || '');
		var cc = ocNormCabeceraId(meta.condicioncompra_id);
		$('#condicioncompra_id').val(cc > 0 ? String(cc) : '');
		var ce = ocNormCabeceraId(meta.condicionentrega_id);
		$('#condicionentrega_id').val(ce > 0 ? String(ce) : '');
		if ($('#condicionpago_id').length) {
			var cp = ocNormCabeceraId(meta.condicionpago_id);
			$('#condicionpago_id').val(cp > 0 ? String(cp) : '');
		}
		$('#proveedor_id').trigger('change');
	}

	function ocCargarModalOrigenPrecio() {
		var reqId = parseInt($('#requisicion_id').val(), 10);
		var $row = ptrOcOrigenPrecioRow;
		var reqArtId = parseInt($row.find('.oc-requisicion-articulo-id').val(), 10);
		var artId = parseInt($row.find('.articulo_id').val(), 10);
		var provId = parseInt($('#proveedor_id').val(), 10);
		var fechaRef = $('#fecha').val() || '';

		$('#modalOcOrigenPrecioError').addClass('d-none').text('');
		$('#modalOcOrigenPrecioOpciones').empty();
		$('#modalOcOrigenPrecioCargando').removeClass('d-none');

		if (!reqId || !reqArtId || !artId) {
			$('#modalOcOrigenPrecioCargando').addClass('d-none');
			$('#modalOcOrigenPrecioError').removeClass('d-none').text('Esta línea no proviene de una requisición o falta el artículo.');
			return;
		}

		$.get(carpetaBase + '/compras/ordencompra/opciones-precio-linea', {
			requisicion_id: reqId,
			requisicion_articulo_id: reqArtId,
			articulo_id: artId,
			fecha_referencia: fechaRef,
			proveedor_id: provId > 0 ? provId : ''
		})
			.done(function (data) {
				$('#modalOcOrigenPrecioCargando').addClass('d-none');
				if (!data || data.message) {
					$('#modalOcOrigenPrecioError').removeClass('d-none').text((data && data.message) || 'Sin datos.');
					return;
				}
				var sku = (data.articulo && data.articulo.sku) ? data.articulo.sku : '';
				var desc = (data.articulo && data.articulo.descripcion) ? data.articulo.descripcion : '';
				$('#modalOcOrigenPrecioSubtitulo').text(sku + ' — ' + desc);

				var $box = $('#modalOcOrigenPrecioOpciones');
				var ORQ = 'REQUISICION';
				var OL = 'LISTA_PROVEEDOR';
				var OP = 'PRESUPUESTO';

				function addBtn(tipo, precio, monedaId, refId, titulo, subtitulo) {
					var $b = $('<button type="button" class="btn btn-outline-primary btn-block text-left mb-2 oc-aplica-opcion-precio"/>');
					$b.data('tipo', tipo);
					$b.data('precio', precio);
					$b.data('monedaId', monedaId || '');
					$b.data('refId', refId != null ? refId : '');
					$b.data('etiqueta', titulo);
					$b.html(
						'<strong>' + ocHtmlEsc(titulo) + '</strong><br><span class="small text-muted">' +
							ocHtmlEsc(subtitulo) +
							'</span>'
					);
					$box.append($b);
				}

				if (data.opcion_requisicion) {
					var oq = data.opcion_requisicion;
					var mid = oq.moneda_id || ocMonedaPesoId;
					var $br = $('<button type="button" class="btn btn-outline-primary btn-block text-left mb-2 oc-aplica-opcion-precio"/>');
					$br.data('tipo', ORQ);
					$br.data('precio', oq.precio);
					$br.data('monedaId', mid);
					$br.data('refId', oq.ref_id != null ? oq.ref_id : '');
					$br.data('etiqueta', oq.etiqueta || 'Precio requisición');
					$br.data('ocPrecioMeta', {
						proveedor_id: oq.proveedor_id != null ? oq.proveedor_id : 0,
						condicioncompra_id: oq.condicioncompra_id != null ? oq.condicioncompra_id : 0,
						condicionentrega_id: oq.condicionentrega_id != null ? oq.condicionentrega_id : 0,
						condicionpago_id: oq.condicionpago_id != null ? oq.condicionpago_id : 0
					});
					$br.html(
						'<strong>' + ocHtmlEsc(oq.etiqueta || 'Precio requisición') + '</strong><br><span class="small text-muted">' +
							'Precio: ' + ocFmtPrecio(oq.precio) + ' ' + ocMonedaLabel(mid) +
							'</span>'
					);
					$box.append($br);
				}
				(data.opciones_lista || []).forEach(function (L) {
					var mid = L.moneda_id || ocMonedaPesoId;
					var $bl = $('<button type="button" class="btn btn-outline-primary btn-block text-left mb-2 oc-aplica-opcion-precio"/>');
					$bl.data('tipo', OL);
					$bl.data('precio', L.precio);
					$bl.data('monedaId', mid);
					$bl.data('refId', L.ref_id != null ? L.ref_id : '');
					$bl.data('etiqueta', L.etiqueta || 'Lista proveedor');
					$bl.data('ocPrecioMeta', {
						proveedor_id: L.proveedor_id != null ? L.proveedor_id : 0,
						condicioncompra_id: L.condicioncompra_id != null ? L.condicioncompra_id : 0,
						condicionentrega_id: L.condicionentrega_id != null ? L.condicionentrega_id : 0,
						condicionpago_id: L.condicionpago_id != null ? L.condicionpago_id : 0
					});
					var subL =
						'Precio neto: ' +
						ocFmtPrecio(L.precio) +
						' ' +
						ocMonedaLabel(mid) +
						(L.lista_id ? ' · Lista id ' + L.lista_id : '') +
						(L.linea_lista_id ? ' · Línea lista id ' + L.linea_lista_id : '');
					$bl.html(
						'<strong>' + ocHtmlEsc(L.etiqueta || 'Lista proveedor') + '</strong><br><span class="small text-muted">' +
							ocHtmlEsc(subL) +
							'</span>'
					);
					$box.append($bl);
				});
				(data.opciones_presupuesto || []).forEach(function (P) {
					var mid = P.moneda_id || ocMonedaPesoId;
					var $bp = $('<button type="button" class="btn btn-outline-primary btn-block text-left mb-2 oc-aplica-opcion-precio"/>');
					$bp.data('tipo', OP);
					$bp.data('precio', P.precio);
					$bp.data('monedaId', mid);
					$bp.data('refId', P.ref_id != null ? P.ref_id : '');
					$bp.data('etiqueta', P.etiqueta || ('Presupuesto #' + P.presupuesto_id));
					$bp.data('ocPresMeta', {
						proveedor_id: P.proveedor_id,
						proveedor_codigo: P.proveedor_codigo || '',
						proveedor_nombre: P.proveedor_nombre || '',
						condicioncompra_id: P.condicioncompra_id,
						condicionentrega_id: P.condicionentrega_id,
						condicionpago_id: P.condicionpago_id
					});
					$bp.data('ocPrecioMeta', {
						proveedor_id: P.proveedor_id != null ? P.proveedor_id : 0,
						condicioncompra_id: P.condicioncompra_id != null ? P.condicioncompra_id : 0,
						condicionentrega_id: P.condicionentrega_id != null ? P.condicionentrega_id : 0,
						condicionpago_id: P.condicionpago_id != null ? P.condicionpago_id : 0
					});
					var subP =
						'Precio: ' +
						ocFmtPrecio(P.precio) +
						' ' +
						ocMonedaLabel(mid) +
						(P.presupuesto_id ? ' · Presup. #' + P.presupuesto_id : '');
					if (P.observacion_linea) {
						subP += ' · ' + String(P.observacion_linea).substring(0, 72);
					}
					$bp.html(
						'<strong>' + ocHtmlEsc(P.etiqueta || 'Presupuesto #' + P.presupuesto_id) + '</strong><br><span class="small text-muted">' +
							ocHtmlEsc(subP) +
							'</span>'
					);
					$box.append($bp);
				});
			})
			.fail(function (xhr) {
				$('#modalOcOrigenPrecioCargando').addClass('d-none');
				var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al cargar opciones.';
				$('#modalOcOrigenPrecioError').removeClass('d-none').text(msg);
			});
	}

	$(document).on('click', '.oc-btn-origen-precio', function () {
		ptrOcOrigenPrecioRow = $(this).closest('tr.item-ordencompra-articulo');
		$('#modalOcOrigenPrecioSubtitulo').text('');
		ocCargarModalOrigenPrecio();
		$('#modalOcOrigenPrecio').modal('show');
	});

	$(document).on('click', '.oc-aplica-opcion-precio', function () {
		var $b = $(this);
		var tipo = $b.data('tipo');
		if (tipo === 'PRESUPUESTO') {
			var meta = $b.data('ocPresMeta');
			if (meta && typeof meta === 'object') {
				if (!ocPresupuestoPermitidoParaCabeceraActual(meta)) {
					return;
				}
				if (ocCabeceraPrincipalVacia()) {
					ocAplicarCabeceraDesdePresupuestoMeta(meta);
				}
			}
		}
		var pm = $b.data('ocPrecioMeta');
		if (pm && typeof pm === 'object' && ptrOcOrigenPrecioRow && ptrOcOrigenPrecioRow.length) {
			ptrOcOrigenPrecioRow.data('ocGrupoMeta', pm);
		} else if (ptrOcOrigenPrecioRow && ptrOcOrigenPrecioRow.length) {
			ptrOcOrigenPrecioRow.removeData('ocGrupoMeta');
		}
		ocAplicarSeleccionOrigenPrecio(
			tipo,
			$b.data('precio'),
			$b.data('monedaId'),
			$b.data('refId'),
			$b.data('etiqueta')
		);
	});

	function ocRefreshDetalleLineaBadge($row) {
		var $ta = $row.find('.oc-ta-detalle-linea');
		var t = ($ta.val() || '').trim();
		var $bd = ocSubRowArticulo($row).find('.oc-detalle-linea-badge');
		if (!$bd.length) {
			return;
		}
		if (!t.length) {
			$bd.text('—').removeAttr('title');
			return;
		}
		$bd.text(t).attr('title', t);
	}

	$(document).on('click', '.oc-abrir-detalle-linea', function () {
		ptrOcDetalleLineaRow = $(this).closest('tr.item-ordencompra-articulo');
		var v = ptrOcDetalleLineaRow.find('.oc-ta-detalle-linea').val() || '';
		$('#oc_detalle_linea_editor').val(v);
		$('#modalOcDetalleLinea').modal('show');
	});

	$('#modalOcDetalleLinea').on('show.bs.modal', function () {
		var ro = !$('#oc_detalle_linea_guardar').length;
		$('#oc_detalle_linea_editor').prop('readonly', ro);
	});

	$('#modalOcDetalleLinea').on('shown.bs.modal', function () {
		$('#oc_detalle_linea_editor').trigger('focus');
	});

	$(document).on('click', '#oc_detalle_linea_guardar', function () {
		if (!ptrOcDetalleLineaRow || !ptrOcDetalleLineaRow.length) {
			return;
		}
		ptrOcDetalleLineaRow.find('.oc-ta-detalle-linea').val($('#oc_detalle_linea_editor').val() || '');
		ocRefreshDetalleLineaBadge(ptrOcDetalleLineaRow);
		$('#modalOcDetalleLinea').modal('hide');
	});

	var ptrOcEntregaSemanalRow = null;
	var ocEntregaSemanalSoloLectura = !$('#oc-entrega-semanal-aplicar').length;

	function ocEntregaSemanalHabilitada() {
		return $('#tabla-articulos-ordencompra').attr('data-oc-entrega-semanal') === '1';
	}

	function ocParseEntregasJson($row) {
		var raw = $row.find('.oc-entregas-semanal-json').val() || '[]';
		try {
			var arr = JSON.parse(raw);
			return Array.isArray(arr) ? arr : [];
		} catch (e) {
			return [];
		}
	}

	function ocFmtCantEntrega(n) {
		var x = Number(n);
		if (!isFinite(x)) {
			return '0';
		}
		return String(parseFloat(x.toFixed(4)));
	}

	function ocRefreshEntregasSemanalUi($row) {
		if (!$row || !$row.length || !ocEntregaSemanalHabilitada()) {
			return;
		}
		var arr = ocParseEntregasJson($row);
		var n = arr.length;
		var $badge = $row.find('.oc-entregas-count');
		if ($badge.length) {
			if (n > 0) {
				$badge.text(String(n)).removeClass('d-none');
			} else {
				$badge.text('').addClass('d-none');
			}
		}
		var $cant = $row.find('.cantidad-linea');
		var puedeEditar = $('#oc-entrega-semanal-aplicar').length > 0;
		if (n > 0) {
			$cant.addClass('oc-cant-desde-entregas');
			if (puedeEditar) {
				$cant.prop('readonly', true).attr('title', 'Cantidad = suma de entregas semanales');
			}
		} else if (puedeEditar) {
			$cant.removeClass('oc-cant-desde-entregas').prop('readonly', false).attr('title', '');
		}
		var $res = ocSubRowArticulo($row).find('.oc-entregas-semanal-resumen');
		if ($res.length) {
			if (!n) {
				$res.text('—').removeAttr('title');
			} else {
				var parts = arr.map(function (e) {
					var f = (e.fecha || '').toString();
					if (/^\d{4}-\d{2}-\d{2}$/.test(f)) {
						f = f.slice(8, 10) + '/' + f.slice(5, 7) + '/' + f.slice(0, 4);
					}
					return f + ': ' + ocFmtCantEntrega(e.cantidad);
				});
				var txt = n + ' entrega(s) · ' + parts.join(' · ');
				$res.text(txt).attr('title', txt);
			}
		}
	}

	function ocEntregaSemanalLeerFilasModal() {
		var out = [];
		$('#oc-entrega-semanal-tbody tr.oc-entrega-semanal-renglon').each(function () {
			var fecha = ($(this).find('.oc-entrega-fecha').val() || '').trim();
			var cant = parseFloat($(this).find('.oc-entrega-cantidad').val());
			if (!fecha || !isFinite(cant) || cant <= 0) {
				return;
			}
			out.push({ fecha: fecha, cantidad: cant });
		});
		out.sort(function (a, b) {
			return String(a.fecha).localeCompare(String(b.fecha));
		});
		return out;
	}

	function ocEntregaSemanalActualizarTotal() {
		var sum = 0;
		$('#oc-entrega-semanal-tbody .oc-entrega-cantidad').each(function () {
			var v = parseFloat($(this).val());
			if (isFinite(v) && v > 0) {
				sum += v;
			}
		});
		$('#oc-entrega-semanal-total').text(ocFmtCantEntrega(sum));
	}

	function ocEntregaSemanalAgregarRenglon(fecha, cantidad) {
		var tpl = document.getElementById('oc-entrega-semanal-template-renglon');
		if (!tpl) {
			return;
		}
		var $tr = $(tpl.content.cloneNode(true)).find('tr');
		if (fecha) {
			$tr.find('.oc-entrega-fecha').val(fecha);
		}
		if (cantidad !== undefined && cantidad !== null && cantidad !== '') {
			$tr.find('.oc-entrega-cantidad').val(ocFmtCantEntrega(cantidad));
		}
		if (ocEntregaSemanalSoloLectura) {
			$tr.find('input').prop('readonly', true);
			$tr.find('.oc-entrega-quitar').prop('disabled', true).addClass('d-none');
		}
		$('#oc-entrega-semanal-tbody').append($tr);
		ocEntregaSemanalActualizarTotal();
	}

	function ocEntregaSemanalPoblarModal(arr) {
		$('#oc-entrega-semanal-tbody').empty();
		if (!arr || !arr.length) {
			if (!ocEntregaSemanalSoloLectura) {
				ocEntregaSemanalAgregarRenglon('', '');
			}
		} else {
			arr.forEach(function (e) {
				ocEntregaSemanalAgregarRenglon(e.fecha || '', e.cantidad);
			});
		}
		ocEntregaSemanalActualizarTotal();
	}

	$(document).on('click', '.oc-abrir-entrega-semanal', function () {
		if (!ocEntregaSemanalHabilitada()) {
			return;
		}
		ptrOcEntregaSemanalRow = $(this).closest('tr.item-ordencompra-articulo');
		var sku = (ptrOcEntregaSemanalRow.find('.codigoarticulo').val() || '').trim();
		var desc = (ptrOcEntregaSemanalRow.find('.descripcionarticulo').val() || '').trim();
		var sub = 'Cargue fecha y cantidad por semana. La suma se aplica a la cantidad de la línea.';
		if (sku || desc) {
			sub = (sku ? sku + ' — ' : '') + desc;
		}
		$('#oc-entrega-semanal-subtitulo').text(sub);
		ocEntregaSemanalPoblarModal(ocParseEntregasJson(ptrOcEntregaSemanalRow));
		$('#modalOcEntregaSemanal').modal('show');
	});

	$(document).on('click', '#oc-entrega-semanal-agregar', function () {
		ocEntregaSemanalAgregarRenglon('', '');
		$('#oc-entrega-semanal-tbody tr:last .oc-entrega-fecha').trigger('focus');
	});

	$(document).on('click', '#oc-entrega-semanal-tbody .oc-entrega-quitar', function () {
		var $tbody = $('#oc-entrega-semanal-tbody');
		var $tr = $(this).closest('tr');
		if ($tbody.find('tr').length <= 1) {
			$tr.find('input').val('');
		} else {
			$tr.remove();
		}
		ocEntregaSemanalActualizarTotal();
	});

	$(document).on('input change', '#oc-entrega-semanal-tbody .oc-entrega-fecha, #oc-entrega-semanal-tbody .oc-entrega-cantidad', function () {
		ocEntregaSemanalActualizarTotal();
	});

	$(document).on('click', '#oc-entrega-semanal-limpiar', function () {
		$('#oc-entrega-semanal-tbody').empty();
		ocEntregaSemanalAgregarRenglon('', '');
		ocEntregaSemanalActualizarTotal();
	});

	$(document).on('click', '#oc-entrega-semanal-aplicar', function () {
		if (!ptrOcEntregaSemanalRow || !ptrOcEntregaSemanalRow.length) {
			return;
		}
		var arr = ocEntregaSemanalLeerFilasModal();
		ptrOcEntregaSemanalRow.find('.oc-entregas-semanal-json').val(JSON.stringify(arr));
		var sum = 0;
		var fechas = [];
		arr.forEach(function (e) {
			sum += Number(e.cantidad) || 0;
			if (e.fecha) {
				fechas.push(e.fecha);
			}
		});
		if (arr.length > 0 && sum > 0) {
			ptrOcEntregaSemanalRow.find('.cantidad-linea').val(ocFmtCantEntrega(sum));
			fechas.sort();
			if (fechas.length) {
				ptrOcEntregaSemanalRow.find('input[name="fechaentrega_articulos[]"]').val(fechas[0]);
			}
		}
		ocRefreshEntregasSemanalUi(ptrOcEntregaSemanalRow);
		ocActualizarCantidadAlternativaHint(ptrOcEntregaSemanalRow);
		ocActualizarPesoTotalHint(ptrOcEntregaSemanalRow);
		if (window.ArticuloProveedorOperativo) {
			window.ArticuloProveedorOperativo.actualizarHintConversion(ptrOcEntregaSemanalRow);
		}
		ocScheduleTotales();
		$('#modalOcEntregaSemanal').modal('hide');
	});

	function ocFmtFechaEntregaDdMmYyyy(iso) {
		var f = (iso || '').toString();
		if (/^\d{4}-\d{2}-\d{2}$/.test(f)) {
			return f.slice(8, 10) + '/' + f.slice(5, 7) + '/' + f.slice(0, 4);
		}
		return f;
	}

	function ocRecolectarLineasEntregaSemanalResumen() {
		var lineas = [];
		$('#tabla-articulos-ordencompra tbody tr.item-ordencompra-articulo').each(function () {
			var $row = $(this);
			var sku = ($row.find('.codigoarticulo').val() || '').trim();
			var desc = ($row.find('.descripcionarticulo').val() || '').trim();
			var artId = ($row.find('.articulo_id').val() || '').trim();
			if (!artId && !sku && !desc) {
				return;
			}
			var entregas = ocParseEntregasJson($row);
			var cantLinea = parseFloat($row.find('.cantidad-linea').val()) || 0;
			var porFecha = {};
			var totalEnt = 0;
			entregas.forEach(function (e) {
				var fecha = (e.fecha || '').toString();
				var cant = Number(e.cantidad) || 0;
				if (!fecha || cant <= 0) {
					return;
				}
				porFecha[fecha] = (porFecha[fecha] || 0) + cant;
				totalEnt += cant;
			});
			lineas.push({
				sku: sku,
				descripcion: desc,
				cantidad_linea: cantLinea,
				porFecha: porFecha,
				total_entregas: totalEnt
			});
		});
		return lineas;
	}

	function ocRenderEntregaSemanalResumen() {
		var lineas = ocRecolectarLineasEntregaSemanalResumen();
		var fechasSet = {};
		lineas.forEach(function (l) {
			Object.keys(l.porFecha).forEach(function (f) {
				fechasSet[f] = true;
			});
		});
		var fechas = Object.keys(fechasSet).sort();
		var $thead = $('#oc-entrega-semanal-resumen-thead').empty();
		var $tbody = $('#oc-entrega-semanal-resumen-tbody').empty();
		var $tfoot = $('#oc-entrega-semanal-resumen-tfoot').empty();
		var $vacio = $('#oc-entrega-semanal-resumen-vacio');

		if (!lineas.length) {
			$vacio.removeClass('d-none').text('No hay artículos en la grilla.');
			return;
		}

		var $hr = $('<tr/>');
		$hr.append($('<th class="text-nowrap"/>').text('SKU'));
		$hr.append($('<th/>').text('Descripción'));
		fechas.forEach(function (f) {
			$hr.append(
				$('<th class="text-right text-nowrap"/>')
					.attr('title', f)
					.text(ocFmtFechaEntregaDdMmYyyy(f))
			);
		});
		$hr.append($('<th class="text-right text-nowrap"/>').text('Total entregas'));
		$hr.append($('<th class="text-right text-nowrap"/>').text('Cant. línea'));
		$thead.append($hr);

		var totPorFecha = {};
		fechas.forEach(function (f) { totPorFecha[f] = 0; });
		var totEntregas = 0;
		var totCantLinea = 0;
		var hayEntregas = fechas.length > 0;

		lineas.forEach(function (l) {
			var $tr = $('<tr/>');
			$tr.append($('<td class="text-nowrap"/>').text(l.sku || '—'));
			$tr.append($('<td/>').text(l.descripcion || '—'));
			fechas.forEach(function (f) {
				var v = l.porFecha[f] || 0;
				totPorFecha[f] += v;
				$tr.append(
					$('<td class="text-right"/>').text(v > 0 ? ocFmtCantEntrega(v) : '—')
				);
			});
			totEntregas += l.total_entregas;
			totCantLinea += l.cantidad_linea;
			$tr.append($('<td class="text-right font-weight-bold"/>').text(ocFmtCantEntrega(l.total_entregas)));
			$tr.append($('<td class="text-right"/>').text(ocFmtCantEntrega(l.cantidad_linea)));
			$tbody.append($tr);
		});

		var $fr = $('<tr/>');
		$fr.append($('<th colspan="2" class="text-right"/>').text('Totales'));
		fechas.forEach(function (f) {
			$fr.append(
				$('<th class="text-right"/>').text(ocFmtCantEntrega(totPorFecha[f]))
			);
		});
		$fr.append($('<th class="text-right"/>').text(ocFmtCantEntrega(totEntregas)));
		$fr.append($('<th class="text-right"/>').text(ocFmtCantEntrega(totCantLinea)));
		$tfoot.append($fr);

		if (!hayEntregas) {
			$vacio.removeClass('d-none').text('No hay entregas semanales cargadas; se listan los artículos con cantidad de línea.');
		} else {
			$vacio.addClass('d-none');
		}

		var nOc = ($('#numeroordencompra_show').val() || '').toString().trim();
		var sub = 'Una fila por artículo; cada columna es una fecha de entrega. Totales por artículo y por fecha.';
		if (nOc) {
			sub = 'OC Nº ' + nOc + ' — ' + sub;
		}
		$('#oc-entrega-semanal-resumen-subtitulo').text(sub);
	}

	$(document).on('click', '.oc-abrir-entrega-semanal-resumen', function () {
		if (!ocEntregaSemanalHabilitada()) {
			return;
		}
		ocRenderEntregaSemanalResumen();
		$('#modalOcEntregaSemanalResumen').modal('show');
	});

	function ocJsonParse(id, fallback) {
		var $el = $('#' + id);
		if (!$el.length) {
			return fallback;
		}
		try {
			return JSON.parse($el.text());
		} catch (e) {
			return fallback;
		}
	}

	var ocMonedas = ocJsonParse('oc-json-monedas', []);
	var ocFormapagos = ocJsonParse('oc-json-formapagos', []);
	var ocComprobantesState = [];
	/** Total orden y moneda de referencia (misma lógica que el panel de totales). */
	var ocTotalesReferencia = { total: 0, moneda_id: 1 };
	/** Datos heredados del proveedor para precargar el primer comprobante a venir. */
	var ocProveedorCondicionpagoId = null;
	var ocProveedorFormapagoId = null;
	/** Se intenta precargar el comprobante default hasta tener total (>0) tras elegir proveedor. */
	var ocAutocompPendiente = false;

	function ocMonedaAbrev(monedaId) {
		var m = ocMonedas.find(function (x) { return String(x.id) === String(monedaId); });
		return m && m.abrev ? m.abrev : String(monedaId);
	}

	function ocPrimerFormapagoId() {
		return (ocFormapagos[0] && ocFormapagos[0].id) ? ocFormapagos[0].id : 1;
	}

	function ocRefrescarCotizacionComprobante(idx, cb) {
		var c = ocComprobantesState[idx];
		if (!c) {
			if (cb) {
				cb();
			}
			return;
		}
		var fecha = ($('#fecha').val() || '').substring(0, 10);
		var mid = parseInt(c.moneda_id, 10) || 1;
		if (!fecha || mid === ocMonedaPesoId) {
			c.cotizacion = 1;
			if (cb) {
				cb();
			}
			return;
		}
		$.get(carpetaBase + '/compras/ordencompra/cotizacion-moneda-fecha', { fecha: fecha, moneda_id: mid })
			.done(function (res) {
				if (res && res.cotizacion != null && !Number.isNaN(parseFloat(res.cotizacion))) {
					c.cotizacion = parseFloat(res.cotizacion);
				} else {
					c.cotizacion = 1;
				}
				if (cb) {
					cb();
				}
			})
			.fail(function () {
				c.cotizacion = 1;
				if (cb) {
					cb();
				}
			});
	}

	function ocParseComprobantesFromHidden() {
		var raw = ($('#comprobantes_json').val() || '').trim();
		try {
			ocComprobantesState = JSON.parse(raw);
			if (!Array.isArray(ocComprobantesState)) {
				ocComprobantesState = [];
			}
		} catch (e) {
			ocComprobantesState = [];
		}
	}

	function ocSyncComprobantesToHidden() {
		var json = JSON.stringify(ocComprobantesState);
		$('#comprobantes_json').val(json);
	}

	function ocDetalleCuotaLineaDesdeComp(c, sufijoCuota) {
		var base = c && c.detalle != null ? String(c.detalle).trim() : '';
		var suf = (sufijoCuota || '').trim();
		if (base && suf) {
			return base + ' — ' + suf;
		}
		if (base) {
			return base;
		}
		return suf;
	}

	function ocEtiquetaEstadoComprobante(estado) {
		var e = String(estado || 'PENDIENTE').toUpperCase();
		if (e === 'CARGADO') {
			return 'Ya cargado';
		}
		return 'Pendiente';
	}

	/** Fecha ASC: usados/históricos arriba; a igual fecha, CARGADO antes que PENDIENTE. */
	function ocOrdenarComprobantesPorFecha() {
		ocComprobantesState.sort(function (a, b) {
			var fa = String((a && a.fechavencimiento) || '').substring(0, 10);
			var fb = String((b && b.fechavencimiento) || '').substring(0, 10);
			if (fa !== fb) {
				if (!fa) {
					return 1;
				}
				if (!fb) {
					return -1;
				}
				return fa < fb ? -1 : 1;
			}
			var ea = String((a && a.estado) || 'PENDIENTE').toUpperCase();
			var eb = String((b && b.estado) || 'PENDIENTE').toUpperCase();
			if (ea === 'CARGADO' && eb !== 'CARGADO') {
				return -1;
			}
			if (eb === 'CARGADO' && ea !== 'CARGADO') {
				return 1;
			}
			return 0;
		});
	}

	function ocRenderTablaComprobantes() {
		ocOrdenarComprobantesPorFecha();
		var $b = $('#oc_tabla_comprobantes_body').empty();
		ocComprobantesState.forEach(function (c, idx) {
			var nCuotas = (c.cuotas && c.cuotas.length) ? c.cuotas.length : 0;
			var estado = String(c.estado || 'PENDIENTE').toUpperCase();
			var $tr = $('<tr/>');
			if (estado === 'CARGADO') {
				$tr.addClass('table-secondary');
			}
			$tr.append('<td>' + (idx + 1) + '</td>');
			$tr.append('<td>' + $('<div>').text(c.tipocomprobante || '').html() + '</td>');
			var $tdEst = $('<td/>');
			var badgeClass = estado === 'CARGADO' ? 'badge badge-secondary' : 'badge badge-info';
			$tdEst.append($('<span/>').addClass(badgeClass).text(ocEtiquetaEstadoComprobante(estado)));
			$tr.append($tdEst);
			$tr.append('<td>' + $('<div>').text(c.fechavencimiento || '').html() + '</td>');
			$tr.append('<td class="text-right">' + (c.monto != null ? Number(c.monto).toFixed(2) : '') + '</td>');
			$tr.append('<td>' + $('<div>').text(ocMonedaAbrev(c.moneda_id)).html() + '</td>');
			var detRaw = c.detalle != null ? String(c.detalle) : '';
			var $tdDet = $('<td class="align-top" style="max-width:14rem"/>');
			var $detDiv = $('<div class="text-truncate small"/>').text(detRaw);
			if (detRaw.length) {
				$detDiv.attr('title', detRaw);
			}
			$tdDet.append($detDiv);
			$tr.append($tdDet);
			$tr.append('<td>' + nCuotas + '</td>');
			var acc = $('<td class="text-nowrap"/>');
			if ($('#oc_btn_agregar_comprobante').length) {
				acc.append($('<button type="button" class="btn btn-sm btn-outline-secondary mr-1 oc-comp-editar-cab"/>').text('Editar').data('idx', idx));
				acc.append($('<button type="button" class="btn btn-sm btn-outline-secondary mr-1 oc-comp-editar-cuotas"/>').text('Cuotas').data('idx', idx));
				acc.append($('<button type="button" class="btn btn-sm btn-outline-danger oc-comp-quitar"/>').text('Quitar').data('idx', idx));
			}
			$tr.append(acc);
			$b.append($tr);
		});
	}

	function ocAbrirModalCabecera(idx) {
		$('#oc_comp_cab_idx').val(String(idx));
		if (idx < 0) {
			$('#oc_comp_cab_tipo').val('FACTURA');
			$('#oc_comp_cab_fecha').val($('#fecha').val() || '');
			var pend = ocMontoPendienteComprobantesMonedaRef();
			if (pend != null && pend > 0) {
				$('#oc_comp_cab_monto').val(pend);
				$('#oc_comp_cab_moneda').val(String(ocTotalesReferencia.moneda_id));
			} else {
				$('#oc_comp_cab_monto').val('');
				$('#oc_comp_cab_moneda').val($('#oc_comp_cab_moneda option:first').val());
			}
			$('#oc_comp_cab_detalle').val('');
		} else {
			var c = ocComprobantesState[idx];
			$('#oc_comp_cab_tipo').val(c.tipocomprobante || 'FACTURA');
			$('#oc_comp_cab_fecha').val((c.fechavencimiento || '').substring(0, 10));
			$('#oc_comp_cab_monto').val(c.monto != null ? c.monto : '');
			$('#oc_comp_cab_moneda').val(String(c.moneda_id || ''));
			$('#oc_comp_cab_detalle').val(c.detalle || '');
		}
		$('#modalOcComprobanteCabecera').modal('show');
	}

	function ocGuardarCabeceraModal() {
		var idx = parseInt($('#oc_comp_cab_idx').val(), 10);
		var obj = {
			tipocomprobante: $('#oc_comp_cab_tipo').val() || 'FACTURA',
			fechavencimiento: $('#oc_comp_cab_fecha').val() || '',
			monto: parseFloat($('#oc_comp_cab_monto').val()) || 0,
			moneda_id: parseInt($('#oc_comp_cab_moneda').val(), 10) || 1,
			cotizacion: null,
			detalle: $('#oc_comp_cab_detalle').val() || null,
			condicionpago_id: null,
			cantidadcuota: null,
			cuotas: []
		};
		if (idx >= 0 && ocComprobantesState[idx]) {
			var prev = ocComprobantesState[idx];
			if (prev.id) {
				obj.id = prev.id;
			}
			obj.cuotas = prev.cuotas ? prev.cuotas.slice() : [];
			obj.condicionpago_id = prev.condicionpago_id != null ? prev.condicionpago_id : null;
			obj.cantidadcuota = prev.cantidadcuota != null ? prev.cantidadcuota : null;
			obj.cotizacion = prev.cotizacion != null ? prev.cotizacion : null;
			obj.estado = prev.estado != null ? prev.estado : 'PENDIENTE';
		} else {
			obj.estado = 'PENDIENTE';
		}
		var finalIdx;
		if (idx < 0) {
			ocComprobantesState.push(obj);
			finalIdx = ocComprobantesState.length - 1;
		} else {
			ocComprobantesState[idx] = obj;
			finalIdx = idx;
		}
		ocRefrescarCotizacionComprobante(finalIdx, function () {
			ocSyncComprobantesToHidden();
			ocRenderTablaComprobantes();
			$('#modalOcComprobanteCabecera').modal('hide');
		});
	}

	function ocHtmlOptionsFormapago(selectedId) {
		var h = '';
		var sel = selectedId || ocPrimerFormapagoId();
		ocFormapagos.forEach(function (f) {
			h += '<option value="' + f.id + '"' + (String(f.id) === String(sel) ? ' selected' : '') + '>' + $('<div>').text(f.nombre).html() + '</option>';
		});
		return h;
	}

	function ocFmtEsAr(v) {
		var n = Number(v);
		if (Number.isNaN(n)) {
			n = 0;
		}
		return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function ocRound2Money(x) {
		var n = Number(x);
		if (!Number.isFinite(n)) {
			return 0;
		}
		return Math.round(n * 100) / 100;
	}

	/** Reusa id de cuota por posición al regenerar, para no romper FK con facturas. */
	function ocPreservarIdsCuotasPrevias(prevCuotas, nuevas) {
		var prev = Array.isArray(prevCuotas) ? prevCuotas : [];
		return (nuevas || []).map(function (q, i) {
			var out = Object.assign({}, q);
			if (prev[i] && prev[i].id) {
				out.id = prev[i].id;
			} else {
				delete out.id;
			}
			return out;
		});
	}

	(function ocInitTotalesReferenciaDesdeDom() {
		var ini = ocJsonParse('oc-json-totales-referencia', { total: 0, moneda_id: 1 });
		ocTotalesReferencia.total = ocRound2Money(parseFloat(ini.total) || 0);
		ocTotalesReferencia.moneda_id = parseInt(ini.moneda_id, 10) || 1;
	})();

	/**
	 * Total de la OC (moneda del primer ítem) menos comprobantes a venir ya cargados en esa moneda.
	 * Se usa como monto sugerido al agregar un comprobante para que la suma pueda alinearse al total de la orden.
	 */
	function ocMontoPendienteComprobantesMonedaRef() {
		var totalRef = ocTotalesReferencia.total;
		var midRef = parseInt(ocTotalesReferencia.moneda_id, 10) || 1;
		if (!Number.isFinite(totalRef) || totalRef <= 0) {
			return null;
		}
		var sum = 0;
		ocComprobantesState.forEach(function (c) {
			if (parseInt(c.moneda_id, 10) === midRef && c.monto != null) {
				sum += parseFloat(c.monto) || 0;
			}
		});
		return ocRound2Money(Math.max(0, totalRef - sum));
	}

	/**
	 * Precarga el primer comprobante a venir (FACTURA) heredando la condición de pago del
	 * proveedor y al menos una cuota con la forma de pago cargada en su ABM. Solo actúa si
	 * todavía no hay comprobantes cargados y ya se conoce el total de la orden.
	 * Si el proveedor no tiene condición de pago, no precarga (el backend detiene la grabación).
	 */
	function ocIntentarPrecargaComprobanteDefault() {
		if (!ocAutocompPendiente) {
			return;
		}
		if (ocComprobantesState.length > 0) {
			ocAutocompPendiente = false;
			return;
		}
		if (!ocProveedorCondicionpagoId) {
			// Sin condición de pago del proveedor no se puede armar el comprobante:
			// se deja pendiente por si el dato llega, y el backend valida al grabar.
			return;
		}
		var total = ocRound2Money(ocTotalesReferencia.total);
		if (!(total > 0)) {
			// Aún sin importe (ítems no cargados): reintentar cuando se recalculen totales.
			return;
		}
		ocAutocompPendiente = false;

		var mid = parseInt(ocTotalesReferencia.moneda_id, 10) || 1;
		var fecha = ($('#fecha').val() || '').substring(0, 10) || '';
		var fp = ocProveedorFormapagoId || ocPrimerFormapagoId();
		var cp = ocProveedorCondicionpagoId;

		var comp = {
			tipocomprobante: 'FACTURA',
			fechavencimiento: fecha,
			monto: total,
			moneda_id: mid,
			cotizacion: null,
			detalle: null,
			condicionpago_id: cp,
			cantidadcuota: null,
			cuotas: []
		};
		ocComprobantesState.push(comp);
		var idx = ocComprobantesState.length - 1;

		function finalizarConCuotas(cuotas) {
			var cotComp = ocComprobantesState[idx] && ocComprobantesState[idx].cotizacion != null
				? ocComprobantesState[idx].cotizacion
				: 1;
			var sum = 0;
			cuotas.forEach(function (q) {
				q.monto = ocRound2Money(q.monto);
				q.moneda_id = mid;
				q.formapago_id = fp;
				q.cotizacion = cotComp;
				sum += q.monto;
			});
			var dif = ocRound2Money(total - sum);
			if (Math.abs(dif) >= 0.01 && cuotas.length) {
				cuotas[cuotas.length - 1].monto = ocRound2Money(cuotas[cuotas.length - 1].monto + dif);
			}
			if (ocComprobantesState[idx]) {
				ocComprobantesState[idx].cuotas = ocPreservarIdsCuotasPrevias(
					ocComprobantesState[idx].cuotas,
					cuotas
				);
				ocComprobantesState[idx].condicionpago_id = cp;
				ocComprobantesState[idx].cantidadcuota = ocComprobantesState[idx].cuotas.length;
			}
			ocSyncComprobantesToHidden();
			ocRenderTablaComprobantes();
		}

		ocRefrescarCotizacionComprobante(idx, function () {
			$.post(carpetaBase + '/compras/ordencompra/sugerir-cuotas-condicionpago', {
				_token: $('meta[name="csrf-token"]').attr('content'),
				condicionpago_id: cp,
				fecha_base: fecha,
				monto: total,
				moneda_id: mid
			}).done(function (res) {
				var cuotas = (res && res.cuotas && res.cuotas.length) ? res.cuotas : [{
					fechavencimiento: fecha,
					monto: total,
					moneda_id: mid,
					cotizacion: 1,
					formapago_id: fp,
					detalle: 'Cuota 1'
				}];
				finalizarConCuotas(cuotas);
			}).fail(function () {
				finalizarConCuotas([{
					fechavencimiento: fecha,
					monto: total,
					moneda_id: mid,
					cotizacion: 1,
					formapago_id: fp,
					detalle: 'Cuota 1'
				}]);
			});
		});
	}

	/** Suma `add` meses calendario a YYYY-MM-DD (ajusta día si el mes destino es más corto). */
	function ocYmdAddMonths(ymd, add) {
		var s = String(ymd || '').substring(0, 10);
		var p = s.split('-');
		if (p.length !== 3) {
			return s;
		}
		var y = parseInt(p[0], 10);
		var mo = parseInt(p[1], 10) - 1;
		var da = parseInt(p[2], 10);
		if (!Number.isFinite(y) || !Number.isFinite(mo) || !Number.isFinite(da)) {
			return s;
		}
		var tm = mo + add;
		var first = new Date(y, tm, 1);
		var ld = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
		var day = Math.min(da, ld);
		first.setDate(day);
		var yy = first.getFullYear();
		var mm = first.getMonth() + 1;
		var dd = first.getDate();
		return yy + '-' + (mm < 10 ? '0' : '') + mm + '-' + (dd < 10 ? '0' : '') + dd;
	}

	/** Reparte `total` en `n` importes en centavos; la última fila absorbe el resto. */
	function ocMontosCuotasIguales(n, total) {
		total = ocRound2Money(total);
		if (n < 1 || total <= 0) {
			return [];
		}
		var cents = Math.round(total * 100);
		var base = Math.floor(cents / n);
		var out = [];
		var used = 0;
		for (var i = 0; i < n - 1; i++) {
			out.push(base / 100);
			used += base;
		}
		out.push((cents - used) / 100);
		return out;
	}

	function ocParseMontoCuota(v) {
		if (v === null || v === undefined || v === '') {
			return 0;
		}
		var n = parseFloat(String(v).replace(/\s/g, '').replace(',', '.'));
		return Number.isFinite(n) ? n : 0;
	}

	function ocParseMonedaId(v, fallback) {
		var fb = parseInt(String(fallback != null ? fallback : 1), 10);
		if (!Number.isFinite(fb) || fb < 1) {
			fb = 1;
		}
		if (v === null || v === undefined || v === '') {
			return fb;
		}
		var n = parseInt(String(v), 10);
		return Number.isFinite(n) && n > 0 ? n : fb;
	}

	function ocRenderCuotasTbody(cuotas) {
		var $tb = $('#oc_cuotas_tbody').empty();
		var compIdx = parseInt($('#oc_cuotas_comp_idx').val(), 10);
		var comp = !Number.isNaN(compIdx) ? ocComprobantesState[compIdx] : null;
		var compCot = comp && comp.cotizacion != null ? Number(comp.cotizacion) : 1;
		if (Number.isNaN(compCot)) {
			compCot = 1;
		}
		var midRow = parseInt($('#oc_cuotas_moneda_calc').val(), 10) || (comp ? parseInt(comp.moneda_id, 10) : 1) || 1;
		var abrevTxt = ocMonedaAbrev(midRow);
		var abrevEsc = $('<div/>').text(abrevTxt).html();
		(cuotas || []).forEach(function (q, i) {
			var $tr = $('<tr/>').attr('data-row', i);
			if (q && q.id) {
				$tr.attr('data-cuota-id', String(q.id));
			}
			$tr.append('<td class="align-middle">' + (i + 1) + '</td>');
			$tr.append('<td><input type="date" class="form-control form-control-sm oc-inp-fv" value="' + String(q.fechavencimiento || '').substring(0, 10) + '"/></td>');
			$tr.append('<td><input type="number" step="0.01" class="form-control form-control-sm oc-inp-monto" value="' + (q.monto != null ? q.monto : '') + '"/></td>');
			var $tdMon = $('<td/>');
			$tdMon.append(
				'<span class="form-control form-control-sm oc-inp-moneda-readonly border bg-light text-muted mb-0 d-block py-1">' +
					abrevEsc +
					'</span>'
			);
			$tdMon.append('<input type="hidden" class="oc-inp-moneda" value="' + midRow + '"/>');
			$tdMon.append('<input type="hidden" class="oc-inp-cotiz" value="' + String(compCot) + '"/>');
			$tr.append($tdMon);
			$tr.append('<td><select class="form-control form-control-sm oc-inp-fp">' + ocHtmlOptionsFormapago(q.formapago_id || ocPrimerFormapagoId()) + '</select></td>');
			var $tdDet = $('<td/>');
			$tdDet.append($('<input type="text" class="form-control form-control-sm oc-inp-det"/>').val(q.detalle || ''));
			$tr.append($tdDet);
			$tr.append('<td><button type="button" class="btn btn-sm btn-outline-danger oc-cuota-fila-quitar">&times;</button></td>');
			$tb.append($tr);
		});
		ocActualizarResumenCuotasModal();
	}

	function ocLeerCuotasDesdeTbody() {
		var out = [];
		$('#oc_cuotas_tbody tr').each(function () {
			var $tr = $(this);
			var row = {
				fechavencimiento: $tr.find('.oc-inp-fv').val() || '',
				monto: parseFloat($tr.find('.oc-inp-monto').val()) || 0,
				moneda_id: parseInt($tr.find('.oc-inp-moneda').val(), 10) || 1,
				cotizacion: (function () {
					var cv = parseFloat($tr.find('.oc-inp-cotiz').val());
					return cv > 0 ? cv : 1;
				})(),
				formapago_id: (function () {
					var v = parseInt($tr.find('.oc-inp-fp').val(), 10);
					return v > 0 ? v : ocPrimerFormapagoId();
				})(),
				detalle: $tr.find('.oc-inp-det').val() || ''
			};
			var cuotaId = parseInt($tr.attr('data-cuota-id'), 10);
			if (cuotaId > 0) {
				row.id = cuotaId;
			}
			out.push(row);
		});
		return out;
	}

	function ocMontoReferenciaCuotasModal() {
		var idx = parseInt($('#oc_cuotas_comp_idx').val(), 10);
		var raw = $('#oc_cuotas_monto_calc').val();
		if (raw !== '' && raw != null) {
			var parsed = parseFloat(String(raw).replace(',', '.'));
			if (!Number.isNaN(parsed)) {
				return parsed;
			}
		}
		if (!Number.isNaN(idx) && ocComprobantesState[idx] && ocComprobantesState[idx].monto != null) {
			return parseFloat(ocComprobantesState[idx].monto) || 0;
		}
		return 0;
	}

	/** Monto a repartir: prioriza el campo del modal si es > 0, si no el del comprobante. */
	function ocLeerMontoCuotasModalParaComp(idx) {
		var raw = $('#oc_cuotas_monto_calc').val();
		var m = parseFloat(String(raw != null ? raw : '').replace(/\s/g, '').replace(',', '.'));
		if (Number.isFinite(m) && m > 0) {
			return m;
		}
		var c = ocComprobantesState[idx];
		return c && c.monto != null ? parseFloat(c.monto) || 0 : 0;
	}

	function ocActualizarResumenCuotasModal() {
		if (!$('#oc_cuotas_resumen_wrap').length) {
			return;
		}
		var montoRef = ocMontoReferenciaCuotasModal();
		var cuotas = ocLeerCuotasDesdeTbody();
		var sum = 0;
		for (var i = 0; i < cuotas.length; i++) {
			sum += parseFloat(cuotas[i].monto) || 0;
		}
		var diff = montoRef - sum;
		var midRef = parseInt($('#oc_cuotas_moneda_calc').val(), 10) || 1;
		var monTxt = ocMonedaAbrev(midRef);
		function suf(n) {
			return ocFmtEsAr(n) + ' ' + monTxt;
		}
		$('#oc_cuotas_resumen_monto_ref').text(suf(montoRef));
		$('#oc_cuotas_resumen_total_cuotas').text(suf(sum));
		var $falta = $('#oc_cuotas_resumen_falta');
		$falta.removeClass('text-danger text-success');
		if (Math.abs(diff) < 0.02) {
			$falta.html('<span class="text-success">' + suf(0) + ' — coincide</span>');
		} else if (diff > 0.02) {
			$falta.text(suf(diff));
		} else {
			$falta.html('Excedente ' + suf(-diff)).addClass('text-danger');
		}
	}

	function ocAbrirModalCuotas(idx) {
		$('#oc_cuotas_comp_idx').val(String(idx));
		var c = ocComprobantesState[idx];
		if (!c) {
			return;
		}
		var detCab = c.detalle != null ? String(c.detalle).trim() : '';
		if ($('#oc_cuotas_comp_detalle_text').length) {
			$('#oc_cuotas_comp_detalle_text').text(detCab || '—');
		}
		$('#oc_cuotas_monto_calc').val(c.monto != null ? c.monto : '');
		$('#oc_cuotas_moneda_calc').prop('disabled', false).val(String(c.moneda_id || '')).prop('disabled', true);
		$('#oc_cuotas_fecha_base').val($('#fecha').val() || '');
		var fvPrimera = (c.fechavencimiento || '').substring(0, 10) || ($('#fecha').val() || '').substring(0, 10);
		if ($('#oc_cuotas_fecha_primera_manual').length) {
			$('#oc_cuotas_fecha_primera_manual').val(fvPrimera);
		}
		$('#oc_cuotas_condicionpago_id').val(
			c.condicionpago_id
				? String(c.condicionpago_id)
				: ($('#condicionpago_id').val() || '')
		);
		ocRefrescarCotizacionComprobante(idx, function () {
			ocSyncComprobantesToHidden();
			ocRenderCuotasTbody(c.cuotas && c.cuotas.length ? c.cuotas : []);
			$('#modalOcComprobanteCuotas').modal('show');
		});
	}

	function ocValidarCuotasContraMontoModal() {
		var idx = parseInt($('#oc_cuotas_comp_idx').val(), 10);
		if (isNaN(idx) || !ocComprobantesState[idx]) {
			return true;
		}
		var c = ocComprobantesState[idx];
		var cuotas = ocLeerCuotasDesdeTbody();
		if (!cuotas.length) {
			return true;
		}
		var montoComp = ocRound2Money(ocParseMontoCuota(ocMontoReferenciaCuotasModal()));
		var selMon = $('#oc_cuotas_moneda_calc').val();
		var monedaComp = ocParseMonedaId(selMon !== '' && selMon != null ? selMon : c.moneda_id, 1);
		var sum = 0;
		for (var i = 0; i < cuotas.length; i++) {
			var qm = ocParseMonedaId(cuotas[i].moneda_id, monedaComp);
			if (qm !== monedaComp) {
				alert('Todas las cuotas deben usar la misma moneda que el comprobante a venir.');
				return false;
			}
			sum += ocParseMontoCuota(cuotas[i].monto);
		}
		sum = ocRound2Money(sum);
		if (Math.abs(sum - montoComp) > 0.02) {
			alert(
				'La suma de cuotas (' +
					sum.toFixed(2) +
					') no coincide con el monto del comprobante (' +
					montoComp.toFixed(2) +
					').'
			);
			return false;
		}
		return true;
	}

	function ocGuardarCuotasModal() {
		if (!ocValidarCuotasContraMontoModal()) {
			return;
		}
		var idx = parseInt($('#oc_cuotas_comp_idx').val(), 10);
		if (isNaN(idx) || !ocComprobantesState[idx]) {
			return;
		}
		ocComprobantesState[idx].cuotas = ocLeerCuotasDesdeTbody();
		ocComprobantesState[idx].condicionpago_id = $('#oc_cuotas_condicionpago_id').val() ? parseInt($('#oc_cuotas_condicionpago_id').val(), 10) : null;
		ocComprobantesState[idx].monto = ocMontoReferenciaCuotasModal();
		var midCalc = parseInt($('#oc_cuotas_moneda_calc').val(), 10);
		if (!Number.isNaN(midCalc) && midCalc > 0) {
			ocComprobantesState[idx].moneda_id = midCalc;
		}
		ocRefrescarCotizacionComprobante(idx, function () {
			var cotComp = ocComprobantesState[idx].cotizacion != null ? ocComprobantesState[idx].cotizacion : 1;
			(ocComprobantesState[idx].cuotas || []).forEach(function (q) {
				q.cotizacion = cotComp;
				q.moneda_id = parseInt(ocComprobantesState[idx].moneda_id, 10) || 1;
			});
			ocSyncComprobantesToHidden();
			ocRenderTablaComprobantes();
			$('#modalOcComprobanteCuotas').modal('hide');
		});
	}

	if ($('#comprobantes_json').length) {
		ocParseComprobantesFromHidden();
		ocRenderTablaComprobantes();
		ocSyncComprobantesToHidden();
	}

	// Al abrir el formulario con un proveedor ya elegido (crear con requisición/plantilla o editar
	// una OC sin comprobante), se heredan sus datos y se intenta precargar el comprobante a venir.
	(function ocInicializarDefaultsProveedor() {
		var provId = $('#proveedor_id').val();
		if (!provId || ocComprobantesState.length > 0) {
			return;
		}
		$.get(carpetaBase + '/compras/leerproveedor/' + provId, function (data) {
			ocAplicarDefaultsComprobanteDesdeProveedor(data);
		});
	})();

	$(document).on('click', '#oc_btn_agregar_comprobante', function () {
		ocAbrirModalCabecera(-1);
	});

	$(document).on('click', '#oc_comp_cab_guardar', function () {
		ocGuardarCabeceraModal();
	});

	$(document).on('click', '.oc-comp-editar-cab', function () {
		ocAbrirModalCabecera(parseInt($(this).data('idx'), 10));
	});

	$(document).on('click', '.oc-comp-editar-cuotas', function () {
		ocAbrirModalCuotas(parseInt($(this).data('idx'), 10));
	});

	$(document).on('click', '.oc-comp-quitar', function () {
		var idx = parseInt($(this).data('idx'), 10);
		if (!isNaN(idx)) {
			ocComprobantesState.splice(idx, 1);
			ocSyncComprobantesToHidden();
			ocRenderTablaComprobantes();
		}
	});

	$('#oc_cuotas_btn_generar_cond').on('click', function () {
		var idx = parseInt($('#oc_cuotas_comp_idx').val(), 10);
		var cp = parseInt($('#oc_cuotas_condicionpago_id').val(), 10) || 0;
		var fb = $('#oc_cuotas_fecha_base').val();
		var m = parseFloat($('#oc_cuotas_monto_calc').val()) || 0;
		var mon = parseInt($('#oc_cuotas_moneda_calc').val(), 10) || 1;
		if (!cp || !fb || m <= 0) {
			alert('Seleccione condición de pago, fecha base y monto.');
			return;
		}
		if (ocComprobantesState[idx]) {
			ocComprobantesState[idx].monto = m;
			ocComprobantesState[idx].moneda_id = mon;
		}
		ocRefrescarCotizacionComprobante(idx, function () {
			var cotComp = ocComprobantesState[idx] && ocComprobantesState[idx].cotizacion != null ? ocComprobantesState[idx].cotizacion : 1;
			$.post(carpetaBase + '/compras/ordencompra/sugerir-cuotas-condicionpago', {
				_token: $('meta[name="csrf-token"]').attr('content'),
				condicionpago_id: cp,
				fecha_base: fb,
				monto: m,
				moneda_id: mon
			}).done(function (res) {
				var cuotas = (res && res.cuotas) ? res.cuotas : [];
				var cab =
					ocComprobantesState[idx] && ocComprobantesState[idx].detalle != null
						? String(ocComprobantesState[idx].detalle).trim()
						: '';
				cuotas.forEach(function (q) {
					if (!q.formapago_id) {
						q.formapago_id = ocPrimerFormapagoId();
					}
					q.cotizacion = cotComp;
					if (cab) {
						var qd = q.detalle != null ? String(q.detalle).trim() : '';
						q.detalle = qd ? cab + ' — ' + qd : cab;
					}
				});
				if (ocComprobantesState[idx]) {
					cuotas = ocPreservarIdsCuotasPrevias(ocComprobantesState[idx].cuotas, cuotas);
					ocComprobantesState[idx].cuotas = cuotas;
					ocComprobantesState[idx].condicionpago_id = cp;
				}
				ocSyncComprobantesToHidden();
				ocRenderCuotasTbody(cuotas);
			});
		});
	});

	$('#oc_cuotas_btn_crear_manual').on('click', function () {
		var idx = parseInt($('#oc_cuotas_comp_idx').val(), 10);
		var n = parseInt($('#oc_cuotas_cantidad_manual').val(), 10) || 1;
		if (n < 1 || n > 60) {
			alert('Indique una cantidad entre 1 y 60.');
			return;
		}
		var c = ocComprobantesState[idx];
		if (!c) {
			return;
		}
		var total = ocLeerMontoCuotasModalParaComp(idx);
		if (total <= 0) {
			alert('Indique un monto mayor a cero en el campo Monto (solapa superior) o en la cabecera del comprobante.');
			return;
		}
		var montos = ocMontosCuotasIguales(n, total);
		var fv = (c.fechavencimiento || '').substring(0, 10) || ($('#fecha').val() || '').substring(0, 10);
		var mon = parseInt(c.moneda_id, 10) || 1;
		var fp = ocPrimerFormapagoId();
		if (ocComprobantesState[idx]) {
			ocComprobantesState[idx].monto = total;
		}
		$('#oc_cuotas_monto_calc').val(String(total));
		ocRefrescarCotizacionComprobante(idx, function () {
			var cotLine = c.cotizacion != null ? c.cotizacion : 1;
			var cuotas = [];
			for (var i = 0; i < n; i++) {
				cuotas.push({
					fechavencimiento: fv,
					monto: montos[i],
					moneda_id: mon,
					cotizacion: cotLine,
					formapago_id: fp,
					detalle: ocDetalleCuotaLineaDesdeComp(c, 'Cuota ' + (i + 1))
				});
			}
			c.cuotas = ocPreservarIdsCuotasPrevias(c.cuotas, cuotas);
			c.condicionpago_id = null;
			ocSyncComprobantesToHidden();
			ocRenderCuotasTbody(c.cuotas);
		});
	});

	$('#oc_cuotas_btn_mensual').on('click', function () {
		var idx = parseInt($('#oc_cuotas_comp_idx').val(), 10);
		var n = parseInt($('#oc_cuotas_cantidad_manual').val(), 10) || 1;
		if (n < 1 || n > 60) {
			alert('Indique una cantidad entre 1 y 60.');
			return;
		}
		var c = ocComprobantesState[idx];
		if (!c) {
			return;
		}
		var total = ocLeerMontoCuotasModalParaComp(idx);
		if (total <= 0) {
			alert('Indique un monto mayor a cero en el campo Monto (solapa superior) o en la cabecera del comprobante.');
			return;
		}
		var fecha0 = ($('#oc_cuotas_fecha_primera_manual').val() || '').substring(0, 10);
		if (!fecha0) {
			alert('Indique la fecha de la primera cuota.');
			return;
		}
		var montos = ocMontosCuotasIguales(n, total);
		var mon = parseInt(c.moneda_id, 10) || 1;
		var fp = ocPrimerFormapagoId();
		if (ocComprobantesState[idx]) {
			ocComprobantesState[idx].monto = total;
		}
		$('#oc_cuotas_monto_calc').val(String(total));
		ocRefrescarCotizacionComprobante(idx, function () {
			var cotLine = c.cotizacion != null ? c.cotizacion : 1;
			var cuotas = [];
			for (var i = 0; i < n; i++) {
				cuotas.push({
					fechavencimiento: ocYmdAddMonths(fecha0, i),
					monto: montos[i],
					moneda_id: mon,
					cotizacion: cotLine,
					formapago_id: fp,
					detalle: ocDetalleCuotaLineaDesdeComp(c, 'Cuota ' + (i + 1))
				});
			}
			c.cuotas = ocPreservarIdsCuotasPrevias(c.cuotas, cuotas);
			c.condicionpago_id = null;
			ocSyncComprobantesToHidden();
			ocRenderCuotasTbody(c.cuotas);
		});
	});

	$('#oc_cuotas_agregar_fila').on('click', function () {
		var idx = parseInt($('#oc_cuotas_comp_idx').val(), 10);
		var c = ocComprobantesState[idx];
		var cur = ocLeerCuotasDesdeTbody();
		var montoComp = ocMontoReferenciaCuotasModal();
		var sumPrev = 0;
		for (var k = 0; k < cur.length; k++) {
			sumPrev += parseFloat(cur[k].monto) || 0;
		}
		var remainder = Math.round(Math.max(0, montoComp - sumPrev) * 100) / 100;
		var cotLine = c && c.cotizacion != null ? c.cotizacion : 1;
		var midRow = parseInt($('#oc_cuotas_moneda_calc').val(), 10) || (c ? parseInt(c.moneda_id, 10) : 1) || 1;
		cur.push({
			fechavencimiento: (c && c.fechavencimiento) ? String(c.fechavencimiento).substring(0, 10) : $('#fecha').val(),
			monto: remainder,
			moneda_id: midRow,
			cotizacion: cotLine,
			formapago_id: ocPrimerFormapagoId(),
			detalle: ocDetalleCuotaLineaDesdeComp(c, '')
		});
		ocRenderCuotasTbody(cur);
	});

	$(document).on('click', '.oc-cuota-fila-quitar', function () {
		$(this).closest('tr').remove();
		$('#oc_cuotas_tbody tr').each(function (i) {
			$(this).find('td').first().text(i + 1);
		});
		ocActualizarResumenCuotasModal();
	});

	$(document).on(
		'input change',
		'#oc_cuotas_monto_calc, #modalOcComprobanteCuotas .oc-inp-monto',
		function () {
			ocActualizarResumenCuotasModal();
		}
	);

	$('#modalOcComprobanteCuotas').on('hidden.bs.modal', function () {
		$('#oc_cuotas_moneda_calc').prop('disabled', false);
	});

	$('#oc_cuotas_guardar').on('click', function () {
		ocGuardarCuotasModal();
	});

	function ocActualizarAyudaDescuento() {
		var tipo = ($('#descuento_tipo').val() || 'porcentaje');
		var $ayuda = $('#descuento_ayuda');
		if (!$ayuda.length) {
			return;
		}
		if (tipo === 'importe') {
			$ayuda.text('Monto fijo sobre el neto de ítems antes del IVA (en moneda del 1.er ítem).');
		} else {
			$ayuda.text('Porcentaje sobre el neto de ítems antes del IVA.');
		}
	}

	function ocRecalcTotales() {
		if (!$('#oc-panel-totales').length || $('#descuento').length === 0) {
			return;
		}
		var articulo_ids = [];
		var cantidades = [];
		var precios = [];
		var moneda_linea_ids = [];
		var cotizaciones_linea = [];
		$('#tabla-articulos-ordencompra tbody tr.item-ordencompra-articulo').each(function () {
			var $tr = $(this);
			var aid = $tr.find('.articulo_id').val();
			var cant = parseFloat($tr.find('.cantidad-linea').val()) || 0;
			if (!aid || cant <= 0) {
				return;
			}
			articulo_ids.push(aid);
			cantidades.push(cant);
			precios.push(parseFloat($tr.find('.precio-linea').val()) || 0);
			moneda_linea_ids.push(parseInt($tr.find('.oc-moneda-linea').val(), 10) || 1);
			var c = parseFloat($tr.find('.oc-cotizacion-linea').val());
			cotizaciones_linea.push(c > 0 ? c : 1);
		});
		$.post(
			carpetaBase + '/compras/ordencompra/calcular-totales',
			{
				_token: $('meta[name="csrf-token"]').attr('content'),
				fecha: $('#fecha').val() || '',
				descuento: $('#descuento').val(),
				descuento_tipo: $('#descuento_tipo').val() || 'porcentaje',
				articulo_ids: articulo_ids,
				cantidades: cantidades,
				precios: precios,
				moneda_linea_ids: moneda_linea_ids,
				cotizaciones_linea: cotizaciones_linea
			}
		).done(function (res) {
			if (!res || typeof res !== 'object') {
				return;
			}
			if (res.total != null && res.moneda_id != null) {
				ocTotalesReferencia.total = ocRound2Money(parseFloat(res.total) || 0);
				ocTotalesReferencia.moneda_id = parseInt(res.moneda_id, 10) || 1;
				ocIntentarPrecargaComprobanteDefault();
			}
			$('#oc-tot-mon-abrev').text(res.moneda_abrev || '—');
			$('#oc-tot-final-moneda').text(res.moneda_abrev || '—');
			$('#oc-tot-sub').text(ocFmtEsAr(res.subtotal_bruto_sin_iva));
			$('#oc-tot-dto').text('-' + ocFmtEsAr(res.importe_descuento));
			$('#oc-tot-neto').text(ocFmtEsAr(res.neto_sin_iva));
			$('#oc-tot-iva').text(ocFmtEsAr(res.iva_total));
			$('#oc-tot-final').text(ocFmtEsAr(res.total));
			$('.oc-fila-iva-detalle').remove();
			var filas = res.filas_iva || [];
			if (filas.length > 1) {
				var $ivaAnchor = $('#oc-tot-iva').closest('tr');
				filas.forEach(function (fi) {
					var t = Number(fi.tasa) || 0;
					var lbl = 'IVA ' + String(t.toFixed(2)).replace(/\.?0+$/, '') + '%';
					$ivaAnchor.before(
						'<tr class="oc-fila-iva-detalle"><td class="text-muted pl-0">' +
							lbl +
							'</td><td class="text-right text-nowrap">' +
							ocFmtEsAr(fi.importe) +
							'</td></tr>'
					);
				});
			}
		});
	}

	var ocTotalesTimer = null;
	function ocScheduleTotales() {
		if ($('#descuento').prop('readonly')) {
			return;
		}
		clearTimeout(ocTotalesTimer);
		ocTotalesTimer = setTimeout(ocRecalcTotales, 320);
	}

	$('#agrega_renglon_ordencompra_articulo').on('click', function () {
		var $tbody = $('#tabla-articulos-ordencompra tbody');
		var $table = $('#tabla-articulos-ordencompra');
		var $first = $tbody.find('tr.item-ordencompra-articulo').first();
		var $firstSub = $first.next('tr.item-ordencompra-articulo-sub');
		if (!$first.length || !$firstSub.length) {
			return;
		}
		var $clone = $first.clone();
		var $cloneSub = $firstSub.clone();
		$clone.find('input,select').not('[type="hidden"]').val('');
		$clone.find('.oc-cotizacion-linea').val('1');
		$clone.find('input[type="hidden"]').filter('.ordencompra_articulo_id').val('');
		$clone.find('input[type="hidden"]').filter('.articulo_id').val('');
		$clone.find('.partidagasto_id').val('');
		$clone.find('.capex_id').val('');
		$clone.find('.oc-requisicion-articulo-id').val('');
		$clone.find('.oc-precio-origen-tipo').val('');
		$clone.find('.oc-precio-origen-ref-id').val('');
		$clone.find('.oc-precio-origen-etiqueta').val('');
		$clone.find('.codigopartidagasto').val('');
		$clone.find('.codigocapex').val('');
		$clone.find('.descripcionpartidagasto').val('');
		$clone.find('.descripcioncapex').val('');
		$clone.find('.oc-ta-detalle-linea').val('');
		$clone.find('.oc-entregas-semanal-json').val('[]');
		$clone.find('.oc-entregas-count').text('').addClass('d-none');
		$clone.find('.cantidad-linea').removeClass('oc-cant-desde-entregas').prop('readonly', false).attr('title', '');
		$clone.find('select.ms-color-id, select.ms-talle-id').val('').attr('data-selected', '');
		$clone.attr('data-maneja-stock-color-talle', '0');
		ocLimpiarCantidadAlternativaHint($clone);
		ocLimpiarPesoLinea($clone);
		$clone.find('.linea-articulo-proveedor-id,.linea-codigo-articulo-proveedor,.linea-coef-conversion,.linea-um-compra-abrev').val('');
		$clone.find('.linea-proveedor-etiqueta').text('—').attr('title', '');
		$clone.find('.linea-conversion-hint').addClass('d-none').html('');
		$cloneSub.find('.oc-entregas-semanal-resumen').text('—').removeAttr('title');
		$tbody.append($clone);
		$tbody.append($cloneSub);
		ocRefreshDetalleLineaBadge($clone);
		ocRefreshOrigenPrecioResumen($clone);
		ocRefreshEntregasSemanalUi($clone);
		$clone.find('select').each(function () {
			$(this).prop('selectedIndex', 0);
		});
		var defCc = $table.attr('data-oc-cc-destino-default');
		if (defCc !== undefined && defCc !== null && defCc !== '') {
			var $ccDest = $clone.find('select[name="centrocostodestino_ids[]"]');
			if ($ccDest.find('option[value="' + defCc + '"]').length) {
				$ccDest.val(defCc);
			}
		}
		var $newRow = $clone;
		var $prevMain = $newRow.prevAll('tr.item-ordencompra-articulo').first();
		var prevFe = $prevMain.length ? ($prevMain.find('input[name="fechaentrega_articulos[]"]').val() || '') : '';
		if (!prevFe) {
			prevFe = $('#fecha').val() || '';
		}
		$newRow.find('input[name="fechaentrega_articulos[]"]').val(prevFe);
		ocActualizarCotizacionLinea($newRow);
		ocScheduleTotales();
		setTimeout(function () {
			$newRow.find('.codigoarticulo').trigger('focus');
		}, 0);
	});

	$(document).on('click', '.eliminar_ordencompra_articulo', function (event) {
		event.preventDefault();
		var $tbody = $('#tabla-articulos-ordencompra tbody');
		var $rows = $tbody.find('tr.item-ordencompra-articulo');
		var $tr = $(this).closest('tr.item-ordencompra-articulo');
		var $sub = $tr.next('tr.item-ordencompra-articulo-sub');
		if ($rows.length > 1) {
			$sub.remove();
			$tr.remove();
		} else {
			var $lastRow = $tr;
			$lastRow.find('input,select,textarea').each(function () {
				if ($(this).is('select')) {
					$(this).prop('selectedIndex', 0);
				} else if (!$(this).hasClass('ordencompra_articulo_id')) {
					$(this).val('');
				}
			});
			$lastRow.find('.oc-cotizacion-linea').val('1');
			$lastRow.find('.oc-requisicion-articulo-id').val('');
			$lastRow.find('.oc-precio-origen-tipo').val('');
			$lastRow.find('.oc-precio-origen-ref-id').val('');
			$lastRow.find('.oc-precio-origen-etiqueta').val('');
			$lastRow.find('.oc-entregas-semanal-json').val('[]');
			$lastRow.find('.oc-entregas-count').text('').addClass('d-none');
			$lastRow.find('.cantidad-linea').removeClass('oc-cant-desde-entregas');
			if ($('#oc-entrega-semanal-aplicar').length) {
				$lastRow.find('.cantidad-linea').prop('readonly', false).attr('title', '');
			}
			ocLimpiarCantidadAlternativaHint($lastRow);
			ocActualizarCotizacionLinea($lastRow);
			ocRefreshDetalleLineaBadge($lastRow);
			ocRefreshOrigenPrecioResumen($lastRow);
			ocRefreshEntregasSemanalUi($lastRow);
		}
		ocScheduleTotales();
	});

	$(document).on('input change', '#tabla-articulos-ordencompra .cantidad-linea, #tabla-articulos-ordencompra .precio-linea, #tabla-articulos-ordencompra .oc-cotizacion-linea, #tabla-articulos-ordencompra .oc-peso-unitario', function () {
		var $tr = $(this).closest('tr.item-ordencompra-articulo');
		if ($(this).hasClass('cantidad-linea')) {
			ocActualizarCantidadAlternativaHint($tr);
			ocActualizarPesoTotalHint($tr);
			if (window.ArticuloProveedorOperativo) {
				window.ArticuloProveedorOperativo.actualizarHintConversion($tr);
			}
		} else if ($(this).hasClass('oc-peso-unitario')) {
			ocActualizarPesoTotalHint($tr);
			return;
		}
		ocScheduleTotales();
	});

	$(document).on('change', '#tabla-articulos-ordencompra .codigoarticulo', function () {
		var sku = ($(this).val() || '').trim();
		if (!sku) {
			var $trClear = $(this).closest('tr.item-ordencompra-articulo');
			ocLimpiarCantidadAlternativaHint($trClear);
			ocLimpiarPesoLinea($trClear);
		}
	});

	$('#tabla-articulos-ordencompra tbody tr.item-ordencompra-articulo').each(function () {
		ocActualizarCantidadAlternativaHint($(this));
		ocActualizarPesoTotalHint($(this));
		ocRefreshDetalleLineaBadge($(this));
		ocRefreshOrigenPrecioResumen($(this));
		ocRefreshEntregasSemanalUi($(this));
	});
	$('#descuento_tipo').on('change', function () {
		ocActualizarAyudaDescuento();
		ocScheduleTotales();
	});
	ocActualizarAyudaDescuento();

	$('#fecha, #descuento').on('change input', function () {
		if ($(this).attr('id') === 'fecha') {
			ocRefrescarCotizacionesExtranjerasPorFecha();
		}
		ocScheduleTotales();
	});
	$(document).on('change', '#tabla-articulos-ordencompra .oc-moneda-linea', function () {
		ocActualizarCotizacionLinea($(this).closest('tr.item-ordencompra-articulo'));
	});
	ocScheduleTotales();
	$('#tabla-articulos-ordencompra tbody tr.item-ordencompra-articulo').each(function () {
		ocRefreshDetalleLineaBadge($(this));
		ocRefreshOrigenPrecioResumen($(this));
	});
	if (!$('#ordencompra_id_actual').val()) {
		$('#tabla-articulos-ordencompra tbody tr.item-ordencompra-articulo').each(function () {
			var mid = parseInt($(this).find('.oc-moneda-linea').val(), 10) || ocMonedaPesoId;
			if (mid !== ocMonedaPesoId) {
				ocActualizarCotizacionLinea($(this));
			}
		});
	}

	$('#form-ordencompra-general').on('submit', function (e) {
		if ($('#wizard-requisicion-multiples-meta').length && typeof window.reqOcWizardInterceptSubmit === 'function') {
			try {
				ocSyncComprobantesToHidden();
			} catch (errSync) {
				e.preventDefault();
				e.stopPropagation();
				alert('Error al preparar comprobantes. Revise la solapa Comprobantes a venir o recargue la página.');
				return false;
			}
			window.reqOcWizardInterceptSubmit(e, this);
			return false;
		}
		var form = this;
		try {
			ocSyncComprobantesToHidden();
		} catch (errSync) {
			e.preventDefault();
			e.stopPropagation();
			alert('Error al preparar comprobantes. Revise la solapa Comprobantes a venir o recargue la página.');
			return false;
		}
		if (ocNormCabeceraId($('#proveedor_id').val()) <= 0) {
			e.preventDefault();
			e.stopPropagation();
			alert('Debe indicar el proveedor de la orden de compra.');
			ocMostrarSolapaDelElemento(document.getElementById('codigoproveedor') || document.getElementById('proveedor_id'));
			$('#codigoproveedor').focus();
			return false;
		}
		try {
			ocLimpiarMarcasRequiredVacios(form);
			var vacios = ocRecopilarCamposRequiredVacios(form);
			if (vacios.length) {
				e.preventDefault();
				e.stopPropagation();
				for (var k = 0; k < vacios.length; k++) {
					var c = vacios[k];
					if (c.type === 'radio' && c.name) {
						var inputs = form.getElementsByTagName('input');
						for (var r = 0; r < inputs.length; r++) {
							if (inputs[r].type === 'radio' && inputs[r].name === c.name) {
								inputs[r].style.borderColor = 'red';
								inputs[r].classList.add('oc-required-vacio');
							}
						}
					} else {
						c.style.borderColor = 'red';
						c.classList.add('oc-required-vacio');
					}
				}
				ocMostrarSolapaDelElemento(vacios[0]);
				alert('Por favor, rellene todos los campos obligatorios.');
				setTimeout(function () {
					var primero = vacios[0];
					if (primero && typeof primero.focus === 'function') {
						primero.focus({ preventScroll: true });
					}
				}, 0);
				return false;
			}
			var reqIdOc = parseInt($('#requisicion_id').val(), 10);
			if (reqIdOc > 0) {
				var filaSinOrigen = null;
				$('#tabla-articulos-ordencompra tbody tr.item-ordencompra-articulo').each(function () {
					var $tr = $(this);
					var ra = parseInt($tr.find('.oc-requisicion-articulo-id').val(), 10);
					var aid = $tr.find('.articulo_id').val();
					var cant = parseFloat($tr.find('.cantidad-linea').val()) || 0;
					if (!ra || !aid || cant <= 0) {
						return;
					}
					var tipo = ($tr.find('.oc-precio-origen-tipo').val() || '').trim();
					if (!tipo) {
						filaSinOrigen = $tr[0];
						return false;
					}
				});
				if (filaSinOrigen) {
					e.preventDefault();
					e.stopPropagation();
					ocMostrarSolapaDelElemento(filaSinOrigen);
					alert('Con requisición asociada debe elegir el origen del precio en cada línea (botón «Origen precio» en la solapa Artículos).');
					return false;
				}
			}
		} catch (errVal) {
			e.preventDefault();
			e.stopPropagation();
			alert('Error al validar el formulario. Si el problema continúa, recargue la página.');
			return false;
		}
	});

	$('#form-ordencompra-general').on('input change', 'input, select, textarea', function () {
		var el = this;
		if (!el.classList.contains('oc-required-vacio')) {
			return;
		}
		var form = document.getElementById('form-ordencompra-general');
		if (!form) {
			return;
		}
		if (ocEsCampoRequiredVacio(el, form)) {
			return;
		}
		el.style.borderColor = '';
		el.classList.remove('oc-required-vacio');
	});

	$('#oc-boton-principal').on('click', function () {
		mostrarSolapa('#oc-solapa-principal');
		ocMarcarTabActivo('oc-boton-principal');
	});
	$('#oc-boton-articulos').on('click', function () {
		mostrarSolapa('#oc-solapa-articulos');
		ocMarcarTabActivo('oc-boton-articulos');
		var $primerSku = $('#tabla-articulos-ordencompra tbody tr.item-ordencompra-articulo')
			.first()
			.find('.codigoarticulo')
			.first();
		if ($primerSku.length && !$primerSku.prop('readonly') && !$primerSku.prop('disabled')) {
			setTimeout(function () {
				$primerSku.trigger('focus');
				if ($primerSku[0] && typeof $primerSku[0].select === 'function') {
					$primerSku[0].select();
				}
			}, 0);
		}
	});
	$('#oc-boton-comprobantes').on('click', function () {
		mostrarSolapa('#oc-solapa-comprobantes');
		ocMarcarTabActivo('oc-boton-comprobantes');
	});
	$('#oc-boton-archivos').on('click', function () {
		mostrarSolapa('#oc-solapa-archivos');
		ocMarcarTabActivo('oc-boton-archivos');
		var sol = document.getElementById('oc-solapa-archivos');
		if (sol) {
			sol.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	});
	$('#oc-boton-historia-legajo').on('click', function () {
		mostrarSolapa('#oc-solapa-historia-legajo');
		ocMarcarTabActivo('oc-boton-historia-legajo');
		var id = $('#ordencompra_id_actual').val();
		if (!id) return;
		$.get(carpetaBase + '/compras/ordencompra/' + id + '/historia-legajo').done(function (rows) {
			var $tb = $('#tabla-historia-legajo tbody').empty();
			(rows || []).forEach(function (r) {
				var sec = (r.sector_legajocompras && r.sector_legajocompras.nombre) ? r.sector_legajocompras.nombre : '';
				var usr = (r.usuarios && r.usuarios.nombre) ? r.usuarios.nombre : '';
				var f = r.fecha ? String(r.fecha).replace('T', ' ').substring(0, 19) : '';
				$tb.append('<tr><td>' + f + '</td><td>' + sec + '</td><td>' + (r.observacion || '') + '</td><td>' + (r.leyenda || '') + '</td><td>' + usr + '</td></tr>');
			});
		});
	});
	$('#oc-boton-historia-estados').on('click', function () {
		mostrarSolapa('#oc-solapa-historia-estados');
		ocMarcarTabActivo('oc-boton-historia-estados');
		var id = $('#ordencompra_id_actual').val();
		if (!id) return;
		$.get(carpetaBase + '/compras/ordencompra/' + id + '/historia-estados').done(function (rows) {
			var $tb = $('#tabla-historia-estados tbody').empty();
			(rows || []).forEach(function (r) {
				var f = r.fecha ? String(r.fecha).replace('T', ' ').substring(0, 19) : '';
				$tb.append('<tr><td>' + $('<div>').text(f).html() + '</td><td>' + $('<div>').text(r.estado || '').html() + '</td><td>' + $('<div>').text(r.observacion || '').html() + '</td></tr>');
			});
		});
	});

	function ocCargarHistoriaPrecios() {
		var id = $('#ordencompra_id_actual').val();
		if (!id) return;
		var $tb = $('#tabla-historia-precios-oc tbody').empty();
		$('#oc-historia-precios-vacio').addClass('d-none');
		$tb.append('<tr><td colspan="9" class="text-center text-muted">Cargando…</td></tr>');

		$.get(carpetaBase + '/compras/ordencompra/' + id + '/historia-precios').done(function (rows) {
			$tb.empty();
			if (!rows || !rows.length) {
				$('#oc-historia-precios-vacio').removeClass('d-none');
				return;
			}
			rows.forEach(function (r) {
				var f = r.fecha ? String(r.fecha).replace('T', ' ').substring(0, 19) : '';
				var recCell = ocEsc(r.recepcion_documento || '');
				if (r.recepcion_url) {
					recCell = '<a href="' + ocEsc(r.recepcion_url) + '" class="text-primary" target="_blank" rel="noopener">'
						+ ocEsc(r.recepcion_documento || ('#' + r.recepcion_id)) + '</a>';
				}
				$tb.append(
					'<tr>'
					+ '<td class="text-nowrap">' + ocEsc(f) + '</td>'
					+ '<td>' + ocEsc(r.sku) + '</td>'
					+ '<td>' + ocEsc(r.descripcion) + '</td>'
					+ '<td class="text-right">' + ocFmtNumero(r.precio_anterior) + '</td>'
					+ '<td class="text-right font-weight-bold">' + ocFmtNumero(r.precio_nuevo) + '</td>'
					+ '<td class="small">' + ocEsc(r.origen_etiqueta || r.origen) + '</td>'
					+ '<td class="small">' + recCell + '</td>'
					+ '<td>' + ocEsc(r.usuario) + '</td>'
					+ '<td class="small">' + ocEsc(r.comentario) + '</td>'
					+ '</tr>'
				);
			});
		}).fail(function () {
			$tb.empty().append('<tr><td colspan="9" class="text-danger">No se pudo cargar el historial de precios.</td></tr>');
		});
	}

	$('#oc-boton-historia-precios').on('click', function () {
		mostrarSolapa('#oc-solapa-historia-precios');
		ocMarcarTabActivo('oc-boton-historia-precios');
		ocCargarHistoriaPrecios();
	});

	function ocEsc(texto) {
		return $('<div>').text(texto == null ? '' : String(texto)).html();
	}

	function ocFmtNumero(valor) {
		if (valor == null || valor === '') return '';
		var n = parseFloat(valor);
		if (isNaN(n)) return ocEsc(valor);
		return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
	}

	function ocBadgesDiferencias(flags, resumen) {
		var badges = [];
		if (flags && flags.fl_precio_pendiente_aprobacion) badges.push('<span class="badge badge-info mr-1">Precio OC pend.</span>');
		if (flags && flags.fl_precio_diferencia) badges.push('<span class="badge badge-warning mr-1">Precio</span>');
		if (flags && flags.fl_diferencia_cantidad) badges.push('<span class="badge badge-info mr-1">Cantidad</span>');
		if (flags && flags.fl_articulo_extra) badges.push('<span class="badge badge-secondary mr-1">Extra</span>');
		if (flags && flags.fl_faltante_oc) badges.push('<span class="badge badge-danger mr-1">Faltante</span>');
		if (flags && flags.fl_laboratorio) badges.push('<span class="badge badge-primary mr-1">Lab.</span>');
		if (flags && flags.fl_linea_rechazada) badges.push('<span class="badge badge-dark mr-1">Rechazo</span>');
		if (!badges.length && resumen) {
			return '<span class="small text-muted">' + ocEsc(resumen) + '</span>';
		}
		return badges.join('');
	}

	function ocRenderLineasRecepcion(lineas) {
		if (!lineas || !lineas.length) {
			return '<p class="small text-muted mb-0">Sin líneas.</p>';
		}
		var html = '<table class="table table-sm table-bordered mb-0 bg-white"><thead class="thead-light"><tr>'
			+ '<th>SKU</th><th>Descripción</th><th class="text-right">Cant.</th>'
			+ '<th class="text-right">Precio OC (snap.)</th><th class="text-right">Precio OC actual</th>'
			+ '<th class="text-right">Precio recep.</th><th class="text-right">Precio solicitado</th><th>Obs. precio</th></tr></thead><tbody>';
		lineas.forEach(function (l) {
			var rowClass = l.fl_precio_diferencia ? 'table-warning' : '';
			html += '<tr class="' + rowClass + '">'
				+ '<td>' + ocEsc(l.sku) + '</td>'
				+ '<td>' + ocEsc(l.descripcion) + '</td>'
				+ '<td class="text-right">' + ocFmtNumero(l.cantidad) + '</td>'
				+ '<td class="text-right">' + ocFmtNumero(l.precio_ordencompra_snapshot) + '</td>'
				+ '<td class="text-right">' + ocFmtNumero(l.precio_oc_actual) + '</td>'
				+ '<td class="text-right font-weight-bold">' + ocFmtNumero(l.precio_recepcion) + '</td>'
				+ '<td class="text-right">' + ocFmtNumero(l.precio_solicitado) + '</td>'
				+ '<td class="small">' + ocEsc(l.comentario_precio) + '</td>'
				+ '</tr>';
		});
		html += '</tbody></table>';
		return html;
	}

	function ocCargarRecepciones() {
		var id = $('#ordencompra_id_actual').val();
		if (!id) return;
		var $tb = $('#tabla-recepciones-oc tbody').empty();
		$('#oc-recepciones-vacio').addClass('d-none');
		$('#oc-recepciones-resumen').addClass('d-none').empty();
		$tb.append('<tr><td colspan="9" class="text-center text-muted">Cargando…</td></tr>');

		$.get(carpetaBase + '/compras/ordencompra/' + id + '/recepciones').done(function (data) {
			$tb.empty();
			var recepciones = (data && data.recepciones) ? data.recepciones : [];
			var resumen = (data && data.resumen) ? data.resumen : {};

			if (!recepciones.length) {
				$('#oc-recepciones-vacio').removeClass('d-none');
				return;
			}

			var txtResumen = recepciones.length + ' documento(s)';
			if (resumen.con_precio_diferencia) {
				txtResumen += ' · ' + resumen.con_precio_diferencia + ' con diferencia de precio';
			}
			if (resumen.pendientes_aplicar_precio) {
				txtResumen += ' · ' + resumen.pendientes_aplicar_precio + ' con precios OC pendientes de aplicar';
			}
			if (resumen.precio_pendiente_aprobacion) {
				txtResumen += ' · ' + resumen.precio_pendiente_aprobacion + ' borrador(es) esperando aprobación de precio';
			}
			$('#oc-recepciones-resumen').removeClass('d-none').text(txtResumen);

			recepciones.forEach(function (r) {
				var detalleId = 'oc-rec-detalle-' + r.id;
				var acciones = '<div class="btn-group btn-group-sm flex-wrap" role="group">';
				if (r.urls && r.urls.editar) {
					var tituloEditar = r.es_devolucion ? 'Editar devolución' : 'Editar recepción';
					acciones += '<a href="' + ocEsc(r.urls.editar) + '" class="btn btn-outline-primary btn-xs" target="_blank" rel="noopener" title="' + ocEsc(tituloEditar) + '">'
						+ '<i class="fa fa-pencil"></i> Editar</a>';
				}
				if (r.urls && r.urls.pdf) {
					var tituloPdf = r.es_devolucion
						? 'Ver comprobante de devolución en PDF'
						: 'Ver comprobante de recepción (COM) en PDF';
					acciones += '<a href="' + ocEsc(r.urls.pdf) + '" class="btn btn-outline-danger btn-xs" target="_blank" rel="noopener" title="' + ocEsc(tituloPdf) + '">'
						+ '<i class="fa fa-file-pdf-o"></i> PDF</a>';
				}
				if (r.pendiente_aplicar_precio_oc) {
					acciones += '<button type="button" class="btn btn-warning btn-xs oc-aplicar-precios-rec" data-recepcion-id="' + r.id + '" title="Aplicar precios de recepción a la OC">'
						+ '<i class="fa fa-refresh"></i> Precios OC</button>';
				}
				if (r.puede_aplicar_precios_solicitados) {
					acciones += '<button type="button" class="btn btn-info btn-xs oc-aplicar-precios-solicitados-rec" data-recepcion-id="' + r.id + '" title="Aplicar precios solicitados del borrador a la OC y liberar confirmación">'
						+ '<i class="fa fa-check-circle"></i> Aprobar precios</button>';
				}
				acciones += '</div>';

				$tb.append(
					'<tr class="oc-rec-fila-cabecera" data-target="#' + detalleId + '">'
					+ '<td class="text-center align-middle">'
					+ '<button type="button" class="btn btn-link btn-sm p-0 oc-toggle-rec-detalle" aria-expanded="false" data-target="#' + detalleId + '" title="Ver detalle de líneas">'
					+ '<i class="fa fa-plus-square-o"></i></button></td>'
					+ '<td>' + ocEsc(r.documento) + (r.anita_ref ? ' <span class="small text-muted">(' + ocEsc(r.anita_ref) + ')</span>' : '') + '</td>'
					+ '<td>' + ocEsc(r.fecha) + '</td>'
					+ '<td>' + ocEsc(r.estado) + '</td>'
					+ '<td class="text-right">' + ocFmtNumero(r.total) + '</td>'
					+ '<td>' + ocEsc(r.moneda) + '</td>'
					+ '<td>' + ocEsc(r.usuario) + '</td>'
					+ '<td>' + ocBadgesDiferencias(r.flags, r.resumen_diferencias) + '</td>'
					+ '<td class="text-nowrap">' + acciones + '</td>'
					+ '</tr>'
					+ '<tr id="' + detalleId + '" class="oc-rec-detalle d-none"><td></td><td colspan="8">' + ocRenderLineasRecepcion(r.lineas) + '</td></tr>'
				);
			});
		}).fail(function () {
			$tb.empty().append('<tr><td colspan="9" class="text-danger">No se pudo cargar el listado de recepciones.</td></tr>');
		});
	}

	$('#oc-boton-recepciones').on('click', function () {
		mostrarSolapa('#oc-solapa-recepciones');
		ocMarcarTabActivo('oc-boton-recepciones');
		ocCargarRecepciones();
	});

	$(document).on('click', '.oc-toggle-rec-detalle', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $btn = $(this);
		var target = $btn.data('target');
		var $det = $(target);
		var abierto = !$det.hasClass('d-none');
		$det.toggleClass('d-none', abierto);
		$btn.attr('aria-expanded', abierto ? 'false' : 'true');
		$btn.find('i').toggleClass('fa-plus-square-o', abierto).toggleClass('fa-minus-square-o', !abierto);
	});

	$(document).on('click', '.oc-rec-fila-cabecera', function (e) {
		if ($(e.target).closest('a, button').length) return;
		$(this).find('.oc-toggle-rec-detalle').trigger('click');
	});

	$(document).on('click', '.oc-aplicar-precios-rec', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var recId = $(this).data('recepcion-id');
		var ocId = $('#ordencompra_id_actual').val();
		if (!ocId || !recId) return;
		if (!confirm('¿Actualizar en la OC (y Anita) los precios de las líneas con diferencia según esta recepción?')) return;
		var $btn = $(this).prop('disabled', true);
		$.ajax({
			url: carpetaBase + '/compras/ordencompra/' + ocId + '/aplicar-precios-recepcion/' + recId,
			method: 'POST',
			data: { _token: $('meta[name="csrf-token"]').attr('content') }
		}).done(function (resp) {
			alert((resp && resp.mensaje) ? resp.mensaje : 'Precios actualizados.');
			ocCargarRecepciones();
			if ($('#oc-solapa-historia-precios').is(':visible')) {
				ocCargarHistoriaPrecios();
			}
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) ? xhr.responseJSON.mensaje : 'Error al aplicar precios.';
			alert(msg);
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});

	$(document).on('click', '.oc-aplicar-precios-solicitados-rec', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var recId = $(this).data('recepcion-id');
		var ocId = $('#ordencompra_id_actual').val();
		if (!ocId || !recId) return;
		if (!confirm('¿Aplicar los precios solicitados del borrador a la OC, sincronizar Anita y avisar al usuario para confirmar la COM?')) return;
		var $btn = $(this).prop('disabled', true);
		$.ajax({
			url: carpetaBase + '/compras/ordencompra/' + ocId + '/aplicar-precios-solicitados-recepcion/' + recId,
			method: 'POST',
			data: { _token: $('meta[name="csrf-token"]').attr('content') }
		}).done(function (resp) {
			alert((resp && resp.mensaje) ? resp.mensaje : 'Precios aplicados y recepción liberada.');
			ocCargarRecepciones();
			if ($('#oc-solapa-historia-precios').is(':visible')) {
				ocCargarHistoriaPrecios();
			}
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.mensaje) ? xhr.responseJSON.mensaje : 'Error al aplicar precios solicitados.';
			alert(msg);
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});

	$('#oc-boton-arbol').on('click', function () {
		mostrarSolapa('#oc-solapa-arbol');
		ocMarcarTabActivo('oc-boton-arbol');
		var id = $('#ordencompra_id_actual').val();
		if (!id) return;
		$.get(carpetaBase + '/compras/ordencompra/' + id + '/movimiento-aprobacion').done(function (data) {
			var $tb = $('#tabla-movimientos-arbol tbody').empty();
			var movs = (data && data.movimientos) ? data.movimientos : [];
			movs.forEach(function (m) {
				$tb.append(
					'<tr><td>' + m.nivel + '</td><td>' + (m.estado || '') + '</td><td>'
					+ $('<div>').text(m.indicacion_estado_ordencompra || '').html()
					+ '</td><td>' + $('<div>').text(m.observacion || '').html() + '</td></tr>'
				);
			});
			if (data && data.aviso_grabacion_pendiente) {
				$('#oc-aviso-arbol').removeClass('d-none').text(data.aviso_grabacion_pendiente);
			} else {
				$('#oc-aviso-arbol').addClass('d-none').text('');
			}
			if (window.AnitaArbolPanelIa) {
				window.AnitaArbolPanelIa.render(data && data.ai_contexto_arbol ? data.ai_contexto_arbol : null, '#oc-panel-ia-arbol-solapa');
			}
		});
	});

	$('#oc-boton-ingresos').on('click', function () {
		mostrarSolapa('#oc-solapa-ingresos');
		ocMarcarTabActivo('oc-boton-ingresos');
	});

	ocMarcarTabActivo('oc-boton-principal');

	$('#oc-agrega-renglon-archivo').on('click', function (event) {
		event.preventDefault();
		var tpl = $('#oc-template-renglon-archivo').html();
		if (!tpl) {
			return;
		}
		$('#oc-tbody-tabla-archivo').append(tpl);
	});

	$(document).on('click', '#oc-tbody-tabla-archivo .oc-eliminararchivo', function (event) {
		event.preventDefault();
		$(this).closest('tr.item-archivo-oc').remove();
	});

	$(document).on('click', '.eliminar-archivo-ordencompra', function (event) {
		event.preventDefault();
		var $wrap = $(this).closest('.ordencompra-archivo-item');
		if ($wrap.length) {
			$wrap.remove();
		}
	});

	// Modal/código de proveedor dispara change.cpProveedorCargado (consulta.js);
	// plantilla/origen precio pueden disparar change genérico.
	$('#proveedor_id').on('change.ocordencompra change.cpProveedorCargado', function () {
		var id = $(this).val();
		if (!id) {
			return;
		}
		// Si consulta.js ya aplicó defaults con el JSON del proveedor, no hace falta
		// sobrescribir de inmediato; igual re-leemos por si el cambio vino sin ese payload.
		$.get(carpetaBase + '/compras/leerproveedor/' + id, function (data) {
			if (!data) {
				return;
			}
			if (data.condicioncompra_id) {
				$('#condicioncompra_id').val(String(data.condicioncompra_id));
			}
			if (data.condicionentrega_id) {
				$('#condicionentrega_id').val(String(data.condicionentrega_id));
			}
			if (data.condicionpago_id) {
				$('#condicionpago_id').val(String(data.condicionpago_id));
			}
			ocAplicarDefaultsComprobanteDesdeProveedor(data);
		});
	});

	/**
	 * Guarda la condición de pago y la forma de pago del ABM del proveedor e intenta precargar
	 * el primer comprobante a venir. Al cambiar de proveedor sin comprobantes cargados se rearma.
	 */
	function ocAplicarDefaultsComprobanteDesdeProveedor(data) {
		if (!data) {
			return;
		}
		ocProveedorCondicionpagoId = data.condicionpago_id ? parseInt(data.condicionpago_id, 10) : null;
		ocProveedorFormapagoId = null;
		var fps = data.proveedor_formapagos || [];
		for (var i = 0; i < fps.length; i++) {
			var fid = parseInt(fps[i] && fps[i].formapago_id, 10);
			if (fid > 0) {
				ocProveedorFormapagoId = fid;
				break;
			}
		}
		if (ocComprobantesState.length === 0) {
			ocAutocompPendiente = true;
			ocIntentarPrecargaComprobanteDefault();
		}
	}

	function ocAplicarPlantillaRequisicion(id) {
		$.get(carpetaBase + '/compras/ordencompra/plantilla-requisicion', { requisicion_id: id }).done(function (pl) {
			if (pl.empresa_id) {
				$('#empresa_id').val(pl.empresa_id);
			}
			if (pl.fecha) {
				$('input[name="fecha"]').val(pl.fecha);
			}
			if (pl.fechaentrega) {
				$('input[name="fechaentrega"]').val(pl.fechaentrega);
			}
			if (pl.centrocosto_id) {
				$('select[name="centrocosto_id"]').val(pl.centrocosto_id);
			}
			if (pl.comentario !== undefined) {
				$('input[name="comentario"]').val(pl.comentario);
			}
			if (pl.detalle !== undefined) {
				$('textarea[name="detalle"]').val(pl.detalle);
			}
			if (pl.tratamiento) {
				$('select[name="tratamiento"]').val(pl.tratamiento);
			}
			if (pl.proveedor_id) {
				$('#proveedor_id').val(pl.proveedor_id).trigger('change');
			}
			if (pl.proveedor_codigo !== undefined && pl.proveedor_codigo !== null) {
				$('#codigoproveedor').val(pl.proveedor_codigo);
			}
			if (pl.proveedor_nombre !== undefined && pl.proveedor_nombre !== null) {
				$('#nombreproveedor').val(pl.proveedor_nombre);
			}
			if (pl.articulos && pl.articulos.length) {
				var $tbody = $('#tabla-articulos-ordencompra tbody');
				var $sample = $tbody.find('tr.item-ordencompra-articulo').first();
				var $sampleSub = $sample.next('tr.item-ordencompra-articulo-sub');
				if (!$sample.length || !$sampleSub.length) {
					return;
				}
				var $base = $sample.clone();
				var $baseSub = $sampleSub.clone();
				$tbody.empty();
				pl.articulos.forEach(function (a) {
					var $row = $base.clone();
					var $sub = $baseSub.clone();
					$row.find('.ordencompra_articulo_id').val('');
					$row.find('.articulo_id').val(a.articulo_id);
					$row.find('.oc-requisicion-articulo-id').val(a.requisicion_articulo_id || '');
					$row.find('.oc-precio-origen-tipo').val('');
					$row.find('.oc-precio-origen-ref-id').val('');
					$row.find('.oc-precio-origen-etiqueta').val('');
					$row.find('.oc-entregas-semanal-json').val('[]');
					$row.find('.oc-entregas-count').text('').addClass('d-none');
					$row.find('.cantidad-linea').removeClass('oc-cant-desde-entregas');
					if ($('#oc-entrega-semanal-aplicar').length) {
						$row.find('.cantidad-linea').prop('readonly', false).attr('title', '');
					}
					$row.find('.codigoarticulo').val(a.sku || '');
					$row.find('.descripcionarticulo').val(a.descripcion_articulo || '');
					var colorId = a.color_id ? String(a.color_id) : '';
					var talleId = a.talle_id ? String(a.talle_id) : '';
					var manejaCt = !!(a.maneja_stock_color_talle || colorId || talleId);
					$row.attr('data-maneja-stock-color-talle', manejaCt ? '1' : '0');
					$row.find('select.ms-color-id').attr('data-selected', colorId).val(colorId);
					$row.find('select.ms-talle-id').attr('data-selected', talleId).val(talleId);
					$row.find('.cantidad-linea').val(a.cantidad);
					$row.find('.precio-linea').val(a.precio);
					$row.find('select[name="moneda_linea_ids[]"]').val(a.moneda_id);
					$row.find('select[name="centrocostodestino_ids[]"]').val(a.centrocostodestino_id);
					$row.find('input[name="fechaentrega_articulos[]"]').val(a.fechaentrega);
					$row.find('.oc-unidadesxenvase').val(a.unidadesxenvase > 0 ? a.unidadesxenvase : '');
					$row.find('.oc-um-alt-abrev').val(a.um_alternativa_abreviatura || '');
					$row.find('textarea[name="detalle_articulos[]"]').first().val(a.detalle || '');
					$row.find('.oc-cotizacion-linea').val(a.cotizacion || 1);
					$row.find('.partidagasto_id').val(a.partidagasto_id || '');
					$row.find('.codigopartidagasto').val(a.codigopartidagasto || '');
					$row.find('.descripcionpartidagasto').val(a.descripcionpartidagasto || '');
					$row.find('.capex_id').val(a.capex_id || '');
					$row.find('.codigocapex').val(a.codigocapex || '');
					$row.find('.descripcioncapex').val(a.descripcioncapex || '');
					if (ocMostrarPeso) {
						var pesoU = parseFloat(a.peso_unitario != null ? a.peso_unitario : a.peso) || 0;
						$row.find('.oc-peso-unitario').val(pesoU > 0 ? ocFormatearPeso(pesoU) : '');
						ocActualizarPesoTotalHint($row);
					}
					ocActualizarCantidadAlternativaHint($row);
					if (!(parseFloat($row.find('.oc-unidadesxenvase').val()) > 0)) {
						$row.find('.oc-cantidadalternativa').val(a.cantidadalternativa);
					}
					$tbody.append($row);
					$tbody.append($sub);
				});
				$tbody.find('tr.item-ordencompra-articulo').each(function () {
					ocActualizarCotizacionLinea($(this));
					ocRefreshDetalleLineaBadge($(this));
					ocRefreshOrigenPrecioResumen($(this));
					ocRefreshEntregasSemanalUi($(this));
					if (typeof window.msPoblarSelectsColorTalleFila === 'function') {
						window.msPoblarSelectsColorTalleFila($(this));
					}
				});
				if (typeof window.msRecalcularModoColorTalle === 'function') {
					window.msRecalcularModoColorTalle();
				}
				ocScheduleTotales();
			}
		});
	}

	window.ocAplicarPlantillaRequisicionDesdeId = ocAplicarPlantillaRequisicion;

	$('#btn-consulta-requisicion-modal').on('click', function () {
		$('#consultarequisicionModal').modal('show');
		$('#consultarequisicion').val('').trigger('focus');
		cargaTablaRequisiciones();
	});

	$('#consultarequisicionModal').on('shown.bs.modal', function () {
		$('#consultarequisicion').trigger('focus');
	});

	$('#aceptaconsultarequisicionModal').on('click', function () {
		$('#consultarequisicionModal').modal('hide');
	});

	function cargaTablaRequisiciones() {
		var q = $('#consultarequisicion').val() || '';
		$.get(carpetaBase + '/compras/ordencompra/requisiciones-aprobadas', { q: q }).done(function (rows) {
			var $b = $('#datosrequisicion').empty();
			(rows || []).forEach(function (r) {
				var $tr = $('<tr/>');
				$tr.append('<td class="requisicion_tabla_id">' + r.id + '</td>');
				$tr.append('<td class="requisicion_tabla_num">' + r.numerorequisicion + '</td>');
				$tr.append('<td>' + $('<div>').text(r.fecha || '').html() + '</td>');
				$tr.append('<td>' + $('<div>').text(r.proveedor || '').html() + '</td>');
				$tr.append('<td>' + $('<div>').text(r.centrocosto || '').html() + '</td>');
				$tr.append('<td><a href="#" class="btn btn-warning btn-sm eligeconsultarequisicion">Elegir</a></td>');
				$tr.append('<td><a href="#" class="btn btn-warning btn-sm consultarequisiciontabla">Consultar</a></td>');
				$b.append($tr);
			});
		});
	}

	$(document).on('click', '.eligeconsultarequisicion', function (e) {
		e.preventDefault();
		var $tr = $(this).closest('tr');
		var id = parseInt($tr.find('.requisicion_tabla_id').text(), 10);
		if (!id) {
			return;
		}
		var reqActual = parseInt($('#requisicion_id').val(), 10) || 0;
		var ocIdActual = parseInt($('#ordencompra_id_actual').val(), 10) || 0;
		// Edición de OC ya vinculada: no permitir cambiar de requisición (reabre la origen).
		if (ocIdActual > 0 && reqActual > 0 && id !== reqActual) {
			alert('No se puede cambiar la requisición de origen de una orden de compra ya vinculada. Genere una OC nueva desde la otra requisición.');
			$('#consultarequisicionModal').modal('hide');
			return;
		}
		$('#requisicion_id').val(id);
		var num = $tr.find('.requisicion_tabla_num').text();
		var prov = $tr.find('td').eq(3).text();
		$('#requisicion_display').val('#' + num + ' — ' + prov);
		$('#consultarequisicionModal').modal('hide');
		ocAplicarPlantillaRequisicion(id);
	});

	$(document).on('click', '.consultarequisiciontabla', function (e) {
		e.preventDefault();
		var id = parseInt($(this).closest('tr').find('.requisicion_tabla_id').text(), 10);
		if (!id) {
			return;
		}
		window.open(carpetaBase + '/compras/requisicion/' + id + '/editar', '_blank', 'noopener,noreferrer');
	});

	$(document).on('keyup', '#consultarequisicion', function () {
		clearTimeout(window._treqoc);
		window._treqoc = setTimeout(cargaTablaRequisiciones, 300);
	});

	function ocSincronizarBloqueContrato() {
		var esContrato = $('#es_contrato').is(':checked');
		$('#oc-contrato-campos').toggle(esContrato);

		var autoRenovable = esContrato && $('#contrato_auto_renovable').is(':checked');
		$('#contrato_dias_preaviso').prop('disabled', !autoRenovable);
		if (!autoRenovable) {
			$('#contrato_dias_preaviso').val('');
		}

		var requiereRecepcion = $('#contrato_requiere_recepcion_si').is(':checked');
		$('#oc-contrato-imputacion').toggle(esContrato && !requiereRecepcion);
		var imputacionManual = $('#contrato_imputacion_manual').is(':checked');
		$('#oc-contrato-cuenta-imputar').toggle(esContrato && !requiereRecepcion && imputacionManual);

		var requiereValidacion = esContrato && $('#contrato_requiere_validacion_abono').is(':checked');
		var exigeIngresos = esContrato && $('#contrato_exige_ingresos').is(':checked');
		$('#oc-contrato-plantilla-validacion').toggle(requiereValidacion || exigeIngresos);
		$('#oc-contrato-minimo-ingresos').toggle(exigeIngresos);
	}

	$(document).on('change', '#es_contrato, #contrato_auto_renovable, input[name="contrato_requiere_recepcion"], input[name="contrato_imputacion_contable"], #contrato_requiere_validacion_abono, #contrato_exige_ingresos', ocSincronizarBloqueContrato);
	ocSincronizarBloqueContrato();
});
