@php
    use App\Support\Stock\RecuentoModoCierreSupport;

    $modoDefault = RecuentoModoCierreSupport::modoPorDefecto($recuento);
    $textos = RecuentoModoCierreSupport::textosImplicancias();
    $fechaRecuentoFmt = optional($recuento->fecha)->format('d/m/Y');
    $avisoFechaAntigua = RecuentoModoCierreSupport::debeAvisarFechaAntigua($recuento->fecha);
    $resumenPosteriores = ($recuento->fecha && $recuento->deposito_id)
        ? RecuentoModoCierreSupport::resumenMovimientosPosteriores(
            (int) $recuento->deposito_id,
            $recuento->fecha->toDateString()
        )
        : ['movimientos' => 0, 'articulos' => 0];
    $bloqueaSaldoActual = ($resumenPosteriores['movimientos'] ?? 0) > 0;
    $modoInicial = $bloqueaSaldoActual
        ? RecuentoModoCierreSupport::MODO_FECHA_RECUENTO
        : $modoDefault;
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
                @if ($bloqueaSaldoActual)
                    <br>
                    <strong>
                        {{ RecuentoModoCierreSupport::mensajeBloqueoSaldoActualConMovimientosPosteriores($recuento, (int) $resumenPosteriores['movimientos'], (int) $resumenPosteriores['articulos']) }}
                    </strong>
                @endif
            </div>
        @elseif ($bloqueaSaldoActual)
            <div class="alert alert-warning py-2 px-3 small" id="aviso-movimientos-posteriores-recuento">
                <i class="fa fa-exclamation-triangle"></i>
                {{ RecuentoModoCierreSupport::mensajeBloqueoSaldoActualConMovimientosPosteriores($recuento, (int) $resumenPosteriores['movimientos'], (int) $resumenPosteriores['articulos']) }}
            </div>
        @endif

        <div class="form-group mb-3">
            <div class="custom-control custom-radio mb-2">
                <input type="radio" id="modo_cierre_fecha" name="modo_cierre_selector"
                    class="custom-control-input modo-cierre-radio"
                    value="{{ RecuentoModoCierreSupport::MODO_FECHA_RECUENTO }}"
                    @checked($modoInicial === RecuentoModoCierreSupport::MODO_FECHA_RECUENTO)>
                <label class="custom-control-label font-weight-bold" for="modo_cierre_fecha">
                    A fecha del recuento ({{ $fechaRecuentoFmt }})
                </label>
            </div>
            <div class="alert alert-info py-2 px-3 small mb-3" id="help-modo-fecha">
                {{ $textos[RecuentoModoCierreSupport::MODO_FECHA_RECUENTO] }}
            </div>

            <div class="custom-control custom-radio mb-2">
                <input type="radio" id="modo_cierre_actual" name="modo_cierre_selector"
                    class="custom-control-input modo-cierre-radio"
                    value="{{ RecuentoModoCierreSupport::MODO_SALDO_ACTUAL }}"
                    @checked($modoInicial === RecuentoModoCierreSupport::MODO_SALDO_ACTUAL)
                    @disabled($bloqueaSaldoActual)>
                <label class="custom-control-label font-weight-bold" for="modo_cierre_actual">
                    Al saldo actual (hoy)
                    @if ($bloqueaSaldoActual)
                        <span class="text-danger">(bloqueado: hay movimientos posteriores)</span>
                    @endif
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
                data-bloquea-saldo-actual="{{ $bloqueaSaldoActual ? '1' : '0' }}">
                @csrf
                <input type="hidden" name="modo_cierre" class="modo-cierre-input" value="{{ $modoInicial }}">
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
                data-bloquea-saldo-actual="{{ $bloqueaSaldoActual ? '1' : '0' }}">
                @csrf
                <input type="hidden" name="modo_cierre" class="modo-cierre-input" value="{{ $modoInicial }}">
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
    var MODO_ACTUAL = @json(RecuentoModoCierreSupport::MODO_SALDO_ACTUAL);
    var BLOQUEA_SALDO_ACTUAL = @json($bloqueaSaldoActual);

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
            if (BLOQUEA_SALDO_ACTUAL && modo === MODO_ACTUAL) {
                e.preventDefault();
                window.alert(
                    'No se puede cerrar al saldo actual: hay movimientos de stock posteriores a la fecha del recuento. Cierre a fecha del recuento.'
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
