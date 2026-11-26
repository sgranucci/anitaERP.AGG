@extends("theme.$theme.layout")
@section('titulo')
    Conciliaci&oacute;n de centros de costo
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/sumas_saldos/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/sumas_saldos/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/movccosto/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/movccosto/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<style>
    #tabla-paginada thead th { background-color: #85C1E9; color: #17202A; }
    #tabla-paginada .mcc-fila-total-nivel td { background-color: #EAF2F8 !important; }
    #tabla-paginada .mcc-fila-total-general td { background-color: #AED6F1 !important; }
    #tabla-paginada .mcc-fila-header-empresa td { background-color: #E5E7E9 !important; }
</style>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'movccosto-overlay',
    'tituloId' => 'movccosto-titulo',
    'subtituloId' => 'movccosto-subtitulo',
    'titulo' => 'Buscando movimientos sin centro de costo…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
@php
    $modoInclusionSel = (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion');
    $totalesFiltro = $totales ?? [];
    $logosPantalla = $consultado ? \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($logos_fuente ?? []) : [];
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Conciliaci&oacute;n de centros de costo &mdash; movimientos sin centro de costo</h3>
                <div class="card-tools">
                    <a href="{{ route('movccosto', $reporte_query ?? []) }}" class="btn btn-outline-info btn-sm" title="Volver a Totales por centro de costo">
                        <i class="fa fa-reply-all"></i> Totales por centro de costo
                    </a>
                    <a href="{{ route('movccosto_conciliacion') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('movccosto_conciliacion') }}" id="form-movccosto" class="mb-0" autocomplete="off">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Recorre las cuentas marcadas con &laquo;Maneja centro de costo&raquo; y lista los movimientos
                        grabados sin centro de costo (o con un centro que ya no existe). Esos importes no entran en
                        ninguna columna de Totales por centro de costo.
                    </p>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'movccosto',
                        'id_prefix' => 'sys',
                        'col_label' => 'col-lg-2 text-right',
                    ])

                    <div class="form-group row mb-2">
                        <label class="col-lg-2 control-label text-right requerido">Desde / Hasta</label>
                        <div class="col-lg-9">
                            <div class="form-inline">
                                <input type="date" name="fecha_desde" id="mcc_fecha_desde" class="form-control form-control-sm mr-2"
                                    value="{{ $filtros['fecha_desde'] ?? '' }}">
                                <input type="date" name="fecha_hasta" id="mcc_fecha_hasta" class="form-control form-control-sm mr-3"
                                    value="{{ $filtros['fecha_hasta'] ?? '' }}">
                                <span class="btn-group btn-group-sm" role="group" aria-label="Períodos rápidos">
                                    <button type="button" class="btn btn-outline-secondary mcc-periodo-rapido" data-periodo="mes">Mes actual</button>
                                    <button type="button" class="btn btn-outline-secondary mcc-periodo-rapido" data-periodo="mes_anterior">Mes anterior</button>
                                    <button type="button" class="btn btn-outline-secondary mcc-periodo-rapido" data-periodo="anio">A&ntilde;o actual</button>
                                </span>
                            </div>
                            <small class="text-muted">Desde vac&iacute;o = hasta ese d&iacute;a; hasta vac&iacute;o = desde ese d&iacute;a.</small>
                        </div>
                    </div>

                    <div class="form-group row mb-2">
                        <label for="moneda_id" class="col-lg-2 control-label text-right requerido">Expresar en</label>
                        <div class="col-lg-3">
                            <select name="moneda_id" id="moneda_id" class="form-control form-control-sm" required>
                                @foreach ($moneda_query as $mon)
                                    <option value="{{ $mon->id }}" @selected((int) ($filtros['moneda_id'] ?? 1) === (int) $mon->id)>
                                        {{ $mon->nombre }} ({{ $mon->abreviatura }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="modo_inclusion_asientos" class="col-lg-2 control-label text-right">Asientos cierre</label>
                        <div class="col-lg-3">
                            <select name="modo_inclusion_asientos" id="modo_inclusion_asientos" class="form-control form-control-sm" autocomplete="off">
                                <option value="sin_cierre_ni_inflacion" @selected($modoInclusionSel === 'sin_cierre_ni_inflacion')>Excluir cierre e inflaci&oacute;n</option>
                                <option value="sin_cierre" @selected($modoInclusionSel === 'sin_cierre')>Excluir solo cierre</option>
                                <option value="sin_inflacion" @selected($modoInclusionSel === 'sin_inflacion')>Excluir solo inflaci&oacute;n</option>
                                <option value="todos" @selected($modoInclusionSel === 'todos')>Incluir todos</option>
                            </select>
                        </div>
                    </div>

                    @include('contable.sumas_saldos.partials.campo_rango_cuentas', [
                        'cuenta_desde_meta' => $cuenta_desde_meta ?? ['codigo' => '', 'nombre' => ''],
                        'cuenta_hasta_meta' => $cuenta_hasta_meta ?? ['codigo' => '', 'nombre' => ''],
                    ])

                    <div class="form-group row mb-0">
                        <label class="col-lg-2 control-label text-right">Ver</label>
                        <div class="col-lg-9">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="resumen" id="mcc_conc_detalle" value="0"
                                    @checked(empty($solo_resumen))>
                                <label class="form-check-label" for="mcc_conc_detalle">Detalle de movimientos</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="resumen" id="mcc_conc_resumen" value="1"
                                    @checked(! empty($solo_resumen))>
                                <label class="form-check-label" for="mcc_conc_resumen">Resumen por cuenta</label>
                            </div>
                            <span class="mx-2 text-muted">|</span>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="solo_moneda_origen" id="solo_moneda_origen" value="1"
                                    @checked(! empty($filtros['solo_moneda_origen']))>
                                <label class="form-check-label" for="solo_moneda_origen">Solo moneda origen (sin convertir)</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0 mt-3">
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

            @if ($consultado)
                <div class="card-body p-0 border-top">
                    @if (! empty($advertencias))
                        <div class="alert alert-warning mx-3 mt-3 mb-0">
                            <ul class="mb-0">
                                @foreach ($advertencias as $adv)
                                    <li>{{ $adv }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="px-3 py-2 border-bottom bg-light d-flex align-items-start">
                        <div class="mr-3">
                            @foreach ($logosPantalla as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] ?? '' }}" style="max-height:40px;max-width:140px;margin-right:6px;">
                            @endforeach
                        </div>
                        <div>
                            <p class="mb-1 small">
                                <strong>Empresas:</strong> {{ $empresas_texto ?? '' }}
                                &middot; <strong>Per&iacute;odo:</strong> {{ $periodo_texto ?? '' }}
                                &middot; <strong>Expresado en:</strong> {{ $moneda->nombre ?? '' }} ({{ $moneda->abreviatura ?? '' }})
                            </p>
                            <p class="mb-0 small text-muted">
                                Cuentas que manejan centro de costo &middot; {{ $inclusion_asientos_texto ?? '' }}
                            </p>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_movccosto_conciliacion',
                                'queryparams' => $filtrosQuery ?? [],
                                'variant' => 'compact',
                            ])
                        </div>
                        <div class="small mb-1 mb-md-0 text-md-right">
                            <span class="text-muted">Totales filtro:</span>
                            <strong>{{ (int) ($totalesFiltro['cuentas'] ?? 0) }}</strong> cuentas
                            &middot; <strong>{{ (int) ($totalesFiltro['movimientos'] ?? 0) }}</strong> movimientos sin CC
                            &middot; Debe <strong>{{ number_format((float) ($totalesFiltro['debe'] ?? 0), 2, ',', '.') }}</strong>
                            &middot; Haber <strong>{{ number_format((float) ($totalesFiltro['haber'] ?? 0), 2, ',', '.') }}</strong>
                            &middot; Saldo <strong>{{ number_format((float) ($totalesFiltro['saldo'] ?? 0), 2, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div class="tabla-ancha-grilla" data-tabla-ancha>
                        <div class="tabla-ancha-scroll-top" hidden>
                            <div class="tabla-ancha-scroll-top-inner"></div>
                        </div>
                        <div class="tabla-ancha-wrap">
                            <table class="table table-sm table-bordered mb-0 tabla-ancha" id="tabla-paginada">
                                @include('contable.movccosto.conciliacion.partials.tabla', [
                                    'filas' => $filas,
                                    'solo_resumen' => $solo_resumen,
                                    'multiempresa' => $multiempresa,
                                    'enlaces' => true,
                                    'puede_ver_mayor' => $puede_ver_mayor ?? false,
                                    'puede_ver_asiento' => $puede_ver_asiento ?? false,
                                    'mayor_base' => $mayor_base ?? [],
                                ])
                            </table>
                        </div>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="px-3 py-2 d-flex justify-content-between align-items-center flex-wrap">
                            <div class="small text-muted">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}&ndash;{{ $filas->lastItem() }} de {{ $filas->total() }}
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

@include('includes.contable.modalconsultacuentacontable')
@endsection
