@extends("theme.$theme.layout")

@section('titulo')
    {{ $edicion ? 'Editar resultado online' : 'Nuevo resultado online' }}
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
    <script>
    (function () {
        function numero(id) {
            var el = document.getElementById(id);
            if (!el) {
                return 0;
            }
            var texto = String(el.value || '').trim();
            if (texto.indexOf(',') >= 0 && texto.indexOf('.') >= 0) {
                texto = texto.replace(/\./g, '').replace(',', '.');
            } else {
                texto = texto.replace(',', '.');
            }
            var valor = parseFloat(texto);
            return isNaN(valor) ? 0 : valor;
        }
        function texto(valor, decimales) {
            return valor.toLocaleString('es-AR', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
        }
        function recalcular() {
            var apostado = numero('apostado');
            var resultado = apostado - numero('pagado');
            var slot = numero('coin_in') - numero('coin_out') - numero('pago_manual');
            var resultadoEl = document.getElementById('resultado');
            var porcentajeEl = document.getElementById('porcentaje');
            var slotEl = document.getElementById('slot_resultado');
            if (resultadoEl) {
                resultadoEl.value = texto(resultado, 2);
            }
            if (porcentajeEl) {
                porcentajeEl.value = texto(apostado === 0 ? 0 : (resultado / apostado) * 100, 2);
            }
            if (slotEl) {
                slotEl.value = texto(slot, 2);
            }
        }
        document.querySelectorAll('.js-resultado-online').forEach(function (el) {
            el.addEventListener('input', recalcular);
        });
        recalcular();
    })();
    </script>
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.form-error')
<div class="row">
    <div class="col-lg-8">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">{{ $edicion ? 'Editar' : 'Cargar' }} resultado online</h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_resultado_online', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form method="POST" action="{{ route('guardar_tesoreria_crown_resultado_online') }}" class="form-horizontal" data-enter-campos="1">
                @csrf
                <input type="hidden" name="id" value="{{ $edicion ? $fila->id : '' }}">
                <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                <div class="card-body pb-1">
                    <p class="text-muted small mb-3">{{ $sala->codigo }} — {{ $sala->nombre }}. El resultado y el porcentaje se calculan. El anexo I usa el resultado como On-line y el resultado de slots como recaudación de tragamonedas.</p>
                    <div class="form-group row">
                        <label for="fecha" class="col-lg-4 control-label text-right pr-2">Fecha</label>
                        <div class="col-lg-4">
                            <input type="date" name="fecha" id="fecha" class="form-control form-control-sm" required value="{{ old('fecha', optional($fila->fecha)->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="attendance" class="col-lg-4 control-label text-right pr-2">Asistencia</label>
                        <div class="col-lg-4">
                            <input type="number" name="attendance" id="attendance" class="form-control form-control-sm" step="1" inputmode="numeric" min="0" value="{{ old('attendance', (int) ($fila->attendance ?? 0)) }}">
                        </div>
                    </div>
                </div>
                <div class="card card-outline card-info mx-3">
                    <div class="card-header py-2"><h3 class="card-title">Casino online</h3></div>
                    <div class="card-body pb-0">
                        @foreach ([
                            'apostado' => 'Apostado',
                            'pagado' => 'Pagado',
                            'drop' => 'Drop',
                            'poder_publico' => 'Poder público',
                        ] as $campo => $etiqueta)
                            <div class="form-group row">
                                <label for="{{ $campo }}" class="col-lg-4 control-label text-right pr-2">{{ $etiqueta }}</label>
                                <div class="col-lg-4">
                                    <input type="text" inputmode="decimal" name="{{ $campo }}" id="{{ $campo }}" class="form-control form-control-sm text-right js-resultado-online" value="{{ old($campo, number_format((float) ($fila->{$campo} ?? 0), 2, ',', '.')) }}">
                                </div>
                            </div>
                        @endforeach
                        <div class="form-group row">
                            <label for="resultado" class="col-lg-4 control-label text-right pr-2">Resultado</label>
                            <div class="col-lg-4">
                                <input type="text" id="resultado" class="form-control form-control-sm text-right" readonly tabindex="-1" value="{{ number_format((float) ($fila->resultado ?? 0), 2, ',', '.') }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="porcentaje" class="col-lg-4 control-label text-right pr-2">Porcentaje</label>
                            <div class="col-lg-4">
                                <input type="text" id="porcentaje" class="form-control form-control-sm text-right" readonly tabindex="-1" value="{{ number_format((float) ($fila->porcentaje ?? 0), 2, ',', '.') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card card-outline card-info mx-3">
                    <div class="card-header py-2"><h3 class="card-title">Slots online</h3></div>
                    <div class="card-body pb-0">
                        @foreach ([
                            'coin_in' => 'Coin in',
                            'coin_out' => 'Coin out',
                            'pago_manual' => 'Pago manual',
                        ] as $campo => $etiqueta)
                            <div class="form-group row">
                                <label for="{{ $campo }}" class="col-lg-4 control-label text-right pr-2">{{ $etiqueta }}</label>
                                <div class="col-lg-4">
                                    <input type="text" inputmode="decimal" name="{{ $campo }}" id="{{ $campo }}" class="form-control form-control-sm text-right js-resultado-online" value="{{ old($campo, number_format((float) ($fila->{$campo} ?? 0), 2, ',', '.')) }}">
                                </div>
                            </div>
                        @endforeach
                        <div class="form-group row">
                            <label for="cantidad_slots" class="col-lg-4 control-label text-right pr-2">Cantidad de slots</label>
                            <div class="col-lg-4">
                                <input type="number" name="cantidad_slots" id="cantidad_slots" class="form-control form-control-sm" step="1" inputmode="numeric" min="0" value="{{ old('cantidad_slots', (int) ($fila->cantidad_slots ?? 0)) }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="slot_resultado" class="col-lg-4 control-label text-right pr-2">Resultado slots</label>
                            <div class="col-lg-4">
                                <input type="text" id="slot_resultado" class="form-control form-control-sm text-right" readonly tabindex="-1" value="{{ number_format((float) ($fila->slot_resultado ?? 0), 2, ',', '.') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                    <a href="{{ route('tesoreria_crown_resultado_online', ['sala_id' => $sala->id]) }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
