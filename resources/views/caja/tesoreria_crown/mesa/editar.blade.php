@extends("theme.$theme.layout")

@section('titulo')
    Movimiento de mesa
@endsection

@section('scripts')
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
})();
</script>
@endsection

@section('contenido')
@include('includes.mensaje')
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
                        <i class="fa fa-reply-all"></i> Volver a la jornada
                    </a>
                </div>
            </div>
            <div class="card-body">
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
                <form method="POST" action="{{ route('guardar_tesoreria_crown_mesa', $movimiento->id) }}">
                    @csrf
                    @if ($editable)
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="cajero_numero">Cajero</label>
                                <input type="text" name="cajero_numero" id="cajero_numero" class="form-control" inputmode="numeric" value="{{ old('cajero_numero', $movimiento->cajero->codigo ?? '') }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="comentario">Comentario</label>
                                <input type="text" name="comentario" id="comentario" class="form-control" maxlength="80" value="{{ old('comentario', $movimiento->comentario) }}">
                            </div>
                        </div>
                    @elseif ($movimiento->comentario)
                        <p class="text-muted">{{ $movimiento->comentario }}</p>
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
                                                                <input type="number" name="cantidad[{{ $valor->id }}]" class="form-control form-control-sm text-right" min="0" step="1" data-denom="{{ (float) $valor->denominacion }}" value="{{ old('cantidad.'.$valor->id, $cantidad) }}">
                                                            @elseif ($cantidad !== null && (float) $cantidad > 0)
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
                        <button type="submit" class="btn btn-primary">Guardar borrador</button>
                    @endif
                </form>
                @if ($editable && can('editar-tesoreria-crown-mesa', false))
                    <form method="POST" action="{{ route('confirmar_tesoreria_crown_mesa', $movimiento->id) }}" class="border rounded p-3 mt-3" autocomplete="off">
                        @csrf
                        <p class="mb-2">Confirmar fija la banca de esta fecha. Un cambio posterior de banca no reescribe este movimiento.</p>
                        <div class="form-row">
                            <div class="col-md-3 mb-2"><input type="text" name="firma_usuario" class="form-control" placeholder="Usuario" autocomplete="off" required></div>
                            <div class="col-md-3 mb-2"><input type="password" name="firma_password" class="form-control" placeholder="Clave" autocomplete="new-password" required></div>
                            <div class="col-md-3 mb-2"><button type="submit" class="btn btn-success">Confirmar</button></div>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('eliminar_tesoreria_crown_mesa', $movimiento->id) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este borrador?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar borrador</button>
                    </form>
                @endif
                @if ($movimiento->estado === 'C' && $movimiento->jornada->estaAbierta() && can('editar-tesoreria-crown-mesa', false))
                    <form method="POST" action="{{ route('anular_tesoreria_crown_mesa', $movimiento->id) }}" class="border rounded p-3 mt-3" autocomplete="off">
                        @csrf
                        <p class="mb-2">Anular lo saca del stock. El número queda.</p>
                        <div class="form-row">
                            <div class="col-md-3 mb-2"><input type="text" name="firma_usuario" class="form-control" placeholder="Usuario" autocomplete="off" required></div>
                            <div class="col-md-3 mb-2"><input type="password" name="firma_password" class="form-control" placeholder="Clave" autocomplete="new-password" required></div>
                            <div class="col-md-3 mb-2"><button type="submit" class="btn btn-outline-danger">Anular</button></div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
