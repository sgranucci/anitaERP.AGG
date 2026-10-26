@extends("theme.$theme.layout")

@section('titulo')
    Bóveda
@endsection

@section('contenido')
@include('includes.mensaje')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Bóveda
                    @if ($jornada)
                        · {{ $jornada->fecha->format('d/m/Y') }}
                    @endif
                </h3>
                <div class="card-tools">
                    @if ($jornada)
                        <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-reply-all"></i> Volver a la jornada
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', ['ruta' => 'tesoreria_crown_boveda'])
            <div class="card-body">
                <p class="text-muted">
                    El saldo es la suma de los movimientos confirmados hasta esta jornada. Dólares y euros quedan en su moneda. Un tipo marcado como «no mueve saldo» queda asentado y no cambia la cantidad.
                </p>

                @if (! $jornada)
                    <p>Esta sala no tiene jornada. Abrila para cargar movimientos.</p>
                @else
                    <p>
                        Jornada {{ $jornada->fecha->format('d/m/Y') }}
                        · <strong>{{ $jornada->estaAbierta() ? 'Abierta' : 'Cerrada' }}</strong>
                    </p>

                    @if ($lugares->isEmpty())
                        <div class="alert alert-warning">
                            Esta sala no tiene un depósito de bóveda, pre-depósito o caja chica.
                            <a class="text-primary" href="{{ route('tesoreria_crown_deposito', ['sala_id' => $sala->id]) }}">Cargalo en Depósitos</a>
                            con el tipo Bóveda.
                        </div>
                    @else
                        <div class="mb-3">
                            @foreach ($lugares as $opcion)
                                <a class="btn btn-sm {{ $lugar && (int) $lugar->id === (int) $opcion->id ? 'btn-info' : 'btn-outline-info' }} mr-1 mb-1"
                                   href="{{ route('tesoreria_crown_boveda', ['sala_id' => $sala->id, 'jornada_id' => $jornada->id, 'deposito_id' => $opcion->id]) }}">
                                    {{ $opcion->tipoDeposito->nombre ?? '' }} {{ $opcion->codigo }}
                                </a>
                            @endforeach
                        </div>

                        <h5>Saldo de {{ $lugar->nombre ?? '' }}</h5>
                        @if ($saldo === [])
                            <p class="text-muted">Todavía no hay cantidades confirmadas en este lugar.</p>
                        @else
                            @foreach ($saldo as $grupo)
                                <h6 class="mt-3">{{ $grupo['titulo'] }} · {{ number_format($grupo['monto'], 2, ',', '.') }}</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead style="background:#85C1E9;color:#17202A;">
                                            <tr>
                                                <th>Denominación</th>
                                                <th>Descripción</th>
                                                <th class="text-right">Cantidad</th>
                                                <th class="text-right">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($grupo['filas'] as $fila)
                                                <tr>
                                                    <td class="text-right">{{ number_format((float) $fila['valor']->denominacion, 2, ',', '.') }}</td>
                                                    <td>{{ $fila['valor']->descripcion }}</td>
                                                    <td class="text-right">{{ number_format($fila['cantidad'], 2, ',', '.') }}</td>
                                                    <td class="text-right">{{ number_format($fila['monto'], 2, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endforeach
                        @endif

                        @if ($jornada->estaAbierta() && can('crear-tesoreria-crown-boveda', false) && $lugar)
                            <form method="POST" action="{{ route('crear_tesoreria_crown_boveda') }}" class="border rounded p-3 mt-3" id="form-nueva-boveda">
                                @csrf
                                <input type="hidden" name="jornada_id" value="{{ $jornada->id }}">
                                <input type="hidden" name="deposito_id" value="{{ $lugar->id }}">
                                <p class="mb-2">El movimiento se carga en <strong>{{ $lugar->codigo }} — {{ $lugar->nombre }}</strong>. La abreviatura es la de Anita (INGR, OEGR, PDEP…).</p>
                                <div class="form-row">
                                    <div class="form-group col-md-2">
                                        <label for="tipo_abrev">Tipo</label>
                                        <input type="text" name="tipo_abrev" id="tipo_abrev" class="form-control" maxlength="4" required placeholder="Abrev." autocomplete="off" value="{{ old('tipo_abrev') }}">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="comprobante_tipo">Comprobante</label>
                                        <select name="comprobante_tipo" id="comprobante_tipo" class="form-control">
                                            <option value="">Sin vínculo</option>
                                            <option value="H" @selected(old('comprobante_tipo') === 'H')>Habilitación</option>
                                            <option value="F" @selected(old('comprobante_tipo') === 'F')>Rendición</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label for="comprobante_numero">Número</label>
                                        <input type="text" name="comprobante_numero" id="comprobante_numero" class="form-control" inputmode="numeric" placeholder="Número" value="{{ old('comprobante_numero') }}">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="comentario">Comentario</label>
                                        <input type="text" name="comentario" id="comentario" class="form-control" maxlength="80" value="{{ old('comentario') }}">
                                    </div>
                                    <div class="form-group col-md-2 d-flex align-items-end">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input" id="copiar" name="copiar" value="1" @checked(old('copiar') === '1')>
                                            <label class="custom-control-label" for="copiar">Copiar cantidades</label>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Nuevo movimiento</button>
                                <div class="mt-3" style="max-height:12rem;overflow:auto;">
                                    @foreach ($tipos as $tipo)
                                        <button type="button" class="btn btn-outline-secondary btn-sm mr-1 mb-1 js-abrev-boveda" data-abrev="{{ $tipo->abreviatura }}">
                                            {{ $tipo->abreviatura }} · {{ \App\Support\Caja\TesoreriaCrownBovedaSupport::efectoTexto($tipo->signo) }}
                                        </button>
                                    @endforeach
                                </div>
                            </form>
                        @endif
                    @endif

                    <h5 class="mt-4">Movimientos de la jornada</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Número</th>
                                    <th>Lugar</th>
                                    <th>Tipo</th>
                                    <th>Efecto</th>
                                    <th>Estado</th>
                                    <th>Comprobante</th>
                                    <th>Comentario</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($movimientos as $mov)
                                    <tr>
                                        <td>
                                            <a class="text-primary" href="{{ route('editar_tesoreria_crown_boveda', $mov->id) }}">
                                                {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($mov->numero) }}
                                            </a>
                                        </td>
                                        <td>{{ $mov->deposito->codigo ?? '' }} — {{ $mov->deposito->nombre ?? '' }}</td>
                                        <td>{{ $mov->tipo->etiqueta() ?? '' }}</td>
                                        <td>{{ \App\Support\Caja\TesoreriaCrownBovedaSupport::efectoTexto((string) ($mov->tipo->signo ?? '2')) }}</td>
                                        <td>{{ \App\Support\Caja\TesoreriaCrownBovedaSupport::estado($mov->estado) }}</td>
                                        <td>
                                            @if ($mov->comprobante)
                                                <a class="text-primary" href="{{ route('editar_tesoreria_crown_movimiento', $mov->comprobante_id) }}">
                                                    {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::tipo($mov->comprobante->tipo) }}
                                                    {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($mov->comprobante->numero) }}
                                                </a>
                                            @endif
                                        </td>
                                        <td>{{ $mov->comentario }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">No hay movimientos en esta jornada.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    document.querySelectorAll('.js-abrev-boveda').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var campo = document.getElementById('tipo_abrev');
            if (!campo) {
                return;
            }
            campo.value = boton.getAttribute('data-abrev') || '';
            campo.focus();
        });
    });
})();
</script>
@endsection
