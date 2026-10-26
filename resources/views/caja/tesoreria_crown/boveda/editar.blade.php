@extends("theme.$theme.layout")

@section('titulo')
    Movimiento de bóveda
@endsection

@section('scripts')
<script>
(function () {
    function texto(numero) {
        return numero.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function recalcular(panel) {
        var total = 0;
        panel.querySelectorAll('input[data-denom]').forEach(function (input) {
            var cantidad = parseFloat(String(input.value).replace(',', '.'));
            var denominacion = parseFloat(input.getAttribute('data-denom'));
            if (!isNaN(cantidad) && !isNaN(denominacion)) {
                total += cantidad * denominacion;
            }
        });
        var escrito = texto(total);
        panel.querySelectorAll('.js-total-valor').forEach(function (nodo) {
            nodo.textContent = escrito;
        });
        var codigo = panel.getAttribute('data-grupo');
        if (codigo) {
            document.querySelectorAll('.js-total-barra[data-grupo="' + codigo + '"]').forEach(function (nodo) {
                nodo.textContent = escrito;
            });
        }
    }
    document.querySelectorAll('.js-panel-valor').forEach(function (panel) {
        recalcular(panel);
        panel.addEventListener('input', function () { recalcular(panel); });
    });
})();
</script>
@endsection

@section('contenido')
@include('includes.mensaje')
@php
    $efecto = \App\Support\Caja\TesoreriaCrownBovedaSupport::efectoTexto((string) ($movimiento->tipo->signo ?? '2'));
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Bóveda {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($movimiento->numero) }}
                    · {{ $movimiento->jornada->fecha->format('d/m/Y') }}
                    — {{ $movimiento->deposito->nombre ?? '' }}
                    — {{ $movimiento->tipo->etiqueta() ?? '' }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_boveda', ['sala_id' => $movimiento->sala_id, 'jornada_id' => $movimiento->jornada_id, 'deposito_id' => $movimiento->deposito_id]) }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a la bóveda
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    Estado: <strong>{{ \App\Support\Caja\TesoreriaCrownBovedaSupport::estado($movimiento->estado) }}</strong>
                    · {{ $efecto }}
                    @if ($movimiento->confirmado_at)
                        · {{ \App\Support\Caja\TesoreriaCrownFirmaSupport::textoFirma($movimiento->usuario, $movimiento->confirmado_at) }}
                    @endif
                </p>
                @if ($movimiento->comprobante)
                    <p>
                        Vinculado a
                        <a class="text-primary" href="{{ route('editar_tesoreria_crown_movimiento', $movimiento->comprobante_id) }}">
                            {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::tipo($movimiento->comprobante->tipo) }}
                            {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($movimiento->comprobante->numero) }}
                        </a>
                    </p>
                @endif

                <form method="POST" action="{{ route('guardar_tesoreria_crown_boveda', $movimiento->id) }}" id="form-cantidades-crown">
                    @csrf
                    @if ($editable)
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="comentario">Comentario</label>
                                <input type="text" name="comentario" id="comentario" class="form-control" maxlength="80" value="{{ old('comentario', $movimiento->comentario) }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="comprobante_tipo">Comprobante</label>
                                <select name="comprobante_tipo" id="comprobante_tipo" class="form-control">
                                    <option value="">Sin vínculo</option>
                                    <option value="H" @selected(old('comprobante_tipo', $movimiento->comprobante->tipo ?? '') === 'H')>Habilitación</option>
                                    <option value="F" @selected(old('comprobante_tipo', $movimiento->comprobante->tipo ?? '') === 'F')>Rendición</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="comprobante_numero">Número</label>
                                <input type="text" name="comprobante_numero" id="comprobante_numero" class="form-control" inputmode="numeric" value="{{ old('comprobante_numero', $movimiento->comprobante ? \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($movimiento->comprobante->numero) : '') }}">
                            </div>
                        </div>
                        <div class="border rounded px-3 py-2 mb-3 bg-light d-flex flex-wrap align-items-center" style="position:sticky;top:3.5rem;z-index:5;">
                            @foreach ($grupos as $grupoBarra)
                                <span class="mr-4 mb-1">
                                    <span class="text-muted">{{ $grupoBarra['titulo'] }}</span>
                                    <strong class="js-total-barra" data-grupo="{{ $grupoBarra['codigo'] }}">0,00</strong>
                                </span>
                            @endforeach
                        </div>
                    @elseif ($movimiento->comentario)
                        <p class="text-muted">{{ $movimiento->comentario }}</p>
                    @endif

                    <div class="row">
                        @foreach ($grupos as $grupo)
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card card-outline card-info h-100 mb-0 js-panel-valor" data-grupo="{{ $grupo['codigo'] }}">
                                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                        <h3 class="card-title mb-0">{{ $grupo['titulo'] }}</h3>
                                        <strong class="js-total-valor">0,00</strong>
                                    </div>
                                    <div class="card-body p-0 table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead style="background:#85C1E9;color:#17202A;">
                                                <tr>
                                                    <th>Denominación</th>
                                                    <th>Tipo</th>
                                                    <th style="width:110px;">Cantidad</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($grupo['filas'] as $valor)
                                                    @php
                                                        $denominacion = (float) $valor->denominacion;
                                                        $denominacionTexto = fmod($denominacion, 1.0) === 0.0
                                                            ? number_format($denominacion, 0, ',', '.')
                                                            : number_format($denominacion, 2, ',', '.');
                                                        $cantidad = $cantidades[$valor->id] ?? null;
                                                    @endphp
                                                    <tr>
                                                        <td class="text-right">{{ $denominacionTexto }}</td>
                                                        <td>{{ $valor->descripcion }}</td>
                                                        <td>
                                                            @if ($editable)
                                                                <input type="number" name="cantidad[{{ $valor->id }}]" class="form-control form-control-sm text-right" min="0" step="1" data-denom="{{ $denominacion }}" value="{{ old('cantidad.'.$valor->id, $cantidad) }}">
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
                    @if ($editable && can('crear-tesoreria-crown-boveda', false))
                        <button type="submit" class="btn btn-primary" id="btn-guardar-cantidades">Guardar borrador</button>
                    @endif
                </form>

                @if ($editable && can('editar-tesoreria-crown-boveda', false))
                    <form method="POST" action="{{ route('confirmar_tesoreria_crown_boveda', $movimiento->id) }}" class="border rounded p-3 mt-3" autocomplete="off">
                        @csrf
                        <p class="mb-2">Confirmar con usuario y clave de tesorería. A partir de ahí el movimiento entra en el saldo.</p>
                        <div class="form-row">
                            <div class="col-md-3 mb-2">
                                <input type="text" name="firma_usuario" class="form-control" placeholder="Usuario" autocomplete="off" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <input type="password" name="firma_password" class="form-control" placeholder="Clave" autocomplete="new-password" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button type="submit" class="btn btn-success">Confirmar</button>
                            </div>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('eliminar_tesoreria_crown_boveda', $movimiento->id) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este borrador?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar borrador</button>
                    </form>
                @endif

                @if ($movimiento->estado === 'C' && $movimiento->jornada->estaAbierta() && can('editar-tesoreria-crown-boveda', false))
                    <form method="POST" action="{{ route('anular_tesoreria_crown_boveda', $movimiento->id) }}" class="border rounded p-3 mt-3" autocomplete="off">
                        @csrf
                        <p class="mb-2">Anular lo saca del saldo. El número queda en la jornada.</p>
                        <div class="form-row">
                            <div class="col-md-3 mb-2">
                                <input type="text" name="firma_usuario" class="form-control" placeholder="Usuario" autocomplete="off" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <input type="password" name="firma_password" class="form-control" placeholder="Clave" autocomplete="new-password" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button type="submit" class="btn btn-outline-danger">Anular</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
