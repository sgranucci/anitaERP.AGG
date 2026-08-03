@extends("theme.$theme.layout")
@section('titulo')
    IVA ventas
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/ventas/iva_ventas/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">IVA ventas</h3>
                <div class="card-tools">
                    <a href="{{ route('iva_ventas') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('iva_ventas') }}" id="form-iva-ventas" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Listado de ventas con desglose impositivo desde AnitaERP (tablas <code>venta</code> / <code>venta_impuesto</code>).
                        Facturas de administración se muestran en apartado separado. Orden por fecha de movimiento o jornada y tipo de comprobante.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                        $empresasDisponibles = collect($empresa_query ?? []);
                    @endphp

                    <div class="form-group row">
                        <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
                        <div class="{{ $colInput }}">
                            @if ($empresasDisponibles->count() > 1)
                                <select name="empresa_id" id="empresa_id" class="form-control" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($empresasDisponibles as $emp)
                                        <option value="{{ $emp->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id)>
                                            {{ $emp->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif ($empresasDisponibles->count() === 1)
                                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ (int) $empresasDisponibles->first()->id }}">
                                <span class="form-control-plaintext">{{ $empresasDisponibles->first()->nombre }}</span>
                            @else
                                <p class="text-danger small mb-0">Sin empresas asignadas.</p>
                            @endif
                        </div>
                        <label for="moneda_id" class="{{ $colLabel }}">Moneda reporte</label>
                        <div class="{{ $colInput }}">
                            <select name="moneda_id" id="moneda_id" class="form-control">
                                @foreach ($moneda_query ?? [] as $mon)
                                    <option value="{{ $mon->id }}" @selected((int) ($filtros['moneda_id'] ?? 1) === (int) $mon->id)>
                                        {{ $mon->nombre ?? $mon->codigo }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? date('Y-m-01') }}" required>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }} requerido">Hasta</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? date('Y-m-d') }}" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="orden_fecha" class="{{ $colLabel }}">Orden</label>
                        <div class="{{ $colInput }}">
                            <select name="orden_fecha" id="orden_fecha" class="form-control">
                                @foreach ($orden_enum as $value => $label)
                                    <option value="{{ $value }}" @selected(($filtros['orden_fecha'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="subdiario" class="{{ $colLabel }}">Subdiario</label>
                        <div class="{{ $colInput }}">
                            <select name="subdiario" id="subdiario" class="form-control">
                                @foreach ($subdiario_enum as $value => $label)
                                    <option value="{{ $value }}" @selected(($filtros['subdiario'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="{{ $colLabel }} d-none d-lg-block"></div>
                        <div class="{{ $colInput }}">
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="conciliar_contable" id="conciliar_contable" value="1"
                                    @checked($filtros['conciliar_contable'] ?? true)>
                                <label class="form-check-label" for="conciliar_contable">Conciliar contra mayor contable (cuentas ventas e IVA)</label>
                            </div>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="conciliar_por_unidad" id="conciliar_por_unidad" value="1"
                                    @checked($filtros['conciliar_por_unidad'] ?? true)>
                                <label class="form-check-label" for="conciliar_por_unidad">Conciliar por unidad de negocio (Gastronomía / Vending / Estacionamiento)</label>
                            </div>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="clasificar_por_host" id="clasificar_por_host" value="1"
                                    @checked(! empty($filtros['clasificar_por_host']))>
                                <label class="form-check-label" for="clasificar_por_host">Clasificar por host (PC de facturación)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input js-auto-consultar" type="checkbox" name="agrupar_b_por_dia" id="agrupar_b_por_dia" value="1"
                                    @checked(! empty($filtros['agrupar_b_por_dia']))>
                                <label class="form-check-label" for="agrupar_b_por_dia">Unificar Facturas B por día (rango de comprobantes)</label>
                            </div>
                        </div>
                        <div class="{{ $colLabel }} d-none d-lg-block"></div>
                        <div class="{{ $colInput }}">
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="solo_moneda_origen" id="solo_moneda_origen" value="1"
                                    @checked(! empty($filtros['solo_moneda_origen']))>
                                <label class="form-check-label" for="solo_moneda_origen">Convertir moneda extranjera con cotización del comprobante</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input js-auto-consultar" type="checkbox" name="auditar_ctamov" id="auditar_ctamov" value="1"
                                    @checked(! empty($filtros['auditar_ctamov']))>
                                <label class="form-check-label" for="auditar_ctamov">Auditar contra ctamov (Anita) — lee el bridge, puede demorar</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="{{ $colLabel }} d-none d-lg-block"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado ?? false)
                <div class="card-body p-0 border-top">
                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-0 small">
                            <strong>Período:</strong> {{ $periodo_texto ?? '' }}
                            · <strong>Orden:</strong> {{ $orden_texto ?? '' }}
                            · <strong>Subdiario:</strong> {{ $subdiario_texto ?? '' }}
                            @if (! empty($filtros['clasificar_por_host']))
                                · <strong>Host:</strong> clasificado
                            @endif
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_iva_ventas',
                                'queryparams' => array_merge($filtrosQuery ?? [], ['consultar' => 1]),
                            ])
                        </div>
                        @if (! empty($resultado['stats']))
                            <div class="small mb-1 mb-md-0 text-md-right">
                                <span class="text-muted">Comprobantes:</span>
                                <strong>{{ (int) ($resultado['stats']['ventas'] ?? 0) }}</strong>
                                · Puntos de venta <strong>{{ (int) ($resultado['stats']['puntoventa'] ?? 0) }}</strong>
                                · Total <strong>{{ number_format((float) ($resultado['totales_general']['total'] ?? 0), 2, ',', '.') }}</strong>
                                @php $corrStats = $resultado['auditoria_correlatividad'] ?? []; @endphp
                                @if (! empty($corrStats['habilitada']) && (int) ($corrStats['total_saltos'] ?? 0) > 0)
                                    · <span class="text-danger" title="Saltos de numeración detectados">
                                        <i class="fa fa-exclamation-triangle"></i>
                                        {{ (int) ($corrStats['total_faltantes'] ?? 0) }} número(s) con salto
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    @php
                        $statsIva = $resultado['stats'] ?? [];
                        $sinComprobantes = (int) ($statsIva['ventas'] ?? 0) === 0;
                        $exclSubdiario = (int) ($statsIva['excluidas_subdiario'] ?? 0);
                        $ventasPeriodo = (int) ($statsIva['ventas_periodo'] ?? 0);
                    @endphp
                    @if (! empty($subdiario_ajustado))
                        <div class="alert alert-info mx-3 mt-3 mb-0">
                            El subdiario se ampli&oacute; autom&aacute;ticamente a <strong>Ventas A y B</strong>
                            porque el filtro anterior no inclu&iacute;a comprobantes del per&iacute;odo (p. ej. facturas letra A).
                        </div>
                    @elseif ($sinComprobantes && $exclSubdiario > 0 && $ventasPeriodo > 0)
                        <div class="alert alert-warning mx-3 mt-3 mb-0">
                            Hay {{ $exclSubdiario }} comprobante(s) en el per&iacute;odo excluido(s) por el subdiario
                            <strong>{{ $subdiario_texto ?? '' }}</strong>.
                            Pruebe con <strong>Ventas A y B</strong> si factura con letra A o C.
                        </div>
                    @elseif ($sinComprobantes && $ventasPeriodo === 0)
                        <div class="alert alert-info mx-3 mt-3 mb-0">
                            No hay ventas en el per&iacute;odo para la empresa seleccionada
                            (orden: {{ $orden_texto ?? '' }}).
                        </div>
                    @endif

                    @php
                        $empresaSel = collect($empresa_query ?? [])->firstWhere('id', (int) ($filtros['empresa_id'] ?? 0));
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            collect([['nombreempresa' => $empresaSel->nombre ?? '']])
                        );
                    @endphp
                    @if (count($logosVista) > 0)
                        <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    <style>
                        #tabla-paginada thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-paginada thead th { font-weight: 600; border-color: #7fb3d5; }
                        #tabla-paginada tbody tr.iva-ventas-resumen-b { background-color: #fef9e7; font-weight: 600; }
                    </style>
                    <div class="table-responsive border-top">
                        <table id="tabla-paginada" class="table table-striped table-bordered table-hover table-sm mb-0" style="font-size: 0.78rem;">
                            @include('ventas.iva_ventas.partials.tabla_datos', [
                                'resultado' => $resultado,
                                'filas' => $filasVista ?? [],
                                'clasificar_por_host' => ! empty($filtros['clasificar_por_host']),
                                'mostrar_secciones' => true,
                                'puede_ver_venta' => $puede_ver_venta ?? false,
                                'puede_ver_cliente' => $puede_ver_cliente ?? false,
                                'puede_ver_puntoventa' => $puede_ver_puntoventa ?? false,
                                'puede_ver_tipotransaccion' => $puede_ver_tipotransaccion ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between border-top-0">
                            <span class="small text-muted mb-2 mb-md-0">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} {{ ($resultado['agrupado_b_por_dia'] ?? false) ? 'líneas (Facturas B unificadas por día)' : 'comprobantes' }}
                                @else
                                    Sin registros en esta p&aacute;gina
                                    @if ((int) ($statsIva['ventas'] ?? 0) > 0)
                                        (hay {{ (int) $statsIva['ventas'] }} en total; vaya a la p&aacute;gina 1)
                                    @endif
                                @endif
                            </span>
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif

                    @include('ventas.iva_ventas.partials.conciliacion_contable', [
                        'resultado' => $resultado,
                        'puede_ver_puntoventa' => $puede_ver_puntoventa ?? false,
                        'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                        'puede_ver_venta' => $puede_ver_venta ?? false,
                        'puede_ver_asiento' => $puede_ver_asiento ?? false,
                    ])

                    @include('ventas.iva_ventas.partials.conciliacion_unidad_negocio', [
                        'resultado' => $resultado,
                        'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                    ])

                    @include('ventas.iva_ventas.partials.auditoria_diaria', [
                        'resultado' => $resultado,
                    ])

                    @include('ventas.iva_ventas.partials.auditoria_diaria_unidad_negocio', [
                        'resultado' => $resultado,
                        'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                    ])

                    @include('ventas.iva_ventas.partials.auditoria_correlatividad', [
                        'resultado' => $resultado,
                        'puede_ver_puntoventa' => $puede_ver_puntoventa ?? false,
                        'puede_ver_venta' => $puede_ver_venta ?? false,
                    ])

                    @include('ventas.iva_ventas.partials.totales_puntoventa', [
                        'resultado' => $resultado,
                        'puede_ver_puntoventa' => $puede_ver_puntoventa ?? false,
                    ])
                </div>
            @endif
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'iva-ventas-procesando-overlay',
    'tituloId' => 'iva-ventas-procesando-titulo',
    'subtituloId' => 'iva-ventas-procesando-subtitulo',
    'titulo' => 'Calculando IVA ventas…',
    'subtitulo' => 'Puede demorar según el período y la conciliación contable / ctamov. No cierre la página.',
])

<script>
    (function () {
        var overlay = document.getElementById('iva-ventas-procesando-overlay');
        if (!overlay) {
            return;
        }

        function mostrarProcesoOverlay(titulo) {
            if (titulo) {
                var tituloEl = document.getElementById('iva-ventas-procesando-titulo');
                if (tituloEl) {
                    tituloEl.textContent = titulo;
                }
            }
            overlay.classList.remove('d-none');
            overlay.style.display = 'flex';
            overlay.setAttribute('aria-hidden', 'false');
        }

        function ocultarProcesoOverlay() {
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        }

        var form = document.getElementById('form-iva-ventas');
        if (form) {
            form.addEventListener('submit', function () {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                mostrarProcesoOverlay('Calculando IVA ventas…');
            });
        }

        // Exportaciones (PDF / Excel / CSV) tambien navegan: mostrar el aviso.
        document.querySelectorAll('a[href*="listar-iva-ventas"]').forEach(function (a) {
            a.addEventListener('click', function () {
                mostrarProcesoOverlay('Generando exportaci\u00f3n…');
            });
        });

        // Si el usuario vuelve con el boton atras (bfcache), ocultar el aviso.
        window.addEventListener('pageshow', ocultarProcesoOverlay);
    })();
</script>
@endsection
