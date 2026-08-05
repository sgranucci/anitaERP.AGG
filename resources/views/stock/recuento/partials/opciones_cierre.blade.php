@php
    use App\Support\Stock\RecuentoModoCierreSupport;

    $modoDefault = RecuentoModoCierreSupport::modoPorDefecto($recuento);
    $textos = RecuentoModoCierreSupport::textosImplicancias();
    $fechaRecuentoFmt = optional($recuento->fecha)->format('d/m/Y');
    $diasAntiguedad = RecuentoModoCierreSupport::diasAntiguedadFecha($recuento->fecha);
    $avisoFechaAntigua = RecuentoModoCierreSupport::debeAvisarFechaAntigua($recuento->fecha);
    $bloqueaFechaAntigua = RecuentoModoCierreSupport::bloqueaCierrePorFechaAntigua(
        $recuento->fecha,
        RecuentoModoCierreSupport::MODO_FECHA_RECUENTO
    );
    $diasBloqueo = RecuentoModoCierreSupport::diasBloqueoFechaAntigua();
    $puedeCerrarParcial = in_array($recuento->estado, ['PENDIENTE', 'SUSPENDIDO'], true)
        && can('cerrar-recuento-parcial', false);
    $puedeCerrarTotal = in_array($recuento->estado, ['PENDIENTE', 'SUSPENDIDO'], true)
        && can('cerrar-recuento-total', false);
@endphp

@if ($puedeCerrarParcial || $puedeCerrarTotal)
<div class="card border-primary mb-3" id="panel-opciones-cierre-recuento">
    <div class="card-header py-2 bg-light">
        <strong><i class="fa fa-cog"></i> Cierre de inventario — modo de ajuste</strong>
    </div>
    <div class="card-body pb-2">
        <p class="small text-muted mb-3">
            Elija cómo calcular la diferencia entre lo contado y el saldo del sistema.
            La fecha del recuento es <strong>{{ $fechaRecuentoFmt ?: '—' }}</strong>.
        </p>

        @if ($avisoFechaAntigua)
            <div class="alert alert-warning py-2 px-3 small" id="aviso-fecha-antigua-recuento">
                <i class="fa fa-exclamation-triangle"></i>
                {{ RecuentoModoCierreSupport::mensajeAvisoFechaAntigua($recuento) }}
                @if ($bloqueaFechaAntigua)
                    <br>
                    <strong>
                        Con más de {{ $diasBloqueo }} días de antigüedad no se permite cerrar
                        «a fecha del recuento». Corrija la fecha o use «Al saldo actual».
                    </strong>
                @endif
            </div>
        @endif

        <div class="form-group mb-3">
            <div class="custom-control custom-radio mb-2">
                <input type="radio" id="modo_cierre_fecha" name="modo_cierre_selector"
                    class="custom-control-input modo-cierre-radio"
                    value="{{ RecuentoModoCierreSupport::MODO_FECHA_RECUENTO }}"
                    @checked($modoDefault === RecuentoModoCierreSupport::MODO_FECHA_RECUENTO && ! $bloqueaFechaAntigua)
                    @disabled($bloqueaFechaAntigua)>
                <label class="custom-control-label font-weight-bold" for="modo_cierre_fecha">
                    A fecha del recuento ({{ $fechaRecuentoFmt }})
                    @if ($bloqueaFechaAntigua)
                        <span class="text-danger">(bloqueado por fecha antigua)</span>
                    @endif
                </label>
            </div>
            <div class="alert alert-info py-2 px-3 small mb-3" id="help-modo-fecha">
                {{ $textos[RecuentoModoCierreSupport::MODO_FECHA_RECUENTO] }}
            </div>

            <div class="custom-control custom-radio mb-2">
                <input type="radio" id="modo_cierre_actual" name="modo_cierre_selector"
                    class="custom-control-input modo-cierre-radio"
                    value="{{ RecuentoModoCierreSupport::MODO_SALDO_ACTUAL }}"
                    @checked($modoDefault === RecuentoModoCierreSupport::MODO_SALDO_ACTUAL || $bloqueaFechaAntigua)>
                <label class="custom-control-label font-weight-bold" for="modo_cierre_actual">
                    Al saldo actual (hoy)
                </label>
            </div>
            <div class="alert alert-secondary py-2 px-3 small mb-0" id="help-modo-actual">
                {{ $textos[RecuentoModoCierreSupport::MODO_SALDO_ACTUAL] }}
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
            @if ($puedeCerrarParcial)
            <form action="{{ route('cerrar_recuento_parcial', ['id' => $recuento->id]) }}" method="POST"
                class="form-cierre-recuento d-inline"
                data-confirm="¿Cerrar parcialmente? Se ajustará el stock de las líneas contadas con diferencia según el modo elegido."
                data-aviso-fecha="{{ $avisoFechaAntigua ? RecuentoModoCierreSupport::mensajeAvisoFechaAntigua($recuento) : '' }}"
                data-bloquea-fecha="{{ $bloqueaFechaAntigua ? '1' : '0' }}">
                @csrf
                <input type="hidden" name="modo_cierre" class="modo-cierre-input" value="{{ $bloqueaFechaAntigua ? RecuentoModoCierreSupport::MODO_SALDO_ACTUAL : $modoDefault }}">
                <button type="submit" class="btn btn-info btn-sm">
                    <i class="fa fa-check"></i> Cerrar parcial
                </button>
            </form>
            @endif

            @if ($puedeCerrarTotal)
            <form action="{{ route('cerrar_recuento_total', ['id' => $recuento->id]) }}" method="POST"
                class="form-cierre-recuento d-inline"
                data-confirm="¿Cerrar totalmente? Se ajustará todo el depósito (líneas contadas y artículos con saldo sin contar) según el modo elegido."
                data-aviso-fecha="{{ $avisoFechaAntigua ? RecuentoModoCierreSupport::mensajeAvisoFechaAntigua($recuento) : '' }}"
                data-bloquea-fecha="{{ $bloqueaFechaAntigua ? '1' : '0' }}">
                @csrf
                <input type="hidden" name="modo_cierre" class="modo-cierre-input" value="{{ $bloqueaFechaAntigua ? RecuentoModoCierreSupport::MODO_SALDO_ACTUAL : $modoDefault }}">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="fa fa-check-double"></i> Cerrar total
                </button>
            </form>
            @endif
        </div>
    </div>
</div>

<script>
(function () {
    var MODO_FECHA = @json(RecuentoModoCierreSupport::MODO_FECHA_RECUENTO);
    var BLOQUEA_FECHA = @json($bloqueaFechaAntigua);
    var DIAS_ANTIGUEDAD = @json($diasAntiguedad);

    function modoSeleccionado() {
        var el = document.querySelector('.modo-cierre-radio:checked');
        return el ? el.value : @json(RecuentoModoCierreSupport::MODO_SALDO_ACTUAL);
    }
    function sincronizarModoCierre() {
        var val = modoSeleccionado();
        document.querySelectorAll('.modo-cierre-input').forEach(function (inp) {
            inp.value = val;
        });
    }
    document.querySelectorAll('.modo-cierre-radio').forEach(function (radio) {
        radio.addEventListener('change', sincronizarModoCierre);
    });
    document.querySelectorAll('.form-cierre-recuento').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            sincronizarModoCierre();
            var modo = modoSeleccionado();
            if (BLOQUEA_FECHA && modo === MODO_FECHA) {
                e.preventDefault();
                window.alert(
                    'No se puede cerrar a fecha del recuento: la fecha tiene '
                    + DIAS_ANTIGUEDAD + ' días de antigüedad. Corrija la fecha o use «Al saldo actual».'
                );
                return;
            }
            var aviso = form.getAttribute('data-aviso-fecha') || '';
            if (aviso && modo === MODO_FECHA) {
                if (!window.confirm(aviso + '\n\n¿Confirma el cierre a fecha del recuento de todas formas?')) {
                    e.preventDefault();
                    return;
                }
            }
            var msg = form.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });
    sincronizarModoCierre();
})();
</script>
@endif
