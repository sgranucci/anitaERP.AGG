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
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Informe de stock del local</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('facturacion_local_informe_stock') }}" class="btn btn-outline-light btn-sm mr-1" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                    <a href="{{ route('facturacion_local_stock') }}" class="btn btn-outline-light btn-sm" title="Consulta por artículo">
                        <i class="fa fa-search"></i> Consulta stock
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_informe_stock') }}" class="mb-0" id="form-stock-local-informe">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Equivalente Anita <code>l-stocklocal.c</code>: stock por medida del dep&oacute;sito del local.
                        Lee el <strong>ERP</strong> (<code>articulo_movimiento</code> del dep&oacute;sito del local).
                    </p>

                    @if (! empty($error))
                        <div class="alert alert-warning">{{ $error }}</div>
                    @endif

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    <div class="form-group row">
                        <label for="local_venta_id" class="{{ $colLabel }}">Local</label>
                        <div class="{{ $colInput }}">
                            <select name="local_venta_id" id="local_venta_id" class="form-control">
                                <option value="">— Todos —</option>
                                @foreach ($locales as $loc)
                                    <option value="{{ $loc->id }}"
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
                            <small class="form-text text-muted">«Todos» requiere elegir depósito ERP abajo.</small>
                        </div>
                        <label for="deposito_erp_id" class="{{ $colLabel }}">Depósito ERP</label>
                        <div class="{{ $colInput }}">
                            <select name="deposito_erp_id" id="deposito_erp_id" class="form-control">
                                <option value="">— Del local seleccionado —</option>
                                @foreach ($depositosErp ?? [] as $dep)
                                    <option value="{{ $dep->id }}"
                                        @selected((int) ($filtros['deposito_erp_id'] ?? ($depositoErpId ?? 0)) === (int) $dep->id)>
                                        {{ $dep->etiqueta }} (id {{ $dep->id }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">
                                El que va con el local es el depósito asignado en Locales. Acá también están los que tienen movimientos (fábrica). Los códigos 610, 620, 630… repiten la misma sucursal y no se listan.
                            </small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="modo" class="{{ $colLabel }}">Datos a mostrar</label>
                        <div class="{{ $colInput }}">
                            <select name="modo" id="modo" class="form-control">
                                <option value="saldo" @selected(($filtros['modo'] ?? 'saldo') === 'saldo')>Solo saldo</option>
                                <option value="apertura" @selected(($filtros['modo'] ?? '') === 'apertura')>Entrada / venta / saldo</option>
                                <option value="detalle" @selected(($filtros['modo'] ?? '') === 'detalle')>Detalle movimientos (tipo y nro.)</option>
                            </select>
                            <small class="form-text text-muted">Detalle: un renglón por movimiento con tipo y número de comprobante (solo ERP).</small>
                        </div>
                        <label for="orden" class="{{ $colLabel }}">Orden</label>
                        <div class="{{ $colInput }}">
                            <select name="orden" id="orden" class="form-control">
                                <option value="articulo" @selected(($filtros['orden'] ?? 'articulo') === 'articulo')>Por artículo</option>
                                <option value="categoria" @selected(($filtros['orden'] ?? '') === 'categoria')>Por categoría (agrupación)</option>
                            </select>
                        </div>
                    </div>

                    @include('stock.partials.campo_consulta_categoria', [
                        'prefix' => 'sli',
                        'label' => 'Categoría',
                        'inputName' => 'categoria_id',
                        'inputId' => 'categoria_sli',
                        'codigoName' => 'categoria_codigo',
                        'categoriaId' => $filtros['categoria_id'] ?? '',
                        'codigo' => $filtros['categoria_codigo'] ?? '',
                        'nombre' => $filtros['categoria_nombre'] ?? '',
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'siguiente' => '#subcategoria_sli_codigo',
                        'mostrar_editar' => false,
                        'ayuda' => 'Vacío = todas. F1 o lupa consulta; Enter resuelve el código.',
                    ])
                    @include('stock.partials.campo_consulta_subcategoria', [
                        'prefix' => 'sli',
                        'label' => 'Subcategoría',
                        'inputName' => 'subcategoria_id',
                        'inputId' => 'subcategoria_sli',
                        'codigoName' => 'subcategoria_codigo',
                        'subcategoriaId' => $filtros['subcategoria_id'] ?? '',
                        'codigo' => $filtros['subcategoria_codigo'] ?? '',
                        'nombre' => $filtros['subcategoria_nombre'] ?? '',
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'siguiente' => '#fecha_desde',
                        'mostrar_editar' => false,
                        'filtra_categoria' => '#categoria_sli',
                        'ayuda' => 'Vacío = todas. Si hay categoría, la lupa lista las subcategorías de esa categoría.',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }}">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}">
                            <small class="form-text text-muted">Aplica en entrada/venta/saldo y en detalle de movimientos.</small>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }}">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>

                    @include('produccion.partials.campo_consulta_articulo', [
                        'prefix' => 'sli_desde',
                        'label' => 'Desde SKU',
                        'inputName' => 'desde_articulo_id',
                        'codigoName' => 'desde_sku',
                        'articuloId' => '',
                        'codigo' => $filtros['desde_sku'] ?? '',
                        'descripcion' => '',
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'next_focus' => '#articulo_sli_hasta_codigo',
                        'help' => 'Vacío = desde el primero. F1 o lupa consulta por nombre; Enter resuelve SKU.',
                    ])
                    @include('produccion.partials.campo_consulta_articulo', [
                        'prefix' => 'sli_hasta',
                        'label' => 'Hasta SKU',
                        'inputName' => 'hasta_articulo_id',
                        'codigoName' => 'hasta_sku',
                        'articuloId' => '',
                        'codigo' => $filtros['hasta_sku'] ?? '',
                        'descripcion' => '',
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'help' => 'Vacío = hasta el último. F1 o lupa consulta por nombre; Enter resuelve SKU.',
                    ])

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
                        <label for="mventa_id" class="{{ $colLabel }}">Marca</label>
                        <div class="{{ $colInput }}">
                            <select name="mventa_id" id="mventa_id" class="form-control">
                                <option value="">— Todas —</option>
                                @foreach ($mventa_query ?? [] as $marca)
                                    <option value="{{ $marca->id }}"
                                        @selected((int) ($filtros['mventa_id'] ?? 0) === (int) $marca->id)>
                                        {{ $marca->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Vacío = todas las marcas del canal local.</small>
                        </div>
                        <label for="estado_local" class="{{ $colLabel }}">Estado local</label>
                        <div class="{{ $colInput }}">
                            <select name="estado_local" id="estado_local" class="form-control">
                                <option value="ACTIVO" @selected(($filtros['estado_local'] ?? 'ACTIVO') === 'ACTIVO')>Activos</option>
                                <option value="INACTIVO" @selected(($filtros['estado_local'] ?? '') === 'INACTIVO')>Inactivos</option>
                                <option value="TODOS" @selected(($filtros['estado_local'] ?? '') === 'TODOS')>Todos</option>
                            </select>
                            <small class="form-text text-muted">Siempre del canal local. Activos es el predeterminado.</small>
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
                            Stock total: {{ \App\Support\Ventas\FacturacionLocal\StockLocalErpMovimientosSupport::cantidadVisible((float) ($totales['total_stock'] ?? 0), '0') }}
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
    'subtitulo' => 'Lee el ERP. Puede demorar según el rango. Pulse Esc para ocultar el aviso.',
])
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultacategoria')
@include('includes.stock.modalconsultasubcategoria')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/categoria/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/subcategoria/consulta.js') }}" type="text/javascript"></script>
<script>
(function () {
    var form = document.getElementById('form-stock-local-informe');
    var overlay = document.getElementById('sli-overlay');
    var selectLocal = document.getElementById('local_venta_id');
    var selectDepErp = document.getElementById('deposito_erp_id');

    if (typeof jQuery !== 'undefined') {
        jQuery('#consultaarticuloModal').data('articuloCanal', 'LOCAL');
        jQuery('#consultaarticuloModal').data('articuloOcultarPrecio', true);
        if (typeof activa_eventos_consultaarticulo === 'function') {
            activa_eventos_consultaarticulo();
        }
        // F1 en código SKU abre el mismo modal que la lupa.
        jQuery(document)
            .off('keydown.sliSkuF1', '#form-stock-local-informe .tm-articulo-campo .codigoarticulo')
            .on('keydown.sliSkuF1', '#form-stock-local-informe .tm-articulo-campo .codigoarticulo', function (e) {
                if (!(e.key === 'F1' || e.code === 'F1' || e.keyCode === 112)) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                jQuery(this).closest('.tm-articulo-campo').find('.consultaarticulo').first().trigger('click');
            });
        // Enter en Desde: resuelve y avanza a Hasta (copia si vacío).
        jQuery(document)
            .off('keydown.sliSkuEnter', '#articulo_sli_desde_codigo')
            .on('keydown.sliSkuEnter', '#articulo_sli_desde_codigo', function (e) {
                if (e.key !== 'Enter' && e.keyCode !== 13) {
                    return;
                }
                e.preventDefault();
                var $desde = jQuery(this);
                $desde.trigger('change');
                var $hasta = jQuery('#articulo_sli_hasta_codigo');
                if ($hasta.length) {
                    if (!($hasta.val() || '').trim() && ($desde.val() || '').trim()) {
                        $hasta.val($desde.val()).trigger('change');
                    }
                    $hasta.focus();
                }
            });
        // Resolver descripción si ya viene SKU en la URL.
        jQuery('#form-stock-local-informe .tm-articulo-campo .codigoarticulo').each(function () {
            if ((jQuery(this).val() || '').trim() !== '') {
                jQuery(this).trigger('change');
            }
        });
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

    if (selectLocal) {
        selectLocal.addEventListener('change', function () {
            var opt = selectLocal.options[selectLocal.selectedIndex];
            var depErp = opt ? parseInt(opt.getAttribute('data-deposito-erp') || '0', 10) : 0;
            if (selectDepErp && depErp > 0) {
                selectDepErp.value = String(depErp);
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function (ev) {
            if (!form.checkValidity()) {
                return;
            }
            var localVal = selectLocal ? (selectLocal.value || '') : '';
            var depErpVal = selectDepErp ? (selectDepErp.value || '') : '';
            if (!localVal && !depErpVal) {
                ev.preventDefault();
                alert('Elija un local o un depósito ERP.');
                return;
            }
            mostrarOverlay('Consultando stock ERP…');
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
