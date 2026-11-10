@extends("theme.$theme.layout")
@section('titulo')
    Informe flash
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'informe-flash-overlay',
    'tituloId' => 'informe-flash-titulo',
    'subtituloId' => 'informe-flash-subtitulo',
    'titulo' => 'Armando el informe…',
    'subtitulo' => 'El mes acumulado recorre cada día. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title mb-0">Informe flash</h3>
                <div class="card-tools">
                    <a href="{{ route('flash_crown') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Flash diario
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form id="form-informe-flash" method="get" action="{{ route('flash_reporte_crown') }}" class="form-horizontal" autocomplete="off">
                    @csrf
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2" for="fecha">Día</label>
                        <div class="col-lg-2">
                            <input type="date" name="fecha" id="fecha" class="form-control form-control-sm" required value="{{ $fecha }}">
                        </div>
                        <label class="col-lg-2 control-label text-right pr-2" for="formato">Formato</label>
                        <div class="col-lg-3">
                            <select name="formato" id="formato" class="form-control form-control-sm">
                                @foreach ($formatos as $clave => $etiqueta)
                                    <option value="{{ $clave }}" {{ $formato === $clave ? 'selected' : '' }}>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2" for="destinatarios">Destinatarios</label>
                        <div class="col-lg-8">
                            <textarea name="destinatarios" id="destinatarios" class="form-control form-control-sm" rows="6" required>{{ $destinatarios }}</textarea>
                            <small class="text-muted">Un mail por línea. El envío lo dispara esta pantalla: no sale solo.</small>
                        </div>
                    </div>
                    <div class="form-group row mb-0">
                        <div class="col-lg-8 offset-lg-2">
                            <button type="submit" class="btn btn-primary btn-sm">Vista previa</button>
                            @if ($puedeEnviar)
                                <button type="submit"
                                        class="btn btn-success btn-sm"
                                        form="form-informe-flash"
                                        formaction="{{ route('enviar_flash_reporte_crown') }}"
                                        formmethod="post"
                                        onclick="return confirm('¿Enviar este informe a todos los destinatarios?');">
                                    Enviar
                                </button>
                                <button type="submit"
                                        class="btn btn-outline-secondary btn-sm"
                                        form="form-informe-flash"
                                        formaction="{{ route('guardar_destinatarios_flash_reporte_crown') }}"
                                        formmethod="post">
                                    Guardar destinatarios
                                </button>
                            @endif
                        </div>
                    </div>
                </form>

                <p class="text-muted small mt-3 mb-0">
                    Gaming revenues: la primera columna es el día pedido.
                    La segunda es el mismo día de la semana del año anterior, el más cercano, sin salir de ese año.
                    Las otras dos son el acumulado del mes y el acumulado del mismo mes del año anterior.
                    El total de cada casino es mesas + slots + A&amp;B.
                </p>

                @if ($informe)
                    <hr>
                    <h5 class="mb-2">
                        @if ($formato === 'gaming')
                            Gaming revenues {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                            <small class="text-muted">
                                comparativo {{ $informe['fecha_comparable']->format('d/m/Y') }}
                                ({{ $informe['columnas'][1]['titulo'] }})
                            </small>
                        @else
                            Flash completo {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                        @endif
                    </h5>
                    <div class="table-responsive">
                        @if ($formato === 'gaming')
                            @include('caja.flash_crown.reporte.partials.tabla_gaming', ['informe' => $informe])
                        @else
                            @include('caja.flash_crown.reporte.partials.tabla_completo', ['informe' => $informe])
                        @endif
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
    var form = document.getElementById('form-informe-flash');
    var overlay = document.getElementById('informe-flash-overlay');
    if (!form || !overlay) {
        return;
    }
    function mostrar(titulo) {
        var nodo = document.getElementById('informe-flash-titulo');
        if (nodo && titulo) {
            nodo.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultar() {
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }
    form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) {
            return;
        }
        var boton = e.submitter;
        var accion = boton && boton.getAttribute('formaction') ? boton.textContent.trim() : 'Vista previa';
        if (accion.indexOf('Guardar') === 0) {
            return;
        }
        mostrar(accion.indexOf('Enviar') === 0 ? 'Enviando el informe…' : 'Armando la vista previa…');
    });
    window.addEventListener('pageshow', ocultar);
})();
</script>
@endsection
