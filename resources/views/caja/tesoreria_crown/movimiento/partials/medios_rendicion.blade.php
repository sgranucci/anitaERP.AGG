@php
    $gruposMedio = \App\Support\Caja\TesoreriaCrownRendicionCampos::gruposParaTarea($comprobante->asignacion->tipo_tarea ?? '');
@endphp
@if ($comprobante->tipo === \App\Support\Caja\TesoreriaCrownComprobanteSupport::RENDICION
    && \App\Support\Caja\TesoreriaCrownRendicionCampos::esCajaGastroHotel($comprobante->asignacion->tipo_tarea ?? ''))
    @include('caja.tesoreria_crown.movimiento.partials.cierres_fiscales')
@endif
@foreach ($gruposMedio as $grupoMedio)
    <div class="card card-outline card-info mt-3 mb-0">
        <div class="card-header py-2">
            <h3 class="card-title mb-0">{{ $grupoMedio['titulo'] }}</h3>
        </div>
        <div class="card-body pb-2">
            <p class="text-muted small mb-3">{{ $grupoMedio['ayuda'] }}</p>
            <div class="row">
                @foreach ($grupoMedio['campos'] as $clave => $meta)
                    @php
                        $ingresado = old('medio.'.$clave);
                        $guardado = $comprobante->{$clave};
                        $valorCampo = $ingresado !== null ? $ingresado : $guardado;
                        $vacio = $ingresado === null && ($guardado === null || $guardado === '' || (is_numeric($guardado) && (float) $guardado == 0.0));
                        $esImporte = $meta['tipo'] === 'importe';
                        $mostrado = $vacio
                            ? '—'
                            : ($esImporte ? number_format((float) $valorCampo, 2, ',', '.') : (string) $valorCampo);
                    @endphp
                    <div class="col-sm-6 col-lg-3 mb-3">
                        <label class="small mb-1" for="medio-{{ $clave }}">{{ $meta['label'] }}</label>
                        @if ($editable)
                            <input
                                id="medio-{{ $clave }}"
                                type="{{ $meta['tipo'] === 'texto' ? 'text' : 'number' }}"
                                name="medio[{{ $clave }}]"
                                class="form-control form-control-sm text-right js-importe"
                                @if ($meta['tipo'] !== 'texto') step="{{ $meta['tipo'] === 'entero' ? '1' : '0.01' }}" @endif
                                @if ($meta['tipo'] !== 'texto' && ! $meta['negativo']) min="0" @endif
                                @if ($meta['tipo'] === 'texto') maxlength="20" @endif
                                value="{{ $vacio ? '' : $valorCampo }}"
                                autocomplete="off">
                        @else
                            <div class="form-control form-control-sm text-right bg-light">{{ $mostrado !== '' ? $mostrado : '—' }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endforeach
