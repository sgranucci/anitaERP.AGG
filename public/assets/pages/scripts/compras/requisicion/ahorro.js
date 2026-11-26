/**
 * KPI de ahorro en la requisición: precio anterior, origen, tipo y cálculo en pesos.
 *
 * - Los datos viven en hidden de cada fila (preciooriginales[], moneda_anterior_ids[], ...).
 * - El modal #modalReqAhorroLinea edita esos hidden; no tiene name propio.
 * - El cálculo lo hace el servidor (TC vigente de la fecha de la requisición): acá solo se muestra.
 * - Propone el precio de la última OC del artículo cuando se carga el artículo en la línea.
 */
(function ($) {
	'use strict';

	var SEL_TABLA = '#tabla-articulos-requisicion';
	var SEL_FILA = 'tr.item-requisicion-articulo';
	var cfg = window.requisicionAhorroConfig || null;
	if (!cfg || !$(SEL_TABLA).length) {
		return;
	}

	var $filaActual = null;
	var timerRecalc = null;
	var timerModal = null;
	var ultimaOcModal = null;

	function token() {
		return $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val() || '';
	}

	function fmtPesos(v) {
		return Number(v).toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
	}

	function fmtNum(v, dec) {
		return Number(v).toLocaleString('es-AR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
	}

	function num(v) {
		var n = parseFloat(String(v == null ? '' : v).replace(',', '.'));
		return Number.isNaN(n) ? 0 : n;
	}

	function $filas() {
		return $(SEL_TABLA).find('tbody ' + SEL_FILA);
	}

	function valorFila($fila, clase) {
		return $fila.find('.' + clase).val() || '';
	}

	function monedaLinea($fila) {
		return $fila.find('select[name="moneda_linea_ids[]"]').val() || '1';
	}

	function fechaDoc() {
		return ($('#fecha').val() || '').substring(0, 10);
	}

	function payloadFilas($lista) {
		var p = { _token: token(), fecha: fechaDoc(), precios: [], cantidades: [], moneda_linea_ids: [], preciooriginales: [], moneda_anterior_ids: [] };
		$lista.each(function () {
			var $f = $(this);
			p.precios.push(num($f.find('.precio-linea').val()));
			p.cantidades.push(num($f.find('.cantidad-linea').val()));
			p.moneda_linea_ids.push(monedaLinea($f));
			p.preciooriginales.push(valorFila($f, 'req-ahorro-precio-anterior'));
			p.moneda_anterior_ids.push(valorFila($f, 'req-ahorro-moneda-anterior'));
		});
		return p;
	}

	// ---------- resumen en la fila y en el panel ----------

	function pintarResumenFila($fila, calc) {
		var $r = $fila.find('.req-ahorro-resumen');
		if (!$r.length) {
			return;
		}
		if (!calc) {
			$r.empty();
			return;
		}
		if (calc.sin_cotizacion) {
			$r.html('<span class="text-warning" title="Falta la cotización de alguna de las monedas para comparar.">Sin cotizaci&oacute;n</span>');
			return;
		}
		if (calc.ahorro_total_pesos === null || calc.ahorro_total_pesos === undefined) {
			$r.html('<span class="' + (calc.ahorro_total < 0 ? 'text-danger' : 'text-success') + '">' +
				fmtNum(calc.ahorro_total, 2) + ' ' + (calc.moneda_abrev || '') + '</span>');
			return;
		}
		var neg = calc.ahorro_total_pesos < 0;
		var porc = calc.porc_ahorro === null ? '' : ' &middot; ' + fmtNum(calc.porc_ahorro, 1) + '%';
		$r.html('<span class="font-weight-bold ' + (neg ? 'text-danger' : 'text-success') + '" title="' +
			(neg ? 'Sobrecosto' : 'Ahorro') + ' en pesos">' + (neg ? '-' : '') + '$ ' + fmtPesos(Math.abs(calc.ahorro_total_pesos)) + porc + '</span>');
	}

	function pintarPanel(resumen) {
		var $panel = $('#req-panel-ahorro');
		if (!$panel.length || !resumen) {
			return;
		}
		var tiene = resumen.lineas_con_ahorro > 0;
		var neg = tiene && resumen.ahorro_pesos < 0;
		$('#req-ahorro-etiqueta').text(neg ? 'Sobrecosto' : 'Ahorro');
		$panel.toggleClass('border-danger', neg).toggleClass('border-success', !neg);
		var $v = $('#req-ahorro-valor').toggleClass('text-danger', neg).toggleClass('text-success', !neg);
		if (!tiene) {
			$v.html('&mdash;');
			return;
		}
		var txt = (neg ? '-' : '') + '$ ' + fmtPesos(Math.abs(resumen.ahorro_pesos));
		if (resumen.porcentaje !== null && resumen.porcentaje !== undefined) {
			txt += ' \u00b7 ' + fmtNum(resumen.porcentaje, 1) + '%';
		}
		$v.text(txt);
	}

	function recalcular() {
		var $lista = $filas();
		if (!$lista.length) {
			return;
		}
		$.post(cfg.urlCalcular, payloadFilas($lista)).done(function (res) {
			if (!res || !res.lineas) {
				return;
			}
			$lista.each(function (i) {
				pintarResumenFila($(this), res.lineas[i] || null);
			});
			pintarPanel(res.resumen);
		});
	}

	function programarRecalculo() {
		clearTimeout(timerRecalc);
		timerRecalc = setTimeout(recalcular, 350);
	}

	window.reqAhorroRecalcular = programarRecalculo;

	window.reqAhorroLimpiarFila = function ($fila) {
		$fila.find('.req-ahorro-resumen').empty();
		$fila.find('.req-ahorro-celda input').val('');
		$fila.find('.req-ahorro-tipo-id').attr('data-tipo-nombre', '');
	};

	// ---------- precio anterior automático (última OC del artículo) ----------

	function consultarUltimaOc($fila, cb) {
		var articuloId = $fila.find('.articulo_id').val();
		if (!articuloId) {
			cb(null);
			return;
		}
		$.get(cfg.urlPrecioAnterior, { articulo_id: articuloId, empresa_id: $('#empresa_id').val() || 0 })
			.done(function (res) {
				cb(res && res.encontrado ? res : null);
			})
			.fail(function () {
				cb(null);
			});
	}

	function aplicarUltimaOcEnFila($fila, oc) {
		$fila.find('.req-ahorro-precio-anterior').val(oc.precio);
		$fila.find('.req-ahorro-moneda-anterior').val(oc.moneda_id);
		$fila.find('.req-ahorro-origen').val(oc.origen || 'ultima_compra');
		$fila.find('.req-ahorro-referencia').val(oc.referencia || '');
	}

	$(document).on('req:articulo-linea-cargado.reqAhorro', SEL_FILA, function () {
		var $fila = $(this);
		var origenActual = valorFila($fila, 'req-ahorro-origen');
		var hayAnterior = num(valorFila($fila, 'req-ahorro-precio-anterior')) > 0;
		// Un precio anterior cargado a mano se respeta; el automático se refresca al cambiar de artículo.
		if (hayAnterior && origenActual !== '' && origenActual !== 'ultima_compra') {
			return;
		}
		if (!cfg.editable || cfg.puedeGuardar) {
			return;
		}
		consultarUltimaOc($fila, function (oc) {
			if (oc) {
				aplicarUltimaOcEnFila($fila, oc);
			} else {
				window.reqAhorroLimpiarFila($fila);
			}
			programarRecalculo();
		});
	});

	// Cambios que mueven el ahorro: precio nuevo, cantidad, moneda o fecha de la requisición.
	$(document).on('input.reqAhorro change.reqAhorro',
		SEL_TABLA + ' .precio-linea, ' + SEL_TABLA + ' .cantidad-linea, ' + SEL_TABLA + ' select[name="moneda_linea_ids[]"]',
		programarRecalculo);
	$(document).on('change.reqAhorro', '#fecha', programarRecalculo);

	// ---------- modal ----------

	function $m(id) {
		return $('#' + id);
	}

	function nombreTipo(id) {
		var nombre = '';
		$.each(cfg.tipos || [], function (_i, t) {
			if (String(t.id) === String(id)) {
				nombre = t.nombre;
			}
		});
		return nombre;
	}

	function pintarListaTipos() {
		var $lista = $m('req-ah-tipo-lista').empty();
		if (!(cfg.tipos || []).length) {
			$lista.append('<div class="list-group-item small text-muted">No hay tipos de ahorro activos. Se cargan en Compras &rarr; Tablas &rarr; Tipos de ahorro.</div>');
			return;
		}
		$.each(cfg.tipos, function (_i, t) {
			$('<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1"></div>')
				.append($('<span></span>').text(t.nombre))
				.append($('<button type="button" class="btn btn-outline-primary btn-sm req-ah-tipo-elegir"></button>').text('Elegir').attr('data-id', t.id))
				.appendTo($lista);
		});
	}

	function mostrarResultadoModal() {
		clearTimeout(timerModal);
		timerModal = setTimeout(function () {
			if (!$filaActual) {
				return;
			}
			var $f = $filaActual;
			var p = { _token: token(), fecha: fechaDoc(),
				precios: [num($f.find('.precio-linea').val())],
				cantidades: [num($f.find('.cantidad-linea').val())],
				moneda_linea_ids: [monedaLinea($f)],
				preciooriginales: [$m('req-ah-precio').val()],
				moneda_anterior_ids: [$m('req-ah-moneda').val()] };
			var $res = $m('req-ah-resultado');
			if (num(p.preciooriginales[0]) <= 0) {
				$res.hide();
				return;
			}
			$.post(cfg.urlCalcular, p).done(function (res) {
				var c = res && res.lineas ? res.lineas[0] : null;
				if (!c) {
					$res.html('Cargue precio, cantidad y precio anterior para calcular el ahorro.').show();
					return;
				}
				if (c.sin_cotizacion) {
					$res.html('<span class="text-warning">Falta la cotizaci&oacute;n de alguna moneda para comparar.</span>').show();
					return;
				}
				var neg = c.ahorro_total < 0;
				var html = '<b>' + (neg ? 'Sobrecosto' : 'Ahorro') + ':</b> ' +
					fmtNum(Math.abs(c.ahorro_unitario), 2) + ' ' + (c.moneda_abrev || '') + ' por unidad &middot; ' +
					fmtNum(Math.abs(c.ahorro_total), 2) + ' ' + (c.moneda_abrev || '') + ' en total';
				if (c.ahorro_total_pesos !== null) {
					html += ' &middot; $ ' + fmtPesos(Math.abs(c.ahorro_total_pesos));
				}
				if (c.porc_ahorro !== null) {
					html += ' &middot; ' + (neg ? '-' : '') + fmtNum(Math.abs(c.porc_ahorro), 1) + '%';
				}
				$res.html(html).toggleClass('text-danger', neg).toggleClass('text-success', !neg).show();
			});
		}, 300);
	}

	function abrirModal($fila) {
		$filaActual = $fila;
		var desc = ($fila.find('.codigoarticulo').val() || '') + ' ' + ($fila.find('.descripcionarticulo').val() || '');
		$m('req-ah-articulo').text(desc.trim());
		$m('req-ah-precio').val(valorFila($fila, 'req-ahorro-precio-anterior'));
		$m('req-ah-moneda').val(valorFila($fila, 'req-ahorro-moneda-anterior') || monedaLinea($fila));
		$m('req-ah-origen').val(valorFila($fila, 'req-ahorro-origen'));
		$m('req-ah-referencia').val(valorFila($fila, 'req-ahorro-referencia'));
		var tipoId = valorFila($fila, 'req-ahorro-tipo-id');
		$m('req-ah-tipo-id').val(tipoId);
		$m('req-ah-tipo-nombre').val(tipoId ? (nombreTipo(tipoId) || $fila.find('.req-ahorro-tipo-id').attr('data-tipo-nombre') || '') : '');
		$m('req-ah-observacion').val(valorFila($fila, 'req-ahorro-observacion'));
		$m('req-ah-tipo-lista').addClass('d-none');
		$m('req-ah-resultado').hide();
		$m('req-ah-ultima-oc').text('Consultando…');
		$m('req-ah-usar-ultima').addClass('d-none');
		ultimaOcModal = null;
		$('#modalReqAhorroLinea').modal('show');

		consultarUltimaOc($fila, function (oc) {
			ultimaOcModal = oc;
			if (!oc) {
				$m('req-ah-ultima-oc').text('Sin compras anteriores');
				return;
			}
			$m('req-ah-ultima-oc').text(oc.referencia + ' \u00b7 ' + (oc.moneda_abrev || '') + ' ' + fmtNum(oc.precio, 2) +
				(oc.misma_empresa ? '' : ' (otra empresa)'));
			if (cfg.editable) {
				$m('req-ah-usar-ultima').removeClass('d-none');
			}
		});
		mostrarResultadoModal();
	}

	$(document).on('click', '.req-abrir-ahorro-linea', function () {
		abrirModal($(this).closest(SEL_FILA));
	});

	$(document).on('click', '#req-ah-usar-ultima', function () {
		if (!ultimaOcModal) {
			return;
		}
		$m('req-ah-precio').val(ultimaOcModal.precio);
		$m('req-ah-moneda').val(ultimaOcModal.moneda_id);
		$m('req-ah-origen').val(ultimaOcModal.origen || 'ultima_compra');
		$m('req-ah-referencia').val(ultimaOcModal.referencia || '');
		mostrarResultadoModal();
	});

	$(document).on('input change', '#req-ah-precio, #req-ah-moneda', mostrarResultadoModal);

	$(document).on('click', '#req-ah-tipo-lupa', function () {
		pintarListaTipos();
		$m('req-ah-tipo-lista').toggleClass('d-none');
	});

	$(document).on('click', '.req-ah-tipo-elegir', function () {
		var id = $(this).attr('data-id');
		$m('req-ah-tipo-id').val(id);
		$m('req-ah-tipo-nombre').val(nombreTipo(id));
		$m('req-ah-tipo-lista').addClass('d-none');
	});

	$(document).on('click', '#req-ah-tipo-quitar', function () {
		$m('req-ah-tipo-id').val('');
		$m('req-ah-tipo-nombre').val('');
	});

	// Enter no envía la requisición: salta al campo siguiente del modal.
	$(document).on('keydown', '#modalReqAhorroLinea input, #modalReqAhorroLinea select', function (e) {
		if (e.key !== 'Enter') {
			return;
		}
		e.preventDefault();
		var $campos = $('#modalReqAhorroLinea').find('input:not([type=hidden]):not([readonly]):not(:disabled), select:not(:disabled), textarea:not([readonly])');
		var i = $campos.index(this);
		var $sig = $campos.eq(i + 1);
		if ($sig.length) {
			$sig.trigger('focus');
		} else {
			$('#req-ah-aplicar').trigger('focus');
		}
	});

	$(document).on('click', '#req-ah-aplicar', function () {
		if (!$filaActual) {
			return;
		}
		var $f = $filaActual;
		var precio = num($m('req-ah-precio').val());
		$f.find('.req-ahorro-precio-anterior').val(precio > 0 ? $m('req-ah-precio').val() : '');
		$f.find('.req-ahorro-moneda-anterior').val(precio > 0 ? $m('req-ah-moneda').val() : '');
		$f.find('.req-ahorro-origen').val($m('req-ah-origen').val());
		$f.find('.req-ahorro-referencia').val($m('req-ah-referencia').val());
		var tipoId = $m('req-ah-tipo-id').val();
		$f.find('.req-ahorro-tipo-id').val(tipoId).attr('data-tipo-nombre', tipoId ? $m('req-ah-tipo-nombre').val() : '');
		$f.find('.req-ahorro-observacion').val($m('req-ah-observacion').val());
		$('#modalReqAhorroLinea').modal('hide');
		recalcular();
	});

	// Carga posterior (requisición aprobada, con OC, cumplida): graba solo el ahorro de la línea, sin circuito.
	$(document).on('click', '#req-ah-guardar', function () {
		if (!$filaActual || !cfg.puedeGuardar) {
			return;
		}
		var $f = $filaActual;
		var $btn = $(this).prop('disabled', true);
		var token = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').first().val();
		$.ajax({
			url: cfg.urlGuardar,
			method: 'POST',
			dataType: 'json',
			headers: { 'X-CSRF-TOKEN': token },
			data: {
				_token: token,
				requisicion_articulo_id: $f.find('.requisicion_articulo_id').val(),
				precio_anterior: $m('req-ah-precio').val(),
				moneda_anterior_id: $m('req-ah-moneda').val(),
				origen_anterior: $m('req-ah-origen').val(),
				referencia_anterior: $m('req-ah-referencia').val(),
				tipoahorro_id: $m('req-ah-tipo-id').val(),
				observacion: $m('req-ah-observacion').val()
			}
		}).done(function (resp) {
			if (!resp || resp.mensaje !== 'ok') {
				mostrarAvisoGuardar('text-danger', (resp && resp.detalle) || 'No se pudo guardar el ahorro.');
				return;
			}
			var l = resp.linea;
			$f.find('.req-ahorro-precio-anterior').val(l.preciooriginal);
			$f.find('.req-ahorro-moneda-anterior').val(l.moneda_anterior_id || monedaLinea($f));
			$f.find('.req-ahorro-origen').val(l.origen_anterior || '');
			$f.find('.req-ahorro-referencia').val(l.referencia_anterior || '');
			$f.find('.req-ahorro-tipo-id').val(l.tipoahorro_id || '').attr('data-tipo-nombre', l.tipoahorro_nombre || '');
			$f.find('.req-ahorro-observacion').val($m('req-ah-observacion').val());
			recalcular();
			var msg = 'Ahorro guardado.';
			if (resp.ordenes_actualizadas > 0) {
				msg += ' Se copi\u00f3 tambi\u00e9n a ' + resp.ordenes_actualizadas + ' l\u00ednea(s) de orden de compra.';
			}
			mostrarAvisoGuardar('text-success', msg);
		}).fail(function (xhr) {
			var j = xhr.responseJSON || {};
			var msg = j.detalle || (j.errors && Object.values(j.errors)[0] && Object.values(j.errors)[0][0]) || 'Error al guardar el ahorro.';
			mostrarAvisoGuardar('text-danger', msg);
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});

	function mostrarAvisoGuardar(clase, texto) {
		$m('req-ah-resultado').removeClass('text-danger text-success').addClass(clase).text(texto).show();
	}

	// Estado inicial: si alguna línea ya trae precio anterior, mostrar el ahorro calculado.
	$(function () {
		var hay = false;
		$filas().each(function () {
			if (num(valorFila($(this), 'req-ahorro-precio-anterior')) > 0) {
				hay = true;
			}
		});
		if (hay) {
			recalcular();
		}
	});
})(jQuery);
