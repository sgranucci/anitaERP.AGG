@extends("theme.$theme.layout")
@section('titulo')
    Control CC vs mayor Anita
@endsection

@section('scripts')
<script>
(function () {
    var form = document.getElementById('form-cc-vs-mayor');
    if (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) return;
            var overlay = document.getElementById('cc-vs-mayor-overlay');
            if (overlay) {
                overlay.classList.remove('d-none');
                overlay.style.display = 'flex';
                overlay.setAttribute('aria-hidden', 'false');
            }
            var btn = document.getElementById('btn-consultar');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Consultando bridge…';
            }
        });
    }
    function mostrarOverlayCcVsMayor() {
        var overlay = document.getElementById('cc-vs-mayor-overlay');
        if (overlay) {
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        }
    }
    function ocultarOverlayCcVsMayor() {
        var overlay = document.getElementById('cc-vs-mayor-overlay');
        if (overlay) {
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        }
        var btn = document.getElementById('btn-consultar');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-search"></i> Consultar';
        }
    }
    window.addEventListener('pageshow', ocultarOverlayCcVsMayor);
    window.addEventListener('pagehide', ocultarOverlayCcVsMayor);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.keyCode === 27) {
            ocultarOverlayCcVsMayor();
        }
    });
    document.querySelectorAll('a[href*="listar-cc-vs-mayor-anita"]').forEach(function (a) {
        a.addEventListener('click', function () {
            var sub = document.getElementById('cc-vs-mayor-subtitulo');
            if (sub) {
                sub.textContent = 'El archivo se descarga al terminar. Pulse Esc para cerrar este aviso.';
            }
            mostrarOverlayCcVsMayor();
            window.addEventListener('focus', ocultarOverlayCcVsMayor, { once: true });
        });
    });
})();
</script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'cc-vs-mayor-overlay',
    'tituloId' => 'cc-vs-mayor-titulo',
    'subtituloId' => 'cc-vs-mayor-subtitulo',
    'titulo' => 'Consultando Anita bridge…',
    'subtitulo' => 'Lee climov, aplmov y subdiario. Puede demorar según el volumen del día.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Control cuenta corriente vs mayor (Anita bridge)</h3>
                <div class="card-tools">
                    <a href="{{ route('cc_vs_mayor_anita') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('cc_vs_mayor_anita') }}" id="form-cc-vs-mayor" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Cruza <code>climov</code> / <code>aplmov</code> (CC) con imputaciones de <code>subdiario</code>
                        a la cuenta deudores, expandiendo <code>subd_cuenta</code> y <code>subd_contrapartida</code>
                        según <code>subd_tipo_mov</code> (regla AnitaSubdiarioMayorSupport).
                        Detecta PV distintos con match flexible por tipo+nro+importe.
                    </p>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right requerido">Fecha</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha" class="form-control" required
                                   value="{{ $filtros['fecha'] ?? '' }}">
                        </div>
                        <label class="col-lg-2 control-label text-right requerido">Cuenta deudores</label>
                        <div class="col-lg-3">
                            <input type="text" name="cuenta_codigo" class="form-control" required
                                   value="{{ $filtros['cuenta_codigo'] ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right">Sistema subdiario</label>
                        <div class="col-lg-3">
                            <select name="sistema_subdiario" class="form-control">
                                <option value="ventas" {{ ($filtros['sistema_subdiario'] ?? '') === 'ventas' ? 'selected' : '' }}>ventas</option>
                                <option value="contab" {{ ($filtros['sistema_subdiario'] ?? '') === 'contab' ? 'selected' : '' }}>contab</option>
                            </select>
                            <small class="text-muted">En El Bierzo el subdiario de ventas vive en <code>ventas</code>.</small>
                        </div>
                        <label class="col-lg-2 control-label text-right">Tolerancia</label>
                        <div class="col-lg-2">
                            <input type="text" name="tolerancia" class="form-control"
                                   value="{{ $filtros['tolerancia'] ?? '0.05' }}">
                        </div>
                        <div class="col-lg-3">
                            <div class="form-check mt-2">
                                <input type="hidden" name="solo_diferencias" value="0">
                                <input class="form-check-input" type="checkbox" name="solo_diferencias" value="1" id="solo_diff"
                                    {{ !empty($filtros['solo_diferencias']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="solo_diff">Solo diferencias</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" id="btn-consultar" class="btn btn-info">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </div>
            </form>
        </div>

        @if ($consultado && $resultado)
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">{{ $titulo }}</h3>
                    <div class="card-tools">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_cc_vs_mayor_anita',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                </div>
                <div class="card-body">
                    @if (!empty($resultado['errores_bridge']))
                        <div class="alert alert-danger">
                            <strong>Bridge Anita:</strong>
                            <ul class="mb-0">
                                @foreach ($resultado['errores_bridge'] as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @include('contable.cc_vs_mayor_anita.partials.resumen')
                    @include('contable.cc_vs_mayor_anita.partials.tabla_datos', [
                        'filas' => $filas,
                        'es_export' => false,
                    ])
                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <span class="text-muted small">
                                {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                            </span>
                            {{ $filas->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
