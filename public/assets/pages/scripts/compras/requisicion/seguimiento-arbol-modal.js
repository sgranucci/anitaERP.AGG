/**
 * Modal de movimientos del árbol de aprobación desde el tablero de seguimiento.
 * Usa GET /arbolaprobacion/leer_movimiento_aprobacion/RE/{id}
 */
(function ($) {
	'use strict';

	function carpeta() {
		return String(typeof carpetaBase !== 'undefined' ? carpetaBase : '').replace(/\/$/, '');
	}

	function esc(texto) {
		return $('<div>').text(texto == null ? '' : String(texto)).html();
	}

	function fechaArbolTexto(raw) {
		if (raw == null || raw === '') {
			return '';
		}
		var s = String(raw).replace('T', ' ').trim();
		var m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
		if (!m) {
			return s;
		}
		var out = m[3] + '/' + m[2] + '/' + m[1];
		if (m[4] !== undefined) {
			out += ' ' + m[4] + ':' + m[5];
		}
		return out;
	}

	function badgeEstado(estado) {
		var e = String(estado || '');
		var cls = 'badge-secondary';
		if (e === 'Pendiente') {
			cls = 'badge-warning';
		} else if (e === 'Aprobado') {
			cls = 'badge-success';
		} else if (e === 'Rechazado') {
			cls = 'badge-danger';
		} else if (e === 'Sin efecto') {
			cls = 'badge-light';
		}
		return '<span class="badge ' + cls + '">' + esc(e || '—') + '</span>';
	}

	function renderFilas(rows) {
		var $cuerpo = $('#requisicionArbolSeguimientoCuerpo');
		$cuerpo.empty();
		if (!rows.length) {
			$cuerpo.append(
				'<tr><td colspan="7" class="text-center text-muted">' +
				'Sin movimientos registrados en el árbol.</td></tr>'
			);
			return;
		}
		rows.forEach(function (value) {
			var obs = value.observacion || '';
			if (value.indicacion_estado_requisicion) {
				obs = obs + (obs ? ' — ' : '') + value.indicacion_estado_requisicion;
			}
			var envioNombre = (value.enviousuarios && value.enviousuarios.nombre) || '';
			var destNombre = (value.destinatariousuarios && value.destinatariousuarios.nombre) || '';
			var nivel = (value.nivel !== undefined && value.nivel !== null) ? value.nivel : '';
			var html = '<tr>';
			html += '<td><small>' + esc(fechaArbolTexto(value.fechaenvio)) + '</small></td>';
			html += '<td><small>' + esc(envioNombre) + '</small></td>';
			html += '<td class="text-center"><small>' + esc(nivel) + '</small></td>';
			html += '<td>' + badgeEstado(value.estado) + '</td>';
			html += '<td><small>' + esc(fechaArbolTexto(value.fechaproceso)) + '</small></td>';
			html += '<td><small>' + esc(destNombre) + '</small></td>';
			html += '<td><small title="' + esc(obs) + '">' + esc(obs) + '</small></td>';
			html += '</tr>';
			$cuerpo.append(html);
		});
	}

	$(function () {
		var $modal = $('#modalRequisicionArbolSeguimiento');
		var $titulo = $('#modalRequisicionArbolSeguimientoTitulo');
		var $aviso = $('#requisicionArbolSeguimientoAviso');
		var $cuerpo = $('#requisicionArbolSeguimientoCuerpo');

		$(document).on('click', '.js-requisicion-ver-arbol', function (e) {
			e.preventDefault();
			var id = $(this).data('id');
			var num = $(this).data('numero') || '';
			if (!id) {
				return;
			}
			$titulo.html('<i class="fa fa-sitemap"></i> Árbol de aprobación — RQ ' + esc(num));
			$aviso.addClass('d-none').empty();
			$cuerpo.html('<tr><td colspan="7" class="text-center text-muted">Cargando…</td></tr>');
			$modal.modal('show');

			$.ajax({
				url: carpeta() + '/arbolaprobacion/leer_movimiento_aprobacion/RE/' + id,
				method: 'GET',
				dataType: 'json',
				cache: false
			}).done(function (resp) {
				var rows = Array.isArray(resp) ? resp : (resp.movimientos || []);
				var aviso = (!Array.isArray(resp) && resp.aviso_grabacion_pendiente)
					? resp.aviso_grabacion_pendiente
					: null;
				if (aviso) {
					$aviso
						.removeClass('d-none alert-danger')
						.addClass('alert alert-warning mb-2')
						.text(aviso);
				} else {
					$aviso.addClass('d-none').empty();
				}
				renderFilas(rows);
			}).fail(function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: 'No se pudieron cargar los movimientos del árbol de aprobación.';
				$cuerpo.html(
					'<tr><td colspan="7" class="text-center text-danger">' + esc(msg) + '</td></tr>'
				);
			});
		});
	});
})(jQuery);
