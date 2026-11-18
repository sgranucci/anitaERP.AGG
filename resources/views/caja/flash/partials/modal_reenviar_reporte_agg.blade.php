{{--
  Header card-info (#17a2b8, texto blanco):
  - opener: btn-light (sólido, legible sobre cyan)
  - vecinos del toolbar: btn-outline-light
  - confirmar envío: btn-warning (acción con mail a destinatarios)
--}}
@php
    $suscripcionesReenvio = $suscripcionesReenvio ?? collect();
    $perfilesVistaFlashAgg = $perfilesVistaFlashAgg ?? [];
    $soloBoton = (bool) ($soloBoton ?? false);
    $soloModal = (bool) ($soloModal ?? false);
    $idPreferida = null;
    foreach ($suscripcionesReenvio as $s) {
        if (stripos((string) $s->nombre, 'finanzas') !== false
            || ($s->perfil_vista ?? '') === \App\Support\Caja\Flash\FlashReporteAggPerfilVistaSupport::FINANZAS) {
            $idPreferida = (int) $s->id;
            break;
        }
    }
    if ($idPreferida === null && $suscripcionesReenvio->isNotEmpty()) {
        $idPreferida = (int) $suscripcionesReenvio->first()->id;
    }
@endphp
@if ($suscripcionesReenvio->isNotEmpty())
    @if (! $soloModal)
        <button type="button"
                class="btn btn-light btn-sm mr-2"
                data-toggle="modal"
                data-target="#modal-reenviar-flash-reporte-agg"
                title="Vuelve a armar el Excel con los datos actuales y lo manda a los destinatarios de la suscripción">
            <i class="fa fa-envelope"></i> Reenviar Flash AGG
        </button>
    @endif

    @if (! $soloBoton)
        <div class="modal fade" id="modal-reenviar-flash-reporte-agg" tabindex="-1" role="dialog"
             aria-labelledby="modal-reenviar-flash-reporte-agg-titulo" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" id="form-reenviar-flash-reporte-agg" class="modal-content"
                      onsubmit="return confirm('¿Reenviar ahora el Flash Report AGG a los destinatarios de la suscripción elegida?');">
                    @csrf
                    <input type="hidden" name="retorno" value="flash_caja">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-reenviar-flash-reporte-agg-titulo">
                            Reenviar Flash Report AGG
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">
                            Arma el Excel con los flashes ya guardados (incluye cambios posteriores a las 16:00)
                            y lo manda a los destinatarios de la suscripción.
                        </p>
                        <div class="form-group mb-0">
                            <label for="suscripcion_reenvio_flash_agg">Suscripción</label>
                            <select id="suscripcion_reenvio_flash_agg"
                                    class="form-control"
                                    required>
                                @foreach ($suscripcionesReenvio as $s)
                                    @php
                                        $perfilLabel = $perfilesVistaFlashAgg[$s->perfil_vista ?? '']
                                            ?? ($s->perfil_vista ?? '');
                                        $hora = trim((string) ($s->hora ?? ''));
                                    @endphp
                                    <option value="{{ $s->id }}" @selected((int) $s->id === $idPreferida)>
                                        {{ $s->nombre }}
                                        @if ($perfilLabel !== '')
                                            — {{ $perfilLabel }}
                                        @endif
                                        @if ($hora !== '')
                                            ({{ $hora }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">
                                Por defecto se preselecciona Finanzas si está activa.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fa fa-paper-plane"></i> Reenviar mail
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            (function () {
                var form = document.getElementById('form-reenviar-flash-reporte-agg');
                var select = document.getElementById('suscripcion_reenvio_flash_agg');
                if (!form || !select) {
                    return;
                }
                var plantilla = @json(route('probar_suscripcion_flash_reporte_agg', ['id' => '__SID__']));
                form.addEventListener('submit', function () {
                    form.action = plantilla.replace('__SID__', encodeURIComponent(select.value));
                });
            })();
        </script>
    @endif
@endif
