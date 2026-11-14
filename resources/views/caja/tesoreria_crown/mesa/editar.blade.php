@extends("theme.$theme.layout")

@section('titulo')
    Movimiento de mesa
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/cajero.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/cajero.js')) }}"></script>
<script>
(function () {
    function texto(numero) {
        return numero.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    document.querySelectorAll('.js-panel-valor').forEach(function (panel) {
        function recalcular() {
            var total = 0;
            panel.querySelectorAll('input[data-denom]').forEach(function (input) {
                var cantidad = parseFloat(String(input.value).replace(',', '.'));
                var denominacion = parseFloat(input.getAttribute('data-denom'));
                if (!isNaN(cantidad) && !isNaN(denominacion)) {
                    total += cantidad * denominacion;
                }
            });
            panel.querySelectorAll('.js-total-valor').forEach(function (nodo) {
                nodo.textContent = texto(total);
            });
        }
        recalcular();
        panel.addEventListener('input', recalcular);
    });
    var primeraCantidad = document.querySelector('#form-mesa-crown input[data-denom]:not([disabled])');
    if (primeraCantidad) {
        window.setTimeout(function () {
            primeraCantidad.focus();
        }, 0);
        return;
    }
    var cajero = document.querySelector('.tc-cajero-codigo');
    if (cajero && cajero.value.trim() === '') {
        cajero.focus();
    }
})();
</script>
@endsection

@section('contenido')
@include('includes.mensaje')
@if ($movimiento->jornada && $escritorio)
    @include('caja.tesoreria_crown.jornada.partials.desk_nav', [
        'jornada' => $movimiento->jornada,
        'navActivo' => 'mesas',
        'puedeMesas' => $escritorio['puedeMesas'],
        'puedeBoveda' => $escritorio['puedeBoveda'],
        'navAsignaciones' => $escritorio['asignaciones'],
        'navMesas' => $escritorio['mesas'],
        'navBorradoresMesa' => $escritorio['borradoresMesa'],
        'navBoveda' => $escritorio['movimientosBoveda'],
        'puedeSlots' => $escritorio['puedeSlots'],
        'puedePoder' => $escritorio['puedePoder'],
        'puedeAnexo' => $escritorio['puedeAnexo'],
        'puedeKardex' => $escritorio['puedeKardex'] ?? false,
        'navSlots' => $escritorio['slots'],
    ])
@endif
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    {{ \App\Support\Caja\TesoreriaCrownMesaSupport::tipo($movimiento->tipo) }}
                    {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($movimiento->numero) }}
                    · {{ $movimiento->jornada->fecha->format('d/m/Y') }}
                    — {{ $movimiento->deposito->codigo ?? '' }} {{ $movimiento->deposito->nombre ?? '' }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('ver_tesoreria_crown_jornada', $movimiento->jornada_id) }}?panel=mesas&amp;mesa={{ urlencode($movimiento->deposito->codigo ?? '') }}#detalle" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al escritorio
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">{{ \App\Support\Caja\TesoreriaCrownMesaSupport::ayuda($movimiento->tipo) }}</p>
                <p>
                    Estado: <strong>{{ \App\Support\Caja\TesoreriaCrownMesaSupport::estado($movimiento->estado) }}</strong>
                    @if ($movimiento->cajero)
                        · {{ $movimiento->cajero->nombre }}
                    @endif
                    @if ($movimiento->banca)
                        · Banca {{ $movimiento->banca->nombre }} desde {{ $movimiento->banca->vigencia_desde->format('d/m/Y') }}
                    @endif
                    @if ($movimiento->confirmado_at)
                        · {{ \App\Support\Caja\TesoreriaCrownFirmaSupport::textoFirma($movimiento->usuario, $movimiento->confirmado_at) }}
                    @endif
                </p>
                <form method="POST" action="{{ route('guardar_tesoreria_crown_mesa', $movimiento->id) }}" id="form-mesa-crown" data-enter-campos="1">
                    @csrf
                    @if ($editable)
                        <div class="form-row">
                            @include('caja.tesoreria_crown.partials.campo_cajero', [
                                'salaId' => $movimiento->sala_id,
                                'codigo' => old('cajero_numero', $movimiento->cajero->codigo ?? ''),
                                'nombre' => old('cajero_numero') !== null && old('cajero_numero') !== ($movimiento->cajero->codigo ?? '') ? '' : ($movimiento->cajero->nombre ?? ''),
                                'inputId' => 'cajero_numero',
                            ])
                            <div class="form-group col-md-5 mb-2">
                                <label for="comentario">Comentario</label>
                                <input type="text" name="comentario" id="comentario" class="form-control" maxlength="80" value="{{ old('comentario', $movimiento->comentario) }}">
                            </div>
                            @if ($movimiento->tipo === 'F')
                                <div class="form-group col-md-3 mb-2">
                                    <label for="propina">Propina ($)</label>
                                    <input type="number" name="propina" id="propina" class="form-control text-right" min="0" step="0.01" inputmode="decimal" value="{{ old('propina', $movimiento->propina) }}" title="Solo monto. Neutro: no mueve billetaje. Va al Anexo II y al reporte propinas por casino.">
                                    <small class="form-text text-muted">Anexo II · neutro (sin billetaje), como propina híbrida.</small>
                                </div>
                            @endif
                        </div>
                    @elseif ($movimiento->comentario || ($movimiento->tipo === 'F' && abs((float) $movimiento->propina) > 0.001))
                        <p class="text-muted">
                            {{ $movimiento->comentario }}
                            @if ($movimiento->tipo === 'F')
                                · Propina {{ number_format((float) $movimiento->propina, 2, ',', '.') }}
                                @if ((int) ($movimiento->boveda_id ?? 0) > 0)
                                    · <a class="text-primary" href="{{ route('editar_tesoreria_crown_boveda', $movimiento->boveda_id) }}">Bóveda PROP</a>
                                @endif
                            @endif
                        </p>
                    @endif
                    <div class="row">
                        @foreach ($grupos as $grupo)
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card card-outline card-info h-100 mb-0 js-panel-valor">
                                    <div class="card-header py-2 d-flex justify-content-between">
                                        <h3 class="card-title mb-0">{{ $grupo['titulo'] }}</h3>
                                        <strong class="js-total-valor">0,00</strong>
                                    </div>
                                    <div class="card-body p-0 table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead style="background:#85C1E9;color:#17202A;"><tr><th>Denominación</th><th>Tipo</th><th style="width:110px;">Cantidad</th></tr></thead>
                                            <tbody>
                                                @foreach ($grupo['filas'] as $valor)
                                                    @php $cantidad = $cantidades[$valor->id] ?? null; @endphp
                                                    <tr>
                                                        <td class="text-right">{{ number_format((float) $valor->denominacion, 0, ',', '.') }}</td>
                                                        <td>{{ $valor->descripcion }}</td>
                                                        <td>
                                                            @if ($editable)
                                                                <input type="number" name="cantidad[{{ $valor->id }}]" class="form-control form-control-sm text-right" min="0" step="1" inputmode="numeric" data-denom="{{ (float) $valor->denominacion }}" value="{{ old('cantidad.'.$valor->id, $cantidad === null || $cantidad === '' ? '' : (int) $cantidad) }}">
                                                            @elseif ($cantidad !== null && (float) $cantidad != 0)
                                                                {{ number_format((float) $cantidad, 0, ',', '.') }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if ($editable && can('crear-tesoreria-crown-mesa', false))
                        <button type="submit" class="btn btn-primary" id="btn-guardar-mesa">Guardar borrador</button>
                    @endif
                    @if ($editable && can('editar-tesoreria-crown-mesa', false))
                        <div class="border rounded p-3 mt-3">
                            <p class="mb-2">
                                Confirmar guarda estas cantidades y fija la banca de esta fecha.
                                @if ($movimiento->tipo === 'F')
                                    Si hay propina, genera el asiento informativo de bóveda PROPINA / Mesas (solo monto, sin billetaje).
                                @endif
                                Un cambio posterior de banca no reescribe este movimiento.
                            </p>
                            <div class="form-row">
                                <div class="col-md-3 mb-2"><input type="text" name="firma_usuario" class="form-control" placeholder="Usuario" autocomplete="off" value="{{ old('firma_usuario') }}"></div>
                                <div class="col-md-3 mb-2"><input type="password" name="firma_password" class="form-control" placeholder="Clave" autocomplete="new-password"></div>
                                <div class="col-md-3 mb-2">
                                    <button type="submit" class="btn btn-success" formaction="{{ route('confirmar_tesoreria_crown_mesa', $movimiento->id) }}">Confirmar</button>
                                </div>
                            </div>
                        </div>
                    @endif
                </form>
                @if ($editable && can('eliminar-borrador-tesoreria-crown-mesa', false))
                    <form method="POST" action="{{ route('eliminar_tesoreria_crown_mesa', $movimiento->id) }}" class="mt-3" onsubmit="return confirm('¿Eliminar este borrador? No queda una reversa.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa fa-times-circle"></i> Eliminar borrador</button>
                        <p class="text-muted small mb-0 mt-2">El borrador no entró al stock y no se imprime. Se borra. No se arma una reversa.</p>
                    </form>
                @endif
                @if ($movimiento->reversa)
                    <div class="border rounded p-3 mt-3">
                        <p class="mb-1">Revertido por el movimiento
                            <a class="text-primary" href="{{ route('editar_tesoreria_crown_mesa', $movimiento->reversa->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($movimiento->reversa->numero) }}</a>.
                        </p>
                        @if ($movimiento->reversa->motivo)
                            <p class="mb-0">Motivo: {{ $movimiento->reversa->motivo }}</p>
                        @endif
                    </div>
                @elseif ($movimiento->revierte)
                    <div class="border rounded p-3 mt-3">
                        <p class="mb-1">Reversa del movimiento
                            <a class="text-primary" href="{{ route('editar_tesoreria_crown_mesa', $movimiento->revierte->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($movimiento->revierte->numero) }}</a>.
                        </p>
                        @if ($movimiento->motivo)
                            <p class="mb-0">Motivo: {{ $movimiento->motivo }}</p>
                        @endif
                    </div>
                @elseif ($movimiento->puedeRevertir() && $movimiento->jornada->estaAbierta() && can('editar-tesoreria-crown-mesa', false))
                    <form method="POST" action="{{ route('revertir_tesoreria_crown_mesa', $movimiento->id) }}" class="border rounded p-3 mt-3" autocomplete="off" data-enter-campos="1">
                        @csrf
                        <p class="mb-2">Se graba otro movimiento, con el número siguiente y las cantidades al revés. El original queda.</p>
                        <div class="form-group">
                            <label class="control-label text-right pr-2" for="mesa-motivo-reversion">Motivo</label>
                            <textarea name="motivo" id="mesa-motivo-reversion" class="form-control" rows="2" maxlength="500" required>{{ old('motivo') }}</textarea>
                        </div>
                        <div class="form-row">
                            <div class="col-md-3 mb-2"><input type="text" name="firma_usuario" class="form-control" placeholder="Usuario" autocomplete="off" required value="{{ old('firma_usuario') }}"></div>
                            <div class="col-md-3 mb-2"><input type="password" name="firma_password" class="form-control" placeholder="Clave" autocomplete="new-password" required></div>
                            <div class="col-md-3 mb-2"><button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa fa-undo"></i> Revertir</button></div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@include('caja.tesoreria_crown.partials.modal_consulta_cajero')
@endsection
