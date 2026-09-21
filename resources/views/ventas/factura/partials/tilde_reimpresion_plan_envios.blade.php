@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli())
    <div class="form-check d-inline-flex align-items-center mr-2 mb-1">
        <input type="checkbox" id="reimpresion_con_envios" class="form-check-input" value="1" style="position:static;margin:0 .35rem 0 0;">
        <label class="form-check-label mb-0" for="reimpresion_con_envios" style="color:#fff;">
            Plan de envíos
        </label>
    </div>
    <script>
        document.addEventListener('click', function (ev) {
            var tilde = document.getElementById('reimpresion_con_envios');
            if (!tilde || !tilde.checked) {
                return;
            }
            var enlace = ev.target.closest('a[href*="listaunafactura"]');
            if (!enlace) {
                return;
            }
            ev.preventDefault();
            var url = new URL(enlace.getAttribute('href'), window.location.href);
            url.searchParams.set('con_envios', '1');
            window.location = url.toString();
        });
    </script>
@endif
