@php
    $gruposMedio = \App\Support\Caja\TesoreriaCrownRendicionCampos::gruposParaTarea($comprobante->tipoTareaOperativa());
    $calculaRecargo = ! \App\Support\Caja\TesoreriaCrownRendicionCampos::esCajaGastroHotel($comprobante->tipoTareaOperativa());
    $divisorRecargo = \App\Support\Caja\TesoreriaCrownCierreCalculo::divisorRecargo($comprobante->jornada->fecha->format('Y-m-d'));
    $esGastroHotel = \App\Support\Caja\TesoreriaCrownRendicionCampos::esCajaGastroHotel($comprobante->tipoTareaOperativa());
@endphp
@if ($comprobante->tipo === \App\Support\Caja\TesoreriaCrownComprobanteSupport::RENDICION
    && $esGastroHotel)
    @include('caja.tesoreria_crown.movimiento.partials.cierres_fiscales')
@endif
@foreach ($gruposMedio as $grupoMedio)
    <div class="card card-outline card-info mt-2 mb-0">
        <div class="card-header py-1 px-3 d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0" style="font-size:.95rem;">{{ $grupoMedio['titulo'] }}</h3>
            @if ($esGastroHotel)
                <span class="text-muted small d-none d-md-inline">{{ $grupoMedio['ayuda'] }}</span>
            @endif
        </div>
        <div class="card-body py-2 px-3">
            @if (! $esGastroHotel)
                <p class="text-muted small mb-2">{{ $grupoMedio['ayuda'] }}</p>
            @endif
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
                        $col = $esGastroHotel ? 'col-6 col-md-4 col-lg-2' : 'col-sm-6 col-lg-3';
                    @endphp
                    <div class="{{ $col }} mb-2">
                        <label class="small mb-0" for="medio-{{ $clave }}">{{ $meta['label'] }}</label>
                        @if ($editable)
                            <input
                                id="medio-{{ $clave }}"
                                type="{{ $meta['tipo'] === 'texto' ? 'text' : ($esImporte ? 'text' : 'number') }}"
                                name="medio[{{ $clave }}]"
                                class="form-control form-control-sm text-right js-importe{{ $esImporte ? ' js-miles' : '' }}"
                                inputmode="{{ $esImporte ? 'decimal' : ($meta['tipo'] === 'entero' ? 'numeric' : 'text') }}"
                                @if ($meta['tipo'] === 'entero') step="1" min="0" @endif
                                @if ($meta['tipo'] === 'texto') maxlength="20" @endif
                                @if (in_array($clave, ['tarjeta_credito', 'tarjeta_debito', 'total_qr'], true))
                                    data-total-final="1"
                                @elseif ($clave === 'recargo_fin')
                                    data-total-final="-1"
                                    @if ($calculaRecargo) readonly data-recargo-divisor="{{ $divisorRecargo }}" title="Sale de la tarjeta de crédito" @endif
                                @endif
                                value="{{ $vacio ? '' : ($esImporte ? number_format((float) $valorCampo, 2, ',', '.') : $valorCampo) }}"
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
