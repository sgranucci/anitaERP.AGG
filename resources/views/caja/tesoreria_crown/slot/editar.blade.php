@extends("theme.$theme.layout")

@section('titulo')
    Recuento de slots
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
@endsection

@section('scripts')
<script>
(function () {
    function texto(numero) {
        return numero.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    var panel = document.querySelector('.js-panel-billetes');
    if (panel) {
        function recalcular() {
            var total = 0;
            panel.querySelectorAll('input[data-denom]').forEach(function (input) {
                var cantidad = parseFloat(String(input.value).replace(',', '.'));
                var denominacion = parseFloat(input.getAttribute('data-denom'));
                if (!isNaN(cantidad) && !isNaN(denominacion)) {
                    total += cantidad * denominacion;
                }
            });
            panel.querySelectorAll('.js-total-billetes').forEach(function (nodo) {
                nodo.textContent = texto(total);
            });
        }
        recalcular();
        panel.addEventListener('input', recalcular);
    }
    var primera = document.querySelector('#form-slot-crown input[data-denom]:not([disabled])');
    if (primera) {
        window.setTimeout(function () { primera.focus(); }, 0);
    }
})();
</script>
@endsection

@section('contenido')
@include('includes.mensaje')
@if ($slot->jornada && $escritorio)
    @include('caja.tesoreria_crown.jornada.partials.desk_nav', [
        'jornada' => $slot->jornada,
        'navActivo' => 'slots',
        'puedeMesas' => $escritorio['puedeMesas'],
        'puedeBoveda' => $escritorio['puedeBoveda'],
        'puedeSlots' => $escritorio['puedeSlots'],
        'puedePoder' => $escritorio['puedePoder'],
        'puedeAnexo' => $escritorio['puedeAnexo'],
        'puedeKardex' => $escritorio['puedeKardex'] ?? false,
        'navAsignaciones' => $escritorio['asignaciones'],
        'navMesas' => $escritorio['mesas'],
        'navBorradoresMesa' => $escritorio['borradoresMesa'],
        'navBoveda' => $escritorio['movimientosBoveda'],
        'navSlots' => $escritorio['slots'],
    ])
@endif
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    {{ \App\Models\Caja\TesoreriaCrownSlot::tipo($slot->tipo) }}
                    {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($slot->numero) }}
                    · {{ $slot->maquina }} {{ $slot->maquina_nombre }}
                    · {{ \App\Models\Caja\TesoreriaCrownSlot::estado($slot->estado) }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('ver_tesoreria_crown_jornada', $slot->jornada_id) }}?panel=slots" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al escritorio
                    </a>
                </div>
            </div>
            <div class="card-body">
                @include('includes.form-error')
                @if ($slot->esDrop())
                    <div class="alert alert-info">El drop viene de Anita. Acá solo se consulta.</div>
                @endif
                <form method="POST" action="{{ route('guardar_tesoreria_crown_slot', $slot->id) }}" id="form-slot-crown" data-enter-campos="1">
                    @csrf
                    @if ($editable)
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="maquina">Máquina</label>
                                <input type="text" name="maquina" id="maquina" class="form-control" maxlength="10" required value="{{ old('maquina', $slot->maquina) }}">
                            </div>
                            <div class="form-group col-md-5">
                                <label for="maquina_nombre">Nombre</label>
                                <input type="text" name="maquina_nombre" id="maquina_nombre" class="form-control" maxlength="30" value="{{ old('maquina_nombre', $slot->maquina_nombre) }}">
                            </div>
                        </div>
                    @endif
                    <div class="card card-outline card-info js-panel-billetes mb-3">
                        <div class="card-header py-2 d-flex justify-content-between">
                            <h3 class="card-title mb-0">Billetes</h3>
                            <strong class="js-total-billetes">{{ number_format((float) $slot->total, 2, ',', '.') }}</strong>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th class="text-right">Denominación</th>
                                        <th style="width:120px;">Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if ($editable)
                                        @foreach ($billetes as $valor)
                                            @php $cantidad = $cantidades[$valor->id] ?? null; @endphp
                                            <tr>
                                                <td class="text-right">{{ number_format((float) $valor->denominacion, 0, ',', '.') }}</td>
                                                <td>
                                                    <input type="number" name="cantidad[{{ $valor->id }}]" class="form-control form-control-sm text-right" min="0" step="1" inputmode="numeric" data-denom="{{ (float) $valor->denominacion }}" value="{{ old('cantidad.'.$valor->id, $cantidad === null || $cantidad === '' ? '' : (int) $cantidad) }}">
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        @forelse ($slot->lineas as $linea)
                                            <tr>
                                                <td class="text-right">{{ $linea->valor->tipoValor->codigo ?? '' }} {{ number_format((float) $linea->valor->denominacion, 0, ',', '.') }}</td>
                                                <td>{{ number_format((float) $linea->cantidad, 0, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="2" class="text-muted">Sin billetes cargados.</td></tr>
                                        @endforelse
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if ($editable && can('crear-tesoreria-crown-slot', false))
                        <button type="submit" class="btn btn-primary">Guardar borrador</button>
                    @endif
                    @if ($editable && can('editar-tesoreria-crown-slot', false))
                        <div class="border rounded p-3 mt-3">
                            <p class="mb-2">Confirmar fija el recuento del día. Firmá con usuario y clave de tesorería.</p>
                            <div class="form-row">
                                <div class="col-md-3 mb-2"><input type="text" name="firma_usuario" class="form-control" placeholder="Usuario" autocomplete="off" value="{{ old('firma_usuario') }}"></div>
                                <div class="col-md-3 mb-2"><input type="password" name="firma_password" class="form-control" placeholder="Clave" autocomplete="off"></div>
                                <div class="col-md-3 mb-2">
                                    <button type="submit" class="btn btn-success btn-sm" formaction="{{ route('confirmar_tesoreria_crown_slot', $slot->id) }}">Confirmar</button>
                                </div>
                            </div>
                        </div>
                    @endif
                </form>
                @if ($slot->esBorrador() && $slot->jornada->estaAbierta() && can('eliminar-borrador-tesoreria-crown-slot', false))
                    <form method="POST" action="{{ route('eliminar_tesoreria_crown_slot', $slot->id) }}" class="mt-3" onsubmit="return confirm('¿Eliminar este borrador?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa fa-times-circle"></i> Eliminar borrador</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
