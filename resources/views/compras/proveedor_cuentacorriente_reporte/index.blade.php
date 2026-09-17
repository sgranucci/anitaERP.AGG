@extends("theme.$theme.layout")

@section('titulo')
    Deuda / cuenta corriente proveedores
@endsection

@section('scripts')
<script>
    window.CC_PROVEEDORES_REPORTE = {
        proveedoresIniciales: @json($proveedores_iniciales ?? []),
        leerProveedorUrlBase: @json(url('compras/leerproveedorporcodigo')),
        consultado: @json(! empty($consultado)),
    };
</script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor_cuentacorriente_reporte/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor_cuentacorriente_reporte/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Compras\ProveedorCuentacorrienteReporteFiltros;
    $colLabel = 'col-lg-2 control-label text-right pr-2';
    $modoDeuda = ($filtros['modo'] ?? ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA)
        !== ProveedorCuentacorrienteReporteFiltros::MODO_FICHA;
@endphp

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'cc-proveedores-reporte-overlay',
    'tituloId' => 'cc-proveedores-reporte-titulo',
    'subtituloId' => 'cc-proveedores-reporte-subtitulo',
    'titulo' => 'Consultando cuenta corriente…',
    'subtitulo' => 'Puede demorar según el período y la cantidad de proveedores. No cierre la página.',
])

<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Deuda / ficha de cuenta corriente de proveedores</h3>
                <div class="card-tools">
                    <a href="{{ route('proveedor_cuentacorriente_reporte') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="get" action="{{ route('proveedor_cuentacorriente_reporte') }}" id="form-cc-proveedores-reporte" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Consultá la <strong>deuda pendiente</strong> o la <strong>ficha corrida</strong> de uno, varios o todos los proveedores.
                        Elegí primero cómo querés seleccionar proveedores; el resto de filtros se aplica igual en todos los casos.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => true,
                        'col_label' => $colLabel,
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Tipo de consulta</label>
                        <div class="col-lg-9">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="modo" id="modo_deuda" value="deuda"
                                    @checked($modoDeuda)>
                                <label class="custom-control-label" for="modo_deuda">Deuda pendiente (facturas, NC y adelantos)</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="modo" id="modo_ficha" value="ficha"
                                    @checked(! $modoDeuda)>
                                <label class="custom-control-label" for="modo_ficha">Ficha cuenta corriente (debe / haber)</label>
                            </div>
                        </div>
                    </div>

                    @include('compras.proveedor_cuentacorriente_reporte.partials.selector_proveedores', [
                        'filtros' => $filtros,
                        'proveedores_iniciales' => $proveedores_iniciales ?? [],
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }}">Desde</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}">
                            <small class="text-muted">Vacío = desde el inicio</small>
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right pr-2">Hasta</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Opciones</label>
                        <div class="col-lg-9">
                            <div class="custom-control custom-checkbox mb-2" id="wrap-incluir-aplicaciones">
                                <input type="checkbox" class="custom-control-input" name="incluir_aplicaciones" id="incluir_aplicaciones" value="1"
                                    @checked(! empty($filtros['incluir_aplicaciones']))>
                                <label class="custom-control-label" for="incluir_aplicaciones">
                                    En deuda: mostrar el detalle de aplicaciones debajo de cada factura
                                </label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" name="solo_totales" id="solo_totales" value="1"
                                    @checked(! empty($filtros['solo_totales']))>
                                <label class="custom-control-label" for="solo_totales">
                                    Imprimir solo el total de cada proveedor (sin detalle de comprobantes)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Moneda</label>
                        <div class="col-lg-9">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="expresion" id="expresion_pesos" value="pesos"
                                    @checked(($filtros['expresion'] ?? 'pesos') === 'pesos')>
                                <label class="custom-control-label" for="expresion_pesos">Convertir a moneda local</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="expresion" id="expresion_origen" value="origen"
                                    @checked(($filtros['expresion'] ?? '') === 'origen')>
                                <label class="custom-control-label" for="expresion_origen">Moneda del comprobante</label>
                            </div>
                            <div class="mt-2" id="wrap-cotizacion-modo">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" name="cotizacion_modo" id="cot_comprobante" value="comprobante_o_dia"
                                        @checked(($filtros['cotizacion_modo'] ?? 'comprobante_o_dia') === 'comprobante_o_dia')>
                                    <label class="custom-control-label" for="cot_comprobante">
                                        Cotización del comprobante; si no tiene, la vigente del día
                                    </label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" name="cotizacion_modo" id="cot_dia" value="dia"
                                        @checked(($filtros['cotizacion_modo'] ?? '') === 'dia')>
                                    <label class="custom-control-label" for="cot_dia">
                                        Siempre cotización vigente del día del movimiento
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-cc-reporte">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado ?? false)
                <div class="card-body border-top pt-3">
                    @foreach ($resultado['advertencias'] ?? [] as $aviso)
                        <div class="alert alert-warning py-2 mb-2">{{ $aviso }}</div>
                    @endforeach

                    <p class="small mb-2">
                        {{ $subtitulo ?? '' }}
                        · <strong>Proveedores:</strong> {{ $resultado['stats']['proveedores'] ?? 0 }}
                        · <strong>Movimientos:</strong> {{ $resultado['stats']['movimientos'] ?? 0 }}
                        @if (($resultado['stats']['aplicaciones'] ?? 0) > 0)
                            · <strong>Aplicaciones:</strong> {{ $resultado['stats']['aplicaciones'] }}
                        @endif
                    </p>

                    @php
                        $exportQuery = http_build_query($filtrosQuery ?? []);
                        $totales = $resultado['totales'] ?? [];
                    @endphp

                    <div class="mb-2">
                        @if ($modoDeuda)
                            <span class="badge badge-info mr-1">
                                Deuda total: {{ number_format((float) ($totales['pendiente'] ?? 0), 2, ',', '.') }}
                                {{ $totales['abreviatura'] ?? '' }}
                            </span>
                        @else
                            <span class="badge badge-info mr-1">
                                Debe: {{ number_format((float) ($totales['debe'] ?? 0), 2, ',', '.') }}
                            </span>
                            <span class="badge badge-secondary mr-1">
                                Haber: {{ number_format((float) ($totales['haber'] ?? 0), 2, ',', '.') }}
                            </span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <a href="{{ route('listar_proveedor_cuentacorriente_reporte', ['formato' => 'PDF']) }}?{{ $exportQuery }}" class="btn btn-app bg-danger js-cc-reporte-export">
                            <i class="fas fa-file-pdf"></i> Pdf
                        </a>
                        <a href="{{ route('listar_proveedor_cuentacorriente_reporte', ['formato' => 'EXCEL']) }}?{{ $exportQuery }}" class="btn btn-app bg-success js-cc-reporte-export">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="{{ route('listar_proveedor_cuentacorriente_reporte', ['formato' => 'CSV']) }}?{{ $exportQuery }}" class="btn btn-app bg-warning js-cc-reporte-export">
                            <i class="fas fa-file-csv"></i> Csv
                        </a>
                    </div>

                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            collect($filasVista ?? [])->map(fn ($f) => (object) ['nombreempresa' => $f['nombreempresa'] ?? ''])
                        );
                    @endphp
                    <div class="border-bottom pb-2 mb-3 d-flex flex-wrap align-items-center">
                        @foreach ($logosVista as $logo)
                            <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                        @endforeach
                        <div class="ml-auto text-muted small">Vista previa — columnas alineadas al exportable</div>
                    </div>

                    <style>
                        #tabla-cc-proveedores-reporte thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-cc-proveedores-reporte thead th { font-weight: 600; border-color: #7fb3d5; }
                        #tabla-cc-proveedores-reporte .cc-rep-header { background: #d6eaf8; font-weight: 600; }
                        #tabla-cc-proveedores-reporte .cc-rep-total {
                            background: #f9e79f;
                            font-weight: 700;
                            border-top: 2px solid #b7950b;
                            color: #1b4f72;
                        }
                        #tabla-cc-proveedores-reporte .cc-rep-total td { font-size: 0.92rem; }
                        #tabla-cc-proveedores-reporte .cc-rep-total .text-right { font-size: 0.95rem; }
                        #tabla-cc-proveedores-reporte .cc-rep-apl { color: #555; font-style: italic; }
                        #tabla-cc-proveedores-reporte .cc-rep-saldo-ant { background: #f4f6f7; }
                        #tabla-cc-proveedores-reporte .text-right { text-align: right; }
                    </style>

                    <div class="table-responsive">
                        <table id="tabla-cc-proveedores-reporte" class="table table-bordered table-hover table-sm mb-0" style="font-size: 0.82rem;">
                            @include('compras.proveedor_cuentacorriente_reporte.partials.tabla_datos', [
                                'filas' => $filasVista ?? [],
                                'filtros' => $filtros,
                                'mostrarLinks' => true,
                                'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                                'puede_ver_comprobante' => $puede_ver_comprobante ?? false,
                                'puede_ver_pago' => $puede_ver_pago ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="mt-2 d-flex flex-wrap justify-content-between align-items-center">
                            <div class="small text-muted">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
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

@include('includes.compras.modalconsultaproveedor')
@endsection
