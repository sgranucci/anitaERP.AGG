/**
 * Precarga en background de puntos de venta ARCA al abrir el listado.
 */
(function () {
	'use strict';

	var cfg = window.PUNTOVENTA_ARCA_PRELOAD;
	if (!cfg || !cfg.url || !cfg.empresas || !cfg.empresas.length) {
		return;
	}

	var STORAGE_KEY = 'arca_ptos_venta_v1';

	function cacheKey(empresaId, webservice, modofacturacion) {
		return String(empresaId) + ':' + (webservice || 'auto') + ':' + (modofacturacion || 'all');
	}

	function readCache() {
		try {
			var raw = sessionStorage.getItem(STORAGE_KEY);
			return raw ? JSON.parse(raw) : {};
		} catch (e) {
			return {};
		}
	}

	function writeCacheEntry(key, payload) {
		try {
			var store = readCache();
			store[key] = payload;
			sessionStorage.setItem(STORAGE_KEY, JSON.stringify(store));
		} catch (e) {
			/* sessionStorage lleno o no disponible */
		}
	}

	function precargarEmpresa(empresaId) {
		var params = new URLSearchParams({
			empresa_id: String(empresaId),
			refresh: '0',
		});

		return fetch(cfg.url + '?' + params.toString(), {
			headers: {
				Accept: 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
			},
			credentials: 'same-origin',
		})
			.then(function (res) {
				return res.json().then(function (body) {
					return { ok: res.ok, body: body };
				});
			})
			.then(function (result) {
				if (!result.ok || !result.body.ok) {
					return;
				}
				var body = result.body;
				var key = cacheKey(body.empresa_id, body.webservice, 'all');
				writeCacheEntry(key, {
					empresa_id: body.empresa_id,
					webservice: body.webservice,
					modofacturacion: 'all',
					puntos: body.puntos || [],
					origen: body.origen,
					ts: Date.now(),
				});
			})
			.catch(function () {
				/* precarga silenciosa */
			});
	}

	cfg.empresas.forEach(function (emp) {
		void precargarEmpresa(emp.id);
	});
})();
