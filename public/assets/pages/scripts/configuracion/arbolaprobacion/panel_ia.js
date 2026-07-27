/**
 * Render del panel "Ayuda para firmar (IA)" en solapas de árbol.
 */
(function (window) {
	'use strict';

	function escapeHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function fmtNum(n) {
		var x = Number(n);
		if (!isFinite(x)) {
			return '0,00';
		}
		return x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function renderPanelIaArbol(panel, targetSelector) {
		var $box = window.jQuery ? window.jQuery(targetSelector) : null;
		if (!$box || !$box.length) {
			return;
		}
		if (!panel) {
			$box.addClass('d-none').empty();
			return;
		}
		var parrafos = Array.isArray(panel.ai_parrafos) ? panel.ai_parrafos : [];
		var advs = Array.isArray(panel.ai_advertencias) ? panel.ai_advertencias : [];
		var ctx = panel.contexto || null;
		if (!parrafos.length && !advs.length && !ctx) {
			$box.addClass('d-none').empty();
			return;
		}
		var score = panel.ai_score != null ? Number(panel.ai_score) : null;
		var html = '<div class="card card-outline card-info mb-0">';
		html += '<div class="card-header py-2"><h3 class="card-title mb-0 h6"><i class="fa fa-magic"></i> Ayuda para firmar (IA)';
		if (score != null && isFinite(score)) {
			html += ' <span class="badge badge-secondary ml-1">score ' + fmtNum(score) + '</span>';
		}
		html += '</h3></div><div class="card-body py-2">';
		html += '<p class="small text-muted mb-2">Solo lectura: no aprueba ni cambia el árbol.</p>';
		if (advs.length) {
			html += '<div class="alert alert-warning py-2 mb-2">';
			advs.forEach(function (a) { html += '<div>' + escapeHtml(a) + '</div>'; });
			html += '</div>';
		}
		if (parrafos.length) {
			html += '<ul class="mb-2 pl-3">';
			parrafos.forEach(function (p) { html += '<li>' + escapeHtml(p) + '</li>'; });
			html += '</ul>';
		}
		var excesos = ctx && Array.isArray(ctx.capex_excesos) ? ctx.capex_excesos : [];
		if (excesos.length) {
			html += '<div class="table-responsive"><table class="table table-sm table-striped mb-0">';
			html += '<thead style="background:#85C1E9;color:#17202A"><tr>';
			html += '<th>CAPEX</th><th>Período</th><th class="text-right">Asignado</th>';
			html += '<th class="text-right">Comprometido</th><th class="text-right">Esta línea</th><th class="text-right">Excedente</th>';
			html += '</tr></thead><tbody>';
			excesos.forEach(function (ex) {
				html += '<tr><td>' + escapeHtml(ex.capex_nombre || ('#' + ex.capex_id)) + '</td>';
				html += '<td>' + escapeHtml(ex.periodo || '') + '</td>';
				html += '<td class="text-right">' + fmtNum(ex.asignado) + '</td>';
				html += '<td class="text-right">' + fmtNum(ex.comprometido) + '</td>';
				html += '<td class="text-right">' + fmtNum(ex.monto_linea) + '</td>';
				html += '<td class="text-right"><strong>' + fmtNum(ex.excedente) + '</strong></td></tr>';
			});
			html += '</tbody></table></div>';
		}
		html += '</div></div>';
		$box.removeClass('d-none').html(html);
	}

	window.AnitaArbolPanelIa = {
		render: renderPanelIaArbol
	};
})(window);
