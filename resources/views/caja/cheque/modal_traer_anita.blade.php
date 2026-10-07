<div class="modal fade" id="modalTraerChequeAnita" tabindex="-1" role="dialog" aria-labelledby="modalTraerChequeAnitaLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('traer_cheque_anita') }}" id="form-traer-cheque-anita">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTraerChequeAnitaLabel">Traer cheque de Anita</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label for="traer_cheque_nro_interno">Número interno Anita</label>
                        <input type="number"
                               name="nro_interno"
                               id="traer_cheque_nro_interno"
                               class="form-control"
                               min="1"
                               step="1"
                               required
                               inputmode="numeric"
                               autocomplete="off"
                               placeholder="Ej. 97072">
                    </div>
                    <p class="text-muted small mb-0">
                        Es el interno de Anita (columna Int.), no el número impreso del cheque.
                        Trae el cheque de terceros con el estado que tenga ahora, aunque esté dado de baja.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-download"></i> Traer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'traer-cheque-anita-overlay',
    'tituloId' => 'traer-cheque-anita-overlay-titulo',
    'subtituloId' => 'traer-cheque-anita-overlay-subtitulo',
    'titulo' => 'Trayendo el cheque desde Anita…',
    'subtitulo' => 'Puede demorar unos segundos. No cierre la página.',
])
<script>
(function () {
    var form = document.getElementById('form-traer-cheque-anita');
    var overlay = document.getElementById('traer-cheque-anita-overlay');
    function ocultar() {
        if (!overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.setAttribute('aria-hidden', 'true');
    }
    if (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) {
                return;
            }
            if (overlay) {
                overlay.classList.remove('d-none');
                overlay.style.display = 'flex';
                overlay.setAttribute('aria-hidden', 'false');
            }
        });
    }
    window.addEventListener('pageshow', ocultar);
    if (window.jQuery) {
        window.jQuery('#modalTraerChequeAnita').on('shown.bs.modal', function () {
            var input = document.getElementById('traer_cheque_nro_interno');
            if (input) {
                input.focus();
                input.select();
            }
        });
    }
})();
</script>
