@php
    $impresora = $sesion['impresora_usuario'] ?? [];
    $programaSeteo = $programaSeteo ?? \App\Support\Ventas\ComprobanteImpresionSalidaUsuarioSupport::programaUnificado();
    $salidaActualId = (int) ($impresora['salida_id'] ?? 0);
    $faltantePapel = ! empty($sesion['faltante_impresora_papel']);
    $urlSetearBase = url('configuracion/setearsalida/'.$programaSeteo);
@endphp
<div class="card card-outline card-info mb-3" id="sesion-mi-impresora">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">Mi impresora</h3>
    </div>
    <div class="card-body py-2">
        <p class="small text-muted mb-2">
            El programa define qué copias salen. El papel va a esta impresora (la tuya).
            El archivo NAS no cambia.
        </p>
        @if ($faltantePapel)
            <div class="alert alert-warning py-2 mb-2" role="alert">
                Hay copias de papel sin impresora. Elegí la tuya antes de imprimir; si no, no se envía a ninguna cola.
            </div>
        @endif
        <div class="form-row align-items-end">
            <div class="form-group col-md-7 mb-2">
                <label for="sesion_salida_id" class="small mb-1">Impresora de esta sesión</label>
                <select id="sesion_salida_id" class="form-control form-control-sm" required>
                    <option value="">Seleccione una impresora…</option>
                    @foreach ($salidasUsuario ?? [] as $salida)
                        <option value="{{ (int) $salida->id }}" {{ $salidaActualId === (int) $salida->id ? 'selected' : '' }}>
                            {{ $salida->nombre }}
                            @if (trim((string) ($salida->ubicacion ?? '')) !== '')
                                — {{ $salida->ubicacion }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-5 mb-2">
                <div class="form-check mb-2">
                    <input type="checkbox" id="sesion_enviar_impresora" class="form-check-input" value="1"
                        {{ ! empty($enviarImpresora ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label small" for="sesion_enviar_impresora">
                        Enviar a impresora
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" id="sesion_disparar_al_grabar" class="form-check-input" value="1"
                        {{ ! empty($impresora['disparar_al_grabar']) ? 'checked' : '' }}>
                    <label class="form-check-label small" for="sesion_disparar_al_grabar">
                        Disparar al grabar la factura
                    </label>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-guardar-impresora-sesion"
                    data-url-setear="{{ $urlSetearBase }}">
                    <i class="fa fa-save"></i> Guardar impresora
                </button>
            </div>
        </div>
        @if (! empty($impresora['nombre']))
            <p class="small mb-0 text-muted">
                Actual:
                <strong>{{ $impresora['nombre'] }}</strong>
                @if (($impresora['ubicacion'] ?? '') !== '')
                    ({{ $impresora['ubicacion'] }})
                @endif
            </p>
        @endif
    </div>
</div>
