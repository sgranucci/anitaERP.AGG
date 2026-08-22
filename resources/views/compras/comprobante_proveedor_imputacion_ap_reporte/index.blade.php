@extends("theme.$theme.layout")
@section('titulo')
    Comprobantes vs imputaci&oacute;n AP
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/comprobante_proveedor_imputacion_ap_reporte/filtro.js') }}"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'iap-reporte-overlay',
    'tituloId' => 'iap-reporte-overlay-titulo',
    'subtituloId' => 'iap-reporte-overlay-subtitulo',
    'titulo' => 'Comparando comprobantes contra el asiento…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Comprobantes vs imputaci&oacute;n AP (MN / ME / anticipo)</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('reporte_imputacion_ap_proveedor') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_imputacion_ap_proveedor') }}" id="form-imputacion-ap" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Compara cada <strong>comprobante de proveedor</strong>, <strong>OPA</strong> y
                        <strong>aplicaci&oacute;n de anticipo</strong> contra su asiento en
                        proveedores moneda nacional, proveedores moneda extranjera y anticipos.
                        Todos los importes van a <strong>pesos</strong> con la cotizaci&oacute;n de cada operaci&oacute;n.
                        Una diferencia en el trío de cuentas es distorsi&oacute;n en el mayor.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'comprobante_proveedor_imputacion_ap_reporte',
                        'mostrar_consolidar' => true,
                        'col_label' => 'col-lg-2 col-form-label text-right pr-2',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde"
                                class="form-control" style="max-width: 11.5rem;"
                                value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }}">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta"
                                class="form-control" style="max-width: 11.5rem;"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="proveedores" class="{{ $colLabel }}">Proveedores</label>
                        <div class="{{ $colInput }}">
                            <div id="iap-reporte-proveedor-campo">
                                <div class="input-group mb-1">
                                    <input type="text" name="proveedores" id="proveedores" class="form-control codigoproveedor"
                                        placeholder="Vac&iacute;o = todos; c&oacute;digos separados por coma"
                                        title="F1 o lupa: consulta proveedores"
                                        autocomplete="off"
                                        value="{{ $filtros['proveedores'] ?? '' }}">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta proveedores (F1)"
                                            class="btn btn-outline-secondary consultaproveedor-iap tooltipsC">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control metaproveedor" readonly
                                    value="{{ $meta_proveedores ?? 'Todos los proveedores' }}">
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">&nbsp;</label>
                        <div class="col-lg-10">
                            <div class="custom-control custom-checkbox custom-control-inline mt-2">
                                <input type="checkbox" class="custom-control-input" id="incluir_comprobantes"
                                    name="incluir_comprobantes" value="1"
                                    @checked(!empty($filtros['incluir_comprobantes']))>
                                <label class="custom-control-label" for="incluir_comprobantes">Comprobantes</label>
                            </div>
                            <div class="custom-control custom-checkbox custom-control-inline mt-2">
                                <input type="checkbox" class="custom-control-input" id="incluir_opa"
                                    name="incluir_opa" value="1"
                                    @checked(!empty($filtros['incluir_opa']))>
                                <label class="custom-control-label" for="incluir_opa">OPA</label>
                            </div>
                            <div class="custom-control custom-checkbox custom-control-inline mt-2">
                                <input type="checkbox" class="custom-control-input" id="incluir_aplicaciones"
                                    name="incluir_aplicaciones" value="1"
                                    @checked(!empty($filtros['incluir_aplicaciones']))>
                                <label class="custom-control-label" for="incluir_aplicaciones">Aplicaciones</label>
                            </div>
                            <div class="custom-control custom-checkbox custom-control-inline mt-2">
                                <input type="checkbox" class="custom-control-input" id="solo_diferencias"
                                    name="solo_diferencias" value="1"
                                    @checked(!empty($filtros['solo_diferencias']))>
                                <label class="custom-control-label" for="solo_diferencias">Solo con distorsi&oacute;n</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-imputacion-ap">
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
                            <strong>Per&iacute;odo:</strong> {{ $periodo_texto ?? '' }}
                            @if (! empty($resultado['totales']))
                                &middot; <strong>Filas:</strong> {{ (int) ($resultado['totales']['total_filas'] ?? 0) }}
                                &middot; <strong>Comprobantes:</strong> {{ (int) ($resultado['totales']['comprobantes'] ?? 0) }}
                                &middot; <strong>OPA:</strong> {{ (int) ($resultado['totales']['opa'] ?? 0) }}
                                &middot; <strong>Aplicaciones:</strong> {{ (int) ($resultado['totales']['aplicaciones'] ?? 0) }}
                                &middot; <strong class="{{ ((int) ($resultado['totales']['con_distorsion'] ?? 0)) > 0 ? 'text-danger' : 'text-success' }}">
                                    Distorsi&oacute;n: {{ (int) ($resultado['totales']['con_distorsion'] ?? 0) }}
                                </strong>
                                &middot; <strong>Sin asiento:</strong> {{ (int) ($resultado['totales']['sin_asiento'] ?? 0) }}
                                &middot; <strong>Dif. $:</strong> {{ number_format((float) ($resultado['totales']['diferencia_ars'] ?? 0), 2, ',', '.') }}
                            @endif
                        </p>
                        @if (! empty($subtitulo))
                            <p class="mb-0 small text-muted">{{ $subtitulo }}</p>
                        @endif
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_reporte_imputacion_ap_proveedor',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                    </div>

                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            collect($filtros['empresa_ids'] ?? [])->map(function ($id) use ($empresa_query) {
                                $emp = ($empresa_query ?? collect())->firstWhere('id', (int) $id);

                                return $emp ? (object) ['nombreempresa' => $emp->nombre] : null;
                            })->filter()
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
                        #tabla-imputacion-ap thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-imputacion-ap thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: 0.72rem; }
                        #tabla-imputacion-ap tbody td { font-size: 0.72rem; vertical-align: middle; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-imputacion-ap" class="table table-striped table-bordered table-hover table-sm mb-0">
                            @include('compras.comprobante_proveedor_imputacion_ap_reporte.partials.tabla_datos', [
                                'filas' => $filasVista ?? [],
                                'puede_ver_comprobante' => $puede_ver_comprobante ?? false,
                                'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                                'puede_ver_asiento' => $puede_ver_asiento ?? false,
                                'puede_ver_pagoproveedor' => $puede_ver_pagoproveedor ?? false,
                                'para_pdf' => false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $filas->hasPages())
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                Mostrando {{ $filas->firstItem() }}&ndash;{{ $filas->lastItem() }} de {{ $filas->total() }} filas
                            </span>
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @elseif ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer">
                            <small class="text-muted">{{ $filas->total() }} fila(s).</small>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@endsection
