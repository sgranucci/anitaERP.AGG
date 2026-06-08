@php
    use App\Support\Stock\RecuentoModoCierreSupport;

    $modoDefault = RecuentoModoCierreSupport::modoPorDefecto($recuento);
    $textos = RecuentoModoCierreSupport::textosImplicancias();
    $fechaRecuentoFmt = optional($recuento->fecha)->format('d/m/Y');
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

        <div class="form-group mb-3">
            <div class="custom-control custom-radio mb-2">
                <input type="radio" id="modo_cierre_fecha" name="modo_cierre_selector"
                    class="custom-control-input modo-cierre-radio"
                    value="{{ RecuentoModoCierreSupport::MODO_FECHA_RECUENTO }}"
                    @checked($modoDefault === RecuentoModoCierreSupport::MODO_FECHA_RECUENTO)>
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
                    @checked($modoDefault === RecuentoModoCierreSupport::MODO_SALDO_ACTUAL)>
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
                data-confirm="¿Cerrar parcialmente? Se ajustará el stock de las líneas contadas con diferencia según el modo elegido.">
                @csrf
                <input type="hidden" name="modo_cierre" class="modo-cierre-input" value="{{ $modoDefault }}">
                <button type="submit" class="btn btn-info btn-sm">
                    <i class="fa fa-check"></i> Cerrar parcial
                </button>
            </form>
            @endif

            @if ($puedeCerrarTotal)
            <form action="{{ route('cerrar_recuento_total', ['id' => $recuento->id]) }}" method="POST"
                class="form-cierre-recuento d-inline"
                data-confirm="¿Cerrar totalmente? Se ajustará todo el depósito (líneas contadas y artículos con saldo sin contar) según el modo elegido.">
                @csrf
                <input type="hidden" name="modo_cierre" class="modo-cierre-input" value="{{ $modoDefault }}">
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
    function modoSeleccionado() {
        var el = document.querySelector('.modo-cierre-radio:checked');
        return el ? el.value : '{{ RecuentoModoCierreSupport::MODO_SALDO_ACTUAL }}';
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
