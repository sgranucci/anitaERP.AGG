(function () {
    var root = document.getElementById('articulo-waitry-root');
    var boton = document.getElementById('btn-articulo-waitry');
    var wrap = document.getElementById('wrap-articulo-waitry');
    var sku = document.getElementById('sku');
    if (!root || !boton || !sku) {
        return;
    }

    var patron = new RegExp(root.getAttribute('data-patron') || '^$');

    function skuVisible() {
        var valor = String(sku.value || '').trim().toUpperCase();
        var visible = patron.test(valor);
        if (wrap) {
            wrap.style.display = visible ? '' : 'none';
        }
        boton.style.display = visible ? '' : 'none';
    }

    sku.addEventListener('input', skuVisible);
    skuVisible();

    function marca(idCheckbox, idHidden) {
        var checkbox = document.getElementById(idCheckbox);
        var hidden = document.getElementById(idHidden);
        if (!checkbox || !hidden) {
            return;
        }
        checkbox.checked = hidden.value === '1';
        hidden.value = checkbox.checked ? '1' : '0';
        checkbox.addEventListener('change', function () {
            hidden.value = checkbox.checked ? '1' : '0';
        });
    }

    marca('articulo-waitry-dispara-comanda', 'waitry_dispara_comanda');
    marca('articulo-waitry-va-cocina', 'waitry_va_cocina');

    boton.addEventListener('click', function () {
        if (window.jQuery) {
            window.jQuery('#modal-articulo-waitry').modal('show');
        }
    });

    function token() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        var input = document.querySelector('#form-general input[name="_token"]');
        return input ? input.value : '';
    }

    function cuerpo() {
        return JSON.stringify({
            waitry_dispara_comanda: document.getElementById('articulo-waitry-dispara-comanda').checked ? 1 : 0,
            waitry_va_cocina: document.getElementById('articulo-waitry-va-cocina').checked ? 1 : 0
        });
    }

    function pintar(panel) {
        if (!panel || !panel.salas) {
            return;
        }
        var tbody = document.getElementById('articulo-waitry-salas');
        if (!tbody) {
            return;
        }
        panel.salas.forEach(function (sala) {
            var fila = tbody.querySelector('tr[data-empresa-id="' + sala.empresa_id + '"]');
            if (!fila) {
                return;
            }
            var celda = fila.querySelector('.articulo-waitry-estado');
            if (celda) {
                celda.textContent = sala.estado || '';
            }
        });
        var dispara = document.getElementById('articulo-waitry-dispara-comanda');
        var cocina = document.getElementById('articulo-waitry-va-cocina');
        if (panel.marcas && dispara && cocina) {
            dispara.checked = !!panel.marcas.dispara_comanda;
            cocina.checked = !!panel.marcas.va_cocina;
            document.getElementById('waitry_dispara_comanda').value = dispara.checked ? '1' : '0';
            document.getElementById('waitry_va_cocina').value = cocina.checked ? '1' : '0';
        }
    }

    function aviso(texto, ok) {
        var box = document.getElementById('articulo-waitry-resultado');
        if (!box) {
            return;
        }
        box.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
        box.classList.add(ok ? 'alert-success' : 'alert-danger');
        box.textContent = texto || '';
    }

    var enviando = false;

    function post(url, confirmar) {
        if (!url || enviando) {
            return;
        }
        if (confirmar && !window.confirm('Inactiva el producto en las salas donde Anita lo dio de alta. No borra el artículo.')) {
            return;
        }
        enviando = true;
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token()
            },
            body: cuerpo()
        }).then(function (response) {
            return response.json().then(function (data) {
                return { okHttp: response.ok, data: data };
            });
        }).then(function (resultado) {
            var data = resultado.data || {};
            if (data.panel) {
                pintar(data.panel);
            }
            aviso(data.mensaje || (resultado.okHttp ? 'Listo.' : 'No se pudo completar.'), !!data.ok);
        }).catch(function () {
            aviso('No se pudo comunicar con el servidor.', false);
        }).finally(function () {
            enviando = false;
        });
    }

    var alta = document.getElementById('btn-articulo-waitry-alta');
    var baja = document.getElementById('btn-articulo-waitry-baja');
    var marcasBtn = document.getElementById('btn-articulo-waitry-marcas');
    if (alta) {
        alta.addEventListener('click', function () {
            post(root.getAttribute('data-url-alta'), false);
        });
    }
    if (baja) {
        baja.addEventListener('click', function () {
            post(root.getAttribute('data-url-baja'), true);
        });
    }
    if (marcasBtn) {
        marcasBtn.addEventListener('click', function () {
            post(root.getAttribute('data-url-marcas'), false);
        });
    }
})();
