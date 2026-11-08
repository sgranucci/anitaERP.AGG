@php
    $bingo = $bingo ?? null;
    $pesos = fn ($importe) => number_format((float) $importe, 2, ',', '.');
    $puedeEditar = can('actualizar-tesoreria-crown-bingo', false)
        || can('crear-tesoreria-crown-bingo', false);
    $editable = $puedeEditar && ($jornada->estaAbierta() || can('actualizar-tesoreria-crown-bingo', false));
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2 d-flex flex-wrap align-items-center">
        <h3 class="card-title mb-0 mr-auto"><i class="fa fa-th"></i> Bingo</h3>
        <a href="{{ route('tesoreria_crown_bingo', ['sala_id' => $jornada->sala_id]) }}" class="btn btn-outline-info btn-sm">
            Histórico
        </a>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Igual que <code>a-movbingo.c</code>: cartones − premios − RSE = resultado.
            Alimenta el bloque BINGO del Anexo II y la línea Bingo del Anexo I.
        </p>

        <div class="row mb-3">
            <div class="col-md-3">
                <div class="border rounded px-3 py-2">
                    <div class="text-muted small">Cartones</div>
                    <strong>{{ $pesos($bingo?->tot_carton ?? 0) }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded px-3 py-2">
                    <div class="text-muted small">Premios</div>
                    <strong>{{ $pesos($bingo?->tot_premio ?? 0) }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded px-3 py-2">
                    <div class="text-muted small">RSE</div>
                    <strong>{{ $pesos($bingo?->tot_rse ?? 0) }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded px-3 py-2">
                    <div class="text-muted small">Resultado</div>
                    <strong>{{ $pesos($bingo?->resultado ?? 0) }}</strong>
                </div>
            </div>
        </div>

        @if ($editable)
            <form method="POST" action="{{ route('guardar_bingo_tesoreria_crown_jornada', $jornada->id) }}" class="form-horizontal" data-enter-campos="1" id="form-bingo-jornada">
                @csrf
                <div class="form-group row">
                    <label for="bingo_hora" class="col-lg-3 control-label text-right pr-2">Hora</label>
                    <div class="col-lg-2">
                        <input type="text" name="hora" id="bingo_hora" class="form-control form-control-sm" maxlength="5" placeholder="HH:MM"
                               value="{{ old('hora', $bingo?->hora ?? '') }}">
                    </div>
                </div>
                @foreach ([
                    'tot_carton' => 'Total cartones vendidos',
                    'tot_premio' => 'Total pago de premios',
                    'tot_rse' => 'Total pago RSE',
                ] as $campo => $etiqueta)
                    <div class="form-group row">
                        <label for="bingo_{{ $campo }}" class="col-lg-3 control-label text-right pr-2">{{ $etiqueta }}</label>
                        <div class="col-lg-3">
                            <input type="text" inputmode="decimal" name="{{ $campo }}" id="bingo_{{ $campo }}"
                                   class="form-control form-control-sm text-right js-bingo-jornada" required
                                   value="{{ old($campo, number_format((float) ($bingo?->{$campo} ?? 0), 2, ',', '.')) }}">
                        </div>
                    </div>
                @endforeach
                <div class="form-group row">
                    <label for="bingo_resultado" class="col-lg-3 control-label text-right pr-2">Resultado</label>
                    <div class="col-lg-3">
                        <input type="text" id="bingo_resultado" class="form-control form-control-sm text-right" readonly tabindex="-1"
                               value="{{ number_format((float) ($bingo?->resultado ?? 0), 2, ',', '.') }}">
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <div class="col-lg-9 offset-lg-3">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-save"></i> Guardar bingo del día
                        </button>
                        @if ($bingo?->exists && can('eliminar-tesoreria-crown-bingo', false))
                            <button type="submit" formaction="{{ route('eliminar_bingo_tesoreria_crown_jornada', $jornada->id) }}"
                                    class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('¿Eliminar el bingo de este día?');">
                                <i class="fa fa-times-circle"></i> Eliminar
                            </button>
                        @endif
                    </div>
                </div>
            </form>
            <script>
            (function () {
                function numero(id) {
                    var el = document.getElementById(id);
                    if (!el) { return 0; }
                    var texto = String(el.value || '').trim();
                    if (texto.indexOf(',') >= 0 && texto.indexOf('.') >= 0) {
                        texto = texto.replace(/\./g, '').replace(',', '.');
                    } else {
                        texto = texto.replace(',', '.');
                    }
                    var valor = parseFloat(texto);
                    return isNaN(valor) ? 0 : valor;
                }
                function recalcular() {
                    var r = numero('bingo_tot_carton') - numero('bingo_tot_premio') - numero('bingo_tot_rse');
                    var el = document.getElementById('bingo_resultado');
                    if (el) {
                        el.value = r.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                }
                document.querySelectorAll('.js-bingo-jornada').forEach(function (el) {
                    el.addEventListener('input', recalcular);
                });
                recalcular();
            })();
            </script>
        @elseif (! $bingo)
            <p class="text-muted mb-0">No hay bingo cargado para este día.</p>
        @endif
    </div>
</div>
