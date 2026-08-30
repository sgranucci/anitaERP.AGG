@extends("theme.$theme.layout")
@section('titulo')
    Ventas por concepto
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/ventas/concepto_venta/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/ventas_por_concepto/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ventas por concepto</h3>
                <div class="card-tools">
                    <a href="{{ route('ventas_por_concepto') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('ventas_por_concepto') }}" id="form-ventas-por-concepto" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Renglones de factura / NC / ND con <strong>concepto de venta</strong> (sin art&iacute;culo de stock).
                        Las notas de cr&eacute;dito restan. No incluye comprobantes anulados ni presupuestos (PRE).
                        Los importes son del rengl&oacute;n (no prorratea descuento de pie de factura).
                        La cuenta se resuelve con la matriz vigente (empresa + tipo + fecha del comprobante).
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
                                    <option value="">Seleccione&hellip;</option>
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

                    @include('ventas.partials.campo_consulta_concepto_venta', [
                        'label' => 'Concepto',
                        'conceptoId' => $filtros['concepto_venta_id'] ?? '',
                        'codigo' => $filtros['concepto_codigo'] ?? '',
                        'descripcion' => $filtros['concepto_nombre'] ?? '',
                        'required' => false,
                        'col_label' => $colLabel,
                        'col_input' => 'col-lg-6',
                    ])
                    <input type="hidden" name="concepto_codigo" id="concepto_codigo_filtro" value="{{ $filtros['concepto_codigo'] ?? '' }}">
                    <input type="hidden" name="concepto_nombre" id="concepto_nombre_filtro" value="{{ $filtros['concepto_nombre'] ?? '' }}">

                    <div class="form-group row">
                        <label for="tipotransaccion_id" class="{{ $colLabel }}">Tipo</label>
                        <div class="{{ $colInput }}">
                            <select name="tipotransaccion_id" id="tipotransaccion_id" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($tipo_query ?? [] as $tipo)
                                    <option value="{{ $tipo->id }}" @selected((int) ($filtros['tipotransaccion_id'] ?? 0) === (int) $tipo->id)>
                                        {{ trim(($tipo->abreviatura ?? '').' — '.($tipo->nombre ?? '')) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @php
                        $agruparPor = \App\Support\Ventas\VentasPorConceptoListadoFiltros::normalizarAgruparPor($filtros['agrupar_por'] ?? null);
                    @endphp
                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Agrupar por</label>
                        <div class="col-lg-8 pt-1">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="agrupar_por" id="agrupar_concepto"
                                    value="concepto"
                                    @checked($agruparPor === \App\Support\Ventas\VentasPorConceptoListadoFiltros::AGRUPAR_CONCEPTO)>
                                <label class="custom-control-label" for="agrupar_concepto">Concepto</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="agrupar_por" id="agrupar_cuenta"
                                    value="cuenta"
                                    @checked($agruparPor === \App\Support\Ventas\VentasPorConceptoListadoFiltros::AGRUPAR_CUENTA)>
                                <label class="custom-control-label" for="agrupar_cuenta">Cuenta contable</label>
                            </div>
                            <p class="text-muted small mb-0 mt-1">
                                Mismos renglones; cambia el subtotal y el orden (estilo mayor por servicio).
                            </p>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
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
                            <strong>Empresa:</strong> {{ $empresa_texto ?? '' }}
                            · <strong>Período:</strong> {{ $periodo_texto ?? '' }}
                            · <strong>Concepto:</strong> {{ $concepto_texto ?? 'Todos' }}
                            · <strong>Tipo:</strong> {{ $tipo_texto ?? 'Todos' }}
                            · <strong>Agrupado por:</strong> {{ $agrupacion_texto ?? 'Concepto' }}
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_ventas_por_concepto',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        @if (!empty($totales))
                            <div class="small mb-1 mb-md-0 text-md-right">
                                <span class="text-muted">Renglones:</span>
                                <strong>{{ (int) ($totales['cantidad_detalle'] ?? 0) }}</strong>
                                · Neto <strong>{{ number_format((float) ($totales['neto'] ?? 0), 2, ',', '.') }}</strong>
                                · IVA <strong>{{ number_format((float) ($totales['iva'] ?? 0), 2, ',', '.') }}</strong>
                                · Total <strong>{{ number_format((float) ($totales['total'] ?? 0), 2, ',', '.') }}</strong>
                            </div>
                        @endif
                    </div>

                    @php $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filasVista ?? [])); @endphp
                    @if (count($logosVista) > 0)
                        <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    <style>
                        #tabla-ventas-por-concepto thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-ventas-por-concepto thead th { font-weight: 600; border-color: #7fb3d5; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-ventas-por-concepto" class="table table-striped table-bordered table-hover table-sm mb-0" style="font-size: 0.78rem;">
                            @include('ventas.ventas_por_concepto.partials.tabla_datos', [
                                'filas' => $filasVista ?? [],
                                'puede_ver_venta' => $puede_ver_venta ?? false,
                                'puede_ver_cliente' => $puede_ver_cliente ?? false,
                                'puede_ver_concepto' => $puede_ver_concepto ?? false,
                                'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} filas
                                @else
                                    Sin registros
                                @endif
                            </span>
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultaconceptoventa')
@endsection
