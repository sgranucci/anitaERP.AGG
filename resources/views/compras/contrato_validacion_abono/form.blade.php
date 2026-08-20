@extends("theme.$theme.layout")
@section('titulo')
Validación de Abono / Servicio
@endsection

@section('contenido')
@php
    $ocNumero = $oc->numeroordencompra ?? $oc->id ?? '—';
    $proveedor = optional($oc->proveedores ?? null)->nombre
        ?? optional(optional($validacion->recepcion_proveedores)->proveedores)->nombre
        ?? optional(optional($validacion->comprobante_proveedores)->proveedores)->nombre
        ?? '—';
    $preguntas = optional($validacion->plantillas)->preguntas ?? collect();
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-check-square-o"></i>
                    Validación de Abono / Servicio
                </h3>
                <div class="card-tools">
                    <a href="{{ $volverUrl }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i>
                        {{ $origen === 'factura' ? 'Volver a la factura' : 'Volver a la recepción' }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="text-muted small">OC N°</div>
                        <div class="h5 mb-0">{{ $ocNumero }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Proveedor</div>
                        <div class="h5 mb-0">{{ $proveedor }}</div>
                    </div>
                    <div class="col-md-5">
                        <div class="text-muted small">Ítem</div>
                        <div>{{ $itemTxt }}</div>
                    </div>
                </div>
                <p class="mb-3">
                    <span class="text-muted">Período facturado:</span>
                    <strong>{{ $periodoEtiqueta }}</strong>
                </p>

                @if ($validacion->estaCompleta())
                    <div class="alert alert-success">
                        Validación completa
                        @if ($validacion->usuarios)
                            — respondida por {{ $validacion->usuarios->nombre }}
                        @endif
                        @if ($validacion->confirmado_at)
                            el {{ $validacion->confirmado_at->format('d/m/Y H:i') }}
                        @endif
                    </div>
                @else
                    <div class="alert alert-warning">
                        Esta validación la completa el área que recibió el servicio (o Seguridad, para ingresos de planta) — no Compras.
                        Hasta que esté completa no se puede confirmar la recepción ni contabilizar la factura.
                    </div>
                @endif

                <form action="{{ $guardarUrl }}" method="POST" id="form-validacion-abono" autocomplete="off">
                    @csrf
                    @foreach ($preguntas as $pregunta)
                        @php
                            $pid = (int) $pregunta->id;
                            $valor = old('respuestas.'.$pid.'.valor', $respuestas[$pid]['valor'] ?? '');
                            $comentario = old('respuestas.'.$pid.'.comentario', $respuestas[$pid]['comentario'] ?? '');
                            $exige = strtolower((string) ($pregunta->comentario_si_valor ?? 'no'));
                        @endphp
                        <div class="border rounded p-3 mb-3 js-pregunta-abono" data-exige-comentario="{{ $exige }}">
                            <p class="mb-2 font-weight-bold">{{ $pregunta->orden }}. {{ $pregunta->enunciado }}</p>
                            @if ($pregunta->es_tickets)
                                <p class="small text-muted mb-2">
                                    Dato automático (módulo de seguridad): pendiente de P1.
                                    En P0 se responde a mano. Si el contrato exige ingresos, «No» impide confirmar.
                                </p>
                            @endif
                            <div class="form-check form-check-inline">
                                <input class="form-check-input js-valor-abono" type="radio"
                                    name="respuestas[{{ $pid }}][valor]" id="preg_{{ $pid }}_si" value="si"
                                    {{ $valor === 'si' ? 'checked' : '' }}
                                    {{ $puedeCompletar ? '' : 'disabled' }}>
                                <label class="form-check-label" for="preg_{{ $pid }}_si">Sí</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input js-valor-abono" type="radio"
                                    name="respuestas[{{ $pid }}][valor]" id="preg_{{ $pid }}_no" value="no"
                                    {{ $valor === 'no' ? 'checked' : '' }}
                                    {{ $puedeCompletar ? '' : 'disabled' }}>
                                <label class="form-check-label" for="preg_{{ $pid }}_no">No</label>
                            </div>
                            <div class="js-comentario-abono mt-2" style="display:none;">
                                <label class="small mb-1">Comentario obligatorio</label>
                                <textarea name="respuestas[{{ $pid }}][comentario]" class="form-control" rows="2"
                                    {{ $puedeCompletar ? '' : 'readonly' }}>{{ $comentario }}</textarea>
                            </div>
                        </div>
                    @endforeach

                    @if ($puedeCompletar)
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-check"></i> Confirmar validación
                        </button>
                    @elseif (! $validacion->estaCompleta())
                        <p class="text-muted mb-0">
                            Solo el responsable del contrato, el área con permiso
                            <code>completar-validacion-abono</code> o un override gerencial pueden confirmar.
                        </p>
                    @endif
                    <a href="{{ $volverUrl }}" class="btn btn-default">Volver</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    function syncComentario(bloque) {
        var exige = (bloque.getAttribute('data-exige-comentario') || 'no').toLowerCase();
        var checked = bloque.querySelector('.js-valor-abono:checked');
        var box = bloque.querySelector('.js-comentario-abono');
        if (!box) return;
        box.style.display = (checked && checked.value === exige) ? '' : 'none';
    }
    document.querySelectorAll('.js-pregunta-abono').forEach(function (bloque) {
        syncComentario(bloque);
        bloque.querySelectorAll('.js-valor-abono').forEach(function (radio) {
            radio.addEventListener('change', function () { syncComentario(bloque); });
        });
    });
})();
</script>
@endsection
