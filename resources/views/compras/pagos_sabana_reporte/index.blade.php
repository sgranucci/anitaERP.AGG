@extends("theme.$theme.layout")
@section('titulo')
    Pagos (sábana)
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/pagos_sabana_reporte/filtro.js') }}"></script>
@endsection

@section('contenido')
@php
    use App\Support\Compras\PagosSabanaReporteFiltros as Filtros;

    $colLabel = 'col-lg-2 control-label text-right pr-2';
    $colInput = 'col-lg-4';
    $totales = $resultado['totales'] ?? [];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Pagos x Fecha de Movimiento (s&aacute;bana)</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('reporte_pagos_sabana') }}" class="btn btn-outline-secondary btn-sm"
                        title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="get" action="{{ route('reporte_pagos_sabana') }}" id="form-pagos-sabana" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Listado tipo s&aacute;bana de pagos del per&iacute;odo (formato Anita <em>l-movim</em>):
                        OPP/OPA de proveedores, OPP/OPA desde Ingresos y Egresos (solicitud de pago u otros conceptos).
                        Las columnas de medios de pago se muestran solo si hay movimiento en el rango.
                        Datos le&iacute;dos desde anitaERP (sin bridge Anita).
                    </p>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'pagos_sabana_compras',
                        'mostrar_consolidar' => true,
                        'col_label' => 'col-lg-2 col-form-label text-right pr-2',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }} requerido">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}" required>
                        </div>
                    </div>

                    @if (! empty($anita_habilitada))
                        <div class="form-group row">
                            <div class="col-lg-2"></div>
                            <div class="col-lg-10">
                                <div class="custom-control custom-checkbox">
                                    <input type="hidden" name="incluir_anita" value="0">
                                    <input type="checkbox" class="custom-control-input" name="incluir_anita"
                                           id="incluir_anita" value="1"
                                           {{ ! empty($filtros['incluir_anita']) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="incluir_anita">
                                        Incluir pagos Anita (bridge: 1 lectura <code>pago</code> + 1 <code>auxpag</code>)
                                        <span class="text-muted">— temporal hasta completar el circuito en ERP</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-pagos-sabana">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado)
                <div class="card-body pt-0">
                    @if (! empty($subtitulo))
                        <p class="text-muted small mb-2">{{ $subtitulo }}</p>
                    @endif

                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <span class="badge badge-info mr-1">Registros: {{ (int) ($totales['cantidad'] ?? 0) }}</span>
                        <span class="badge badge-secondary mr-1">
                            Total pago: {{ number_format((float) ($totales['total_pago'] ?? 0), 2, ',', '.') }}
                        </span>
                        @if (($resultado['fuente'] ?? '') === 'anita')
                            <span class="badge badge-warning mr-1" title="Fuente Anita che_ban">Fuente: Anita</span>
                        @else
                            <span class="badge badge-light border mr-1">Fuente: ERP</span>
                        @endif
                        @foreach ($resultado['anita_errores'] ?? [] as $errAnita)
                            <span class="badge badge-danger mr-1">{{ $errAnita }}</span>
                        @endforeach
                        <div class="ml-auto">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_reporte_pagos_sabana',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                    </div>

                    <div class="table-responsive">
                        @include('compras.pagos_sabana_reporte.partials.tabla_datos', [
                            'filas' => $filasVista ?? [],
                            'columnas' => $columnas ?? [],
                            'totales' => $totales,
                            'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                            'puede_ver_pagoproveedor' => $puede_ver_pagoproveedor ?? false,
                            'puede_ver_ingresoegreso' => $puede_ver_ingresoegreso ?? false,
                            'puede_ver_comprobante' => $puede_ver_comprobante ?? false,
                            'puede_ver_ordencompra' => $puede_ver_ordencompra ?? false,
                            'puede_ver_solicitudpago' => $puede_ver_solicitudpago ?? false,
                        ])
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="d-flex flex-wrap justify-content-between align-items-center mt-2">
                            <div class="text-muted small">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}&ndash;{{ $filas->lastItem() }}
                                    de {{ $filas->total() }}
                                @endif
                            </div>
                            {{ $filas->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'pagos-sabana-overlay',
    'tituloId' => 'pagos-sabana-titulo',
    'subtituloId' => 'pagos-sabana-subtitulo',
    'titulo' => 'Consultando pagos…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
@endsection
