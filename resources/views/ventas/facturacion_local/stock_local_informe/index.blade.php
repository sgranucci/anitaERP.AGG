@extends("theme.$theme.layout")
@section('titulo')
    Informe stock locales
@endsection

@section('styles')
<style>
    .sli-tabla { font-size: 12px; }
    .sli-tabla th, .sli-tabla td { white-space: nowrap; padding: 3px 5px; }
</style>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Informe de stock del local</h3>
                <div class="card-tools d-flex flex-wrap align-items-center">
                    <a href="{{ route('facturacion_local_informe_stock') }}" class="btn btn-outline-secondary btn-sm mr-1" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                    <a href="{{ route('facturacion_local_stock') }}" class="btn btn-outline-info btn-sm" title="Consulta por artículo">
                        <i class="fa fa-search"></i> Consulta stock
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_informe_stock') }}" class="mb-0" id="form-stock-local-informe">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Equivalente Anita <code>l-stocklocal.c</code>: stock por medida del depósito del local.
                        Por defecto lee el <strong>ERP</strong> (<code>articulo_movimiento</code> del depósito del local).
                        Active el tilde solo si necesita comparar contra Anita Local (Informix).
                    </p>

                    @if (! empty($error))
                        <div class="alert alert-warning">{{ $error }}</div>
                    @endif

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                        $origenAnita = ($filtros['origen'] ?? 'erp') === 'anita';
                    @endphp

                    <div class="form-group row">
                        <label for="local_venta_id" class="{{ $colLabel }} requerido">Local</label>
                        <div class="{{ $colInput }}">
                            <select name="local_venta_id" id="local_venta_id" class="form-control" required>
                                <option value="">— Seleccione —</option>
                                @foreach ($locales as $loc)
                                    <option value="{{ $loc->id }}"
                                        data-deposito="{{ (int) ($loc->anita_deposito ?? 0) }}"
                                        data-deposito-erp="{{ (int) ($loc->deposito_id ?? 0) }}"
                                        @selected((int) ($filtros['local_venta_id'] ?? 0) === (int) $loc->id)>
                                        {{ $loc->codigo }} — {{ $loc->nombre }}
                                        @if ((int) ($loc->deposito_id ?? 0) > 0)
                                            (dep. ERP {{ $loc->deposito_id }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @if ($locales->isEmpty())
                                <small class="form-text text-danger">No hay locales activos. Cree uno en Locales.</small>
                            @endif
                        </div>
                        <label class="{{ $colLabel }}">Origen de datos</label>
                        <div class="{{ $colInput }} pt-2">
                            <div class="custom-control custom-checkbox">
                                <input type="hidden" name="origen_anita" value="0">
                                <input type="checkbox" class="custom-control-input" id="origen_anita"
                                    name="origen_anita" value="1"
                                    @checked($origenAnita)>
                                <label class="custom-control-label" for="origen_anita">
                                    Traer datos de Anita (Informix)
                                </label>
                            </div>
                            <small class="form-text text-muted">Sin tilde = ERP. Con tilde = bridge Anita Local.</small>
                        </div>
                    </div>

                    <div class="form-group row" id="fila-deposito-anita" style="{{ $origenAnita ? '' : 'display:none;' }}">
                        <label for="deposito_anita" class="{{ $colLabel }}">Depósito Anita</label>
                        <div class="{{ $colInput }}">
                            <input type="number" name="deposito_anita" id="deposito_anita" class="form-control"
                                min="1" step="1"
                                value="{{ $filtros['deposito_anita'] ?? $depositoAnita ?? '' }}"
                                placeholder="Vacío = depósito Anita del local">
                            <small class="form-text text-muted">Override opcional del depósito Informix.</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="modo" class="{{ $colLabel }}">Datos a mostrar</label>
                        <div class="{{ $colInput }}">
                            <select name="modo" id="modo" class="form-control">
                                <option value="saldo" @selected(($filtros['modo'] ?? 'saldo') === 'saldo')>Solo saldo</option>
                                <option value="apertura" @selected(($filtros['modo'] ?? '') === 'apertura')>Entrada / venta / saldo</option>
                            </select>
                        </div>
                        <label for="orden" class="{{ $colLabel }}">Orden</label>
                        <div class="{{ $colInput }}">
                            <select name="orden" id="orden" class="form-control">
                                <option value="articulo" @selected(($filtros['orden'] ?? 'articulo') === 'articulo')>Por artículo</option>
                                <option value="categoria" @selected(($filtros['orden'] ?? '') === 'categoria')>Por categoría (agrupación)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }}">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}">
                            <small class="form-text text-muted">Solo aplica en modo entrada/venta/saldo.</small>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }}">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="desde_sku" class="{{ $colLabel }}">Desde SKU</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="desde_sku" id="desde_sku" class="form-control"
                                value="{{ $filtros['desde_sku'] ?? '' }}" autocomplete="off">
                        </div>
                        <label for="hasta_sku" class="{{ $colLabel }}">Hasta SKU</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="hasta_sku" id="hasta_sku" class="form-control"
                                value="{{ $filtros['hasta_sku'] ?? '' }}" autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="desde_color" class="{{ $colLabel }}">Desde color</label>
                        <div class="{{ $colInput }}">
                            <input type="number" name="desde_color" id="desde_color" class="form-control"
                                value="{{ $filtros['desde_color'] ?? '' }}" min="0" step="1">
                        </div>
                        <label for="hasta_color" class="{{ $colLabel }}">Hasta color</label>
                        <div class="{{ $colInput }}">
                            <input type="number" name="hasta_color" id="hasta_color" class="form-control"
                                value="{{ $filtros['hasta_color'] ?? '' }}" min="0" step="1">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Opciones</label>
                        <div class="{{ $colInput }}">
                            <div class="custom-control custom-checkbox">
                                <input type="hidden" name="solo_con_saldo" value="0">
                                <input type="checkbox" class="custom-control-input" id="solo_con_saldo"
                                    name="solo_con_saldo" value="1"
                                    @checked($filtros['solo_con_saldo'] ?? true)>
                                <label class="custom-control-label" for="solo_con_saldo">Solo filas con movimiento / saldo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex flex-wrap align-items-center">
                    <button type="submit" class="btn btn-primary mr-2" id="btn-consultar-sli">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                    @if ($consultado && empty($error))
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_informe_stock_local',
                            'queryparams' => $filtrosQuery,
                            'variant' => 'compact',
                        ])
                    @endif
                </div>
            </form>

            @if ($consultado && empty($error))
                <div class="card-body pt-0">
                    @if (! empty($subtitulo))
                        <p class="text-muted small mb-2">{{ $subtitulo }}</p>
                    @endif
                    <div class="mb-2">
                        <span class="badge badge-info">Filas: {{ (int) ($totales['total_filas'] ?? 0) }}</span>
                        <span class="badge badge-secondary">Grupos art./color: {{ (int) ($totales['total_grupos'] ?? 0) }}</span>
                        <span class="badge badge-success">
                            Stock total: {{ number_format((float) ($totales['total_stock'] ?? 0), 0, ',', '.') }}
                        </span>
                        @if (! empty($totales['origen']))
                            <span class="badge badge-light">Origen: {{ $totales['origen'] }}</span>
                        @endif
                    </div>
                    <div class="table-responsive sli-tabla">
                        @include('ventas.facturacion_local.stock_local_informe.partials.tabla_datos', [
                            'medidas' => $medidas,
                            'filas' => $filas,
                            'puede_ver_articulo' => $puede_ver_articulo ?? false,
                            'table_class' => 'table table-sm table-bordered table-striped mb-0 sli-tabla',
                        ])
                    </div>
                    @if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-2 d-flex justify-content-between align-items-center flex-wrap">
                            <div class="text-muted small">
                                @if ($filas->total() > 0)
                                    {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                                @endif
                            </div>
                            <div>{{ $filas->links() }}</div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'sli-overlay',
    'tituloId' => 'sli-overlay-titulo',
    'subtituloId' => 'sli-overlay-subtitulo',
    'titulo' => 'Consultando stock del local…',
    'subtitulo' => 'Lee el ERP (o Anita si activó el tilde). Puede demorar según el rango. Pulse Esc para ocultar el aviso.',
])
@endsection

@section('scripts')
<script>
(function () {
    var form = document.getElementById('form-stock-local-informe');
    var overlay = document.getElementById('sli-overlay');
    var selectLocal = document.getElementById('local_venta_id');
    var inputDep = document.getElementById('deposito_anita');
    var checkAnita = document.getElementById('origen_anita');
    var filaAnita = document.getElementById('fila-deposito-anita');

    function toggleAnita() {
        var on = checkAnita && checkAnita.checked;
        if (filaAnita) {
            filaAnita.style.display = on ? '' : 'none';
        }
    }

    function mostrarOverlay(titulo) {
        if (!overlay) return;
        if (titulo) {
            var t = document.getElementById('sli-overlay-titulo');
            if (t) t.textContent = titulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    function ocultarOverlay() {
        if (!overlay) return;
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    if (checkAnita) {
        checkAnita.addEventListener('change', toggleAnita);
        toggleAnita();
    }

    if (selectLocal && inputDep) {
        selectLocal.addEventListener('change', function () {
            var opt = selectLocal.options[selectLocal.selectedIndex];
            var dep = opt ? parseInt(opt.getAttribute('data-deposito') || '0', 10) : 0;
            if (!inputDep.value && dep > 0) {
                inputDep.value = dep;
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) {
                return;
            }
            var msg = (checkAnita && checkAnita.checked)
                ? 'Consultando Anita Local…'
                : 'Consultando stock ERP…';
            mostrarOverlay(msg);
        });
    }

    document.querySelectorAll('a[href*="listar-informe-stock-local"]').forEach(function (a) {
        a.addEventListener('click', function () {
            mostrarOverlay('Exportando…');
        });
    });

    window.addEventListener('pageshow', ocultarOverlay);
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') ocultarOverlay();
    });
    window.addEventListener('focus', function () {
        setTimeout(ocultarOverlay, 800);
    }, { once: true });
})();
</script>
@endsection
