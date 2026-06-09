var filaDescuentoComprobanteModal = null;

function initDescuentoComprobanteCobranza() {
	if ($('#tbody-comprobante-table').length === 0) {
		return;
	}

	$(document).on('click', '.btn-descuento-comprobante', function (event) {
		event.preventDefault();
		abrirModalDescuentoComprobante($(this).closest('tr.item-comprobante'));
	});

	$('#descuento_modal_tipo, #descuento_modal_valor').on('input change', actualizarPreviewDescuentoModal);
	$('#descuento_modal_aplicar').on('click', aplicarDescuentoDesdeModal);
	$('#descuento_modal_quitar').on('click', quitarDescuentoDesdeModal);
}

function abrirModalDescuentoComprobante($fila) {
	filaDescuentoComprobanteModal = $fila;
	const codigo = $fila.find('.codigocomprobante').val();
	const saldo = parseFloat($fila.find('.saldocomprobante').val()) || 0;
	const base = parseFloat($fila.find('.montoaplicadocomprobante').val()) || saldo;

	$('#descuento-modal-codigo').text(codigo);
	$('#descuento-modal-saldo').text(base.toFixed(2));

	const tipo = $fila.find('.descuento_tipo').val() || 'porcentaje';
	const valor = $fila.find('.descuento_valor').val() || '';
	const leyenda = $fila.find('.descuento_leyenda').val() || '';

	$('#descuento_modal_tipo').val(tipo);
	$('#descuento_modal_valor').val(valor);
	$('#descuento_modal_leyenda').val(leyenda);

	if (parseFloat($fila.find('.descuento_importe').val()) > 0) {
		$('#descuento_modal_quitar').show();
	} else {
		$('#descuento_modal_quitar').hide();
	}

	actualizarPreviewDescuentoModal();
	$('#modalDescuentoComprobante').modal('show');
}

function baseDescuentoComprobante($fila) {
	const aplicado = parseFloat($fila.find('.montoaplicadocomprobante').val());
	if (!Number.isNaN(aplicado) && aplicado > 0) {
		return aplicado;
	}
	const saldo = parseFloat($fila.find('.saldocomprobante').val());
	return Number.isNaN(saldo) ? 0 : saldo;
}

function calcularImporteDescuento(tipo, valor, base) {
	const v = parseFloat(valor);
	if (Number.isNaN(v) || v <= 0 || base <= 0) {
		return 0;
	}
	let importe = 0;
	if (tipo === 'porcentaje') {
		importe = base * (v / 100.);
	} else {
		importe = v;
	}
	if (importe > base) {
		importe = base;
	}

	return Math.round(importe * 100) / 100;
}

function actualizarPreviewDescuentoModal() {
	if (!filaDescuentoComprobanteModal) {
		return;
	}
	const tipo = $('#descuento_modal_tipo').val();
	const valor = $('#descuento_modal_valor').val();
	const base = baseDescuentoComprobante(filaDescuentoComprobanteModal);
	const importe = calcularImporteDescuento(tipo, valor, base);
	$('#descuento-modal-preview').text(importe.toFixed(2));
}

function limpiarDescuentoFila($fila) {
	$fila.removeClass('tiene-descuento-cobranza');
	$fila.find('.descuento_tipo').val('');
	$fila.find('.descuento_valor').val('');
	$fila.find('.descuento_importe').val('');
	$fila.find('.descuento_venta_origen_id').val('');
	$fila.find('.descuento_cc_origen_id').val('');
	$fila.find('.descuento_leyenda').val('');
	$fila.find('.btn-descuento-comprobante i').removeClass('text-success').addClass('text-warning');
}

function aplicarDescuentoEnFila($fila, tipo, valor, importe, leyenda) {
	$fila.addClass('tiene-descuento-cobranza');
	$fila.find('.descuento_tipo').val(tipo);
	$fila.find('.descuento_valor').val(valor);
	$fila.find('.descuento_importe').val(importe.toFixed(2));
	$fila.find('.descuento_venta_origen_id').val($fila.find('.idventa').val());
	$fila.find('.descuento_cc_origen_id').val($fila.find('.idcuentacorriente').val());
	$fila.find('.descuento_leyenda').val(leyenda);
	$fila.find('.btn-descuento-comprobante i').removeClass('text-warning').addClass('text-success');
}

function aplicarDescuentoDesdeModal() {
	if (!filaDescuentoComprobanteModal) {
		return;
	}
	const tipo = $('#descuento_modal_tipo').val();
	const valor = $('#descuento_modal_valor').val();
	const leyenda = $('#descuento_modal_leyenda').val();
	const base = baseDescuentoComprobante(filaDescuentoComprobanteModal);
	const importe = calcularImporteDescuento(tipo, valor, base);

	if (importe <= 0) {
		alert('Indique un descuento mayor a cero.');
		return;
	}

	aplicarDescuentoEnFila(filaDescuentoComprobanteModal, tipo, valor, importe, leyenda);
	$('#modalDescuentoComprobante').modal('hide');
	sincronizaFilasNcPendientes();
	sumaMontoComprobante();
}

function quitarDescuentoDesdeModal() {
	if (!filaDescuentoComprobanteModal) {
		return;
	}
	limpiarDescuentoFila(filaDescuentoComprobanteModal);
	$('#modalDescuentoComprobante').modal('hide');
	sincronizaFilasNcPendientes();
	sumaMontoComprobante();
}

function sincronizaFilasNcPendientes() {
	$('#tbody-nc-pendiente-table').empty();
	const template = $('#template-renglon-nc-pendiente').html();
	if (!template) {
		return;
	}

	$('#tbody-comprobante-table tr.item-comprobante').each(function () {
		const importe = parseFloat($(this).find('.descuento_importe').val());
		if (Number.isNaN(importe) || importe <= 0) {
			return;
		}
		const codigo = $(this).find('.codigocomprobante').val();
		const monedaId = $(this).find('.monedacomprobante').val();
		const monedaTxt = descripcionMoneda[monedaId] || monedaId;

		$('#tbody-nc-pendiente-table').append(template);
		const $row = $('#tbody-nc-pendiente-table tr').last();
		$row.attr('data-venta-origen-id', $(this).find('.idventa').val());
		$row.find('.nc-pendiente-referencia').text(' por ' + codigo);
		$row.find('.nc-pendiente-moneda-abrev').text(monedaTxt);
		$row.find('.nc-pendiente-importe').text('-' + importe.toFixed(2));
	});
}

function totalDescuentosPorMoneda() {
	const totales = {};
	idMoneda.forEach(function (moneda) {
		totales[moneda] = 0;
	});

	$('#tbody-comprobante-table tr.item-comprobante').each(function () {
		const importe = parseFloat($(this).find('.descuento_importe').val());
		if (Number.isNaN(importe) || importe <= 0) {
			return;
		}
		const moneda = $(this).find('.monedacomprobante').val();
		if (totales[moneda] !== undefined) {
			totales[moneda] += importe;
		}
	});

	return totales;
}

function sumaTotalDescuentosPantalla() {
	const wrapper = $('.totales-descuentos-cobranza');
	const descuentos = totalDescuentosPorMoneda();
	let hayDescuento = false;

	$(wrapper).empty();
	idMoneda.forEach(function (moneda) {
		if (descuentos[moneda] !== undefined && descuentos[moneda] > 0) {
			hayDescuento = true;
			const detalleLabel = 'Total descuentos (NC) ' + descripcionMoneda[moneda];
			$(wrapper).append('<label class="col-lg-3 col-form-label text-warning">' + detalleLabel + '</label>');
			$(wrapper).append('<input type="text" class="form-control col-lg-1" readonly value="' + descuentos[moneda].toFixed(2) + '" />');
		}
	});

	if (!hayDescuento) {
		return 0;
	}

	let monedaDefault = $('#tbody-comprobante-table').children(':first').find('.monedacomprobante').val();
	let totalArs = 0;
	idMoneda.forEach(function (moneda) {
		const cotRow = $('#tbody-comprobante-table tr.item-comprobante').find('.monedacomprobante[value="' + moneda + '"]').first().parents('tr').find('.cotizacioncomprobante').val();
		const cot = parseFloat(cotRow) || 1;
		const coef = calculaCoeficienteMoneda(monedaDefault, moneda, cot);
		totalArs += (descuentos[moneda] || 0) * coef;
	});

	return totalArs;
}

function restaurarDescuentosDesdeServidor(descuentosJson) {
	if (!descuentosJson || !descuentosJson.length) {
		return;
	}
	descuentosJson.forEach(function (d) {
		const $fila = $('#tbody-comprobante-table tr.item-comprobante').filter(function () {
			return $(this).find('.idventa').val() == d.venta_origen_id;
		}).first();
		if ($fila.length === 0) {
			return;
		}
		aplicarDescuentoEnFila($fila, d.tipo, d.valor, parseFloat(d.importe_calculado), d.leyenda || '');
	});
	sincronizaFilasNcPendientes();
	sumaMontoComprobante();
}

$(function () {
	initDescuentoComprobanteCobranza();
});
