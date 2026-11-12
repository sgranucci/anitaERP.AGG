@extends("theme.$theme.layout")

@section('titulo')
    {{ $edicion ? 'Editar resultado online' : 'Nuevo resultado online' }}
@endsection

@section('styles')
<style>
.ro-form .form-group { margin-bottom: .45rem; }
.ro-form .control-label { font-size: .85rem; padding-top: .35rem; }
.ro-form .card-outline { margin-bottom: .75rem; }
.ro-form .card-header { padding: .4rem .75rem; }
.ro-form .card-header .card-title { font-size: .95rem; margin: 0; }
.ro-form .card-body { padding: .65rem .75rem .35rem; }
.ro-kpi {
    display: flex; flex-wrap: wrap; gap: .5rem;
}
.ro-kpi .kpi {
    background: #f4f9fc; border: 1px solid #d6eaf8; border-radius: .35rem;
    padding: .35rem .7rem; min-width: 7.5rem;
}
.ro-kpi .kpi .lbl { display: block; font-size: .7rem; color: #5d6d7e; text-transform: uppercase; letter-spacing: .02em; }
.ro-kpi .kpi .val { font-weight: 600; font-size: .95rem; color: #1a5276; }
.ro-calc { background: #eef6fb !important; }
.co-btn-carga {
    background: linear-gradient(135deg, #8e44ad 0%, #2980b9 55%, #16a085 100%);
    border: 0; color: #fff !important; font-weight: 600;
}
.co-btn-carga:hover { filter: brightness(1.06); color: #fff !important; }
</style>
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
        function setKpi(id, valor, decimales) {
            var el = document.getElementById(id);
            if (el) {
                el.textContent = texto(valor, decimales);
            }
        }
        function setCalc(id, valor, decimales) {
            var el = document.getElementById(id);
            if (el) {
                el.value = texto(valor, decimales);
            }
        }
        function recalcular() {
            var slot = numero('coin_in') - numero('coin_out') - numero('pago_manual');
            setCalc('slot_resultado', slot, 2);
            setKpi('kpi-slot', slot, 2);
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
@php
    $slotIni = (float) ($fila->slot_resultado ?? 0);
@endphp
<div class="row">
    <div class="col-12">
        <div class="card card-primary ro-form">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    {{ $edicion ? 'Editar' : 'Cargar' }} resultado online
                    <span class="text-sm font-weight-normal ml-1">{{ $sala->codigo }} — {{ $sala->nombre }}</span>
                </h3>
                <div class="card-tools d-flex flex-wrap align-items-center" style="gap:.35rem;">
                    @if (can('listar-tesoreria-crown-casino-online', false) || can('crear-tesoreria-crown-casino-online', false))
                        <a href="{{ route('tesoreria_crown_casino_online') }}" class="btn btn-sm co-btn-carga" title="Carga global Del Río / Betsur / Apuestas">
                            <i class="fa fa-globe"></i> Casino online diario
                        </a>
                    @endif
                    <a href="{{ route('tesoreria_crown_resultado_online', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form method="POST" action="{{ route('guardar_tesoreria_crown_resultado_online') }}" class="form-horizontal" data-enter-campos="1">
                @csrf
                <input type="hidden" name="id" value="{{ $edicion ? $fila->id : '' }}">
                <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap align-items-end justify-content-between mb-2" style="gap:.75rem;">
                        <div>
                            <label for="fecha" class="mb-0 small text-muted d-block">Fecha</label>
                            <input type="date" name="fecha" id="fecha" class="form-control form-control-sm" style="width:10.5rem;" required value="{{ old('fecha', optional($fila->fecha)->format('Y-m-d')) }}">
                        </div>
                        <div class="ro-kpi">
                            <div class="kpi">
                                <span class="lbl">Resultado slots</span>
                                <span class="val" id="kpi-slot">{{ number_format($slotIni, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-2">
                        Solo slots de la sala: coin in − coin out − pago manual.
                        Casinos del Río, Betsur y Apuestas deportivas se cargan una sola vez por día en
                        <a href="{{ route('tesoreria_crown_casino_online') }}">Casino online diario</a>.
                    </p>

                    <div class="row">
                        <div class="col-lg-6 col-xl-4">
                            <div class="card card-outline card-info">
                                <div class="card-header"><h3 class="card-title"><i class="fa fa-gamepad mr-1"></i> Slots online</h3></div>
                                <div class="card-body">
                                    @foreach ([
                                        'coin_in' => 'Coin in',
                                        'coin_out' => 'Coin out',
                                        'pago_manual' => 'Pago manual',
                                    ] as $campo => $etiqueta)
                                        <div class="form-group row">
                                            <label for="{{ $campo }}" class="col-5 control-label text-right pr-2">{{ $etiqueta }}</label>
                                            <div class="col-7">
                                                <input type="text" inputmode="decimal" name="{{ $campo }}" id="{{ $campo }}" class="form-control form-control-sm text-right js-resultado-online" value="{{ old($campo, number_format((float) ($fila->{$campo} ?? 0), 2, ',', '.')) }}">
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="form-group row">
                                        <label for="cantidad_slots" class="col-5 control-label text-right pr-2">Cant. slots</label>
                                        <div class="col-7">
                                            <input type="number" name="cantidad_slots" id="cantidad_slots" class="form-control form-control-sm text-right" step="1" inputmode="numeric" min="0" value="{{ old('cantidad_slots', (int) ($fila->cantidad_slots ?? 0)) }}">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="slot_resultado" class="col-5 control-label text-right pr-2">Resultado</label>
                                        <div class="col-7">
                                            <input type="text" id="slot_resultado" class="form-control form-control-sm text-right ro-calc" readonly tabindex="-1" value="{{ number_format($slotIni, 2, ',', '.') }}">
                                        </div>
                                    </div>
                                    <div class="form-group row mb-0">
                                        <label for="subtotal_slot" class="col-5 control-label text-right pr-2" title="Soft count nativo. Si está cargado, el import de Anita no lo pisa.">Subtotal soft</label>
                                        <div class="col-7">
                                            <input type="text" inputmode="decimal" name="subtotal_slot" id="subtotal_slot" class="form-control form-control-sm text-right" value="{{ old('subtotal_slot', $fila->subtotal_slot !== null ? number_format((float) $fila->subtotal_slot, 2, ',', '.') : '') }}">
                                            <small class="text-muted" style="font-size:.7rem;">No lo pisa Anita si está cargado</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer py-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Guardar</button>
                    <a href="{{ route('tesoreria_crown_resultado_online', ['sala_id' => $sala->id]) }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
