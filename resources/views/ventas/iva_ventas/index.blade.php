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
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="clasificar_por_host" id="clasificar_por_host" value="1"
                                    @checked(! empty($filtros['clasificar_por_host']))>
                                <label class="form-check-label" for="clasificar_por_host">Clasificar por host (PC de facturación)</label>
                            </div>
                        </div>
                        <div class="{{ $colLabel }} d-none d-lg-block"></div>
                        <div class="{{ $colInput }}">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="solo_moneda_origen" id="solo_moneda_origen" value="1"
                                    @checked(! empty($filtros['solo_moneda_origen']))>
                                <label class="form-check-label" for="solo_moneda_origen">Convertir moneda extranjera con cotización del comprobante</label>
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
                            </div>
                        @endif
                    </div>

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

                    @include('ventas.iva_ventas.partials.conciliacion_contable', [
                        'resultado' => $resultado,
                        'puede_ver_puntoventa' => $puede_ver_puntoventa ?? false,
                        'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                    ])

                    @include('ventas.iva_ventas.partials.totales_puntoventa', [
                        'resultado' => $resultado,
                        'puede_ver_puntoventa' => $puede_ver_puntoventa ?? false,
                    ])

                    <style>
                        #tabla-paginada thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-paginada thead th { font-weight: 600; border-color: #7fb3d5; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-paginada" class="table table-striped table-bordered table-hover table-sm mb-0" style="font-size: 0.78rem;">
                            @include('ventas.iva_ventas.partials.tabla_datos', [
                                'resultado' => $resultado,
                                'filas' => $filasVista ?? [],
                                'clasificar_por_host' => ! empty($filtros['clasificar_por_host']),
                                'mostrar_secciones' => false,
                                'puede_ver_venta' => $puede_ver_venta ?? false,
                                'puede_ver_cliente' => $puede_ver_cliente ?? false,
                                'puede_ver_puntoventa' => $puede_ver_puntoventa ?? false,
                                'puede_ver_tipotransaccion' => $puede_ver_tipotransaccion ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} comprobantes
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
@endsection
