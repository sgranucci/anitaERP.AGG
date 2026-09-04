@extends("theme.$theme.layout")
@section('titulo')
    Mayor plano por cuenta contable
@endsection

@section('scripts')
<script>
(function () {
    function togglePeriodo() {
        var mes = document.getElementById('modo_mes').checked;
        document.getElementById('panel-mes').style.display = mes ? '' : 'none';
        document.getElementById('panel-rango').style.display = mes ? 'none' : '';
    }
    document.querySelectorAll('input[name="modo_periodo"]').forEach(function (el) {
        el.addEventListener('change', togglePeriodo);
    });
    togglePeriodo();

})();
</script>
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/mayor_plano_cuenta/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/mayor_plano_cuenta/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Mayor plano por cuenta contable</h3>
                <div class="card-tools">
                    <a href="{{ route('mayor_plano_cuenta') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('mayor_plano_cuenta') }}" id="form-mayor-plano-cuenta" class="mb-0">
                <div class="card-body pb-2">
                    <style>
                        .mpc-seleccion-scroll {
                            max-height: 145px;
                            overflow-y: auto;
                        }
                        .mpc-panel-parametros .form-group:last-child {
                            margin-bottom: 0;
                        }
                        @media (max-width: 991.98px) {
                            .mpc-seleccion-scroll {
                                max-height: 210px;
                            }
                        }
                    </style>

                    <p class="text-muted small mb-2">
                        Defina el período y combine cuentas puntuales o por rango. Los centros de costo pueden filtrar el mayor sin cambiar su clasificación.
                        El <strong>Excel plano</strong> (formato Anita) sale después de consultar: una fila por movimiento, por cuenta o por centro de costo.
                        Baja como <strong>CSV</strong> (se abre en Excel) con emisor, OC, CAPEX y facturas — sin armar un .xlsx pesado en el servidor.
                        La columna de OC resume qué se compró (ítems; IA si está habilitada). Las facturas van en una sola celda; no se lista la COM.
                    </p>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'mayor_plano_cuenta',
                        'id_prefix' => 'mpc',
                        'col_label' => 'col-lg-2 text-right',
                    ])

                    <div class="card card-outline card-secondary mpc-panel-parametros mb-3">
                        <div class="card-header py-2">
                            <h3 class="card-title font-weight-bold">
                                <i class="fa fa-sliders-h mr-1"></i> Par&aacute;metros generales
                            </h3>
                        </div>
                        <div class="card-body py-2">
                            <div class="row">
                                <div class="col-lg-4 border-lg-right mb-2 mb-lg-0">
                                    <label class="small font-weight-bold requerido d-block mb-1">Per&iacute;odo</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modo_periodo" id="modo_mes" value="mes"
                                    {{ ($filtros['modo_periodo'] ?? 'mes') === 'mes' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modo_mes">Mes completo</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="modo_periodo" id="modo_rango" value="rango"
                                    {{ ($filtros['modo_periodo'] ?? '') === 'rango' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modo_rango">Rango de fechas</label>
                            </div>

                                    <div id="panel-mes" class="form-row mt-2">
                                <div class="col-6">
                                    <select name="mes" class="form-control">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" @selected((int) ($filtros['mes'] ?? $mes_actual) === $m)>
                                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-6">
                                    <input type="number" name="anio" class="form-control" min="2000" max="2100"
                                        value="{{ $filtros['anio'] ?? $anio_actual }}">
                                </div>
                            </div>

                                    <div id="panel-rango" class="form-row mt-2" style="display:none;">
                                <div class="col-6">
                                    <input type="date" name="fecha_desde" class="form-control"
                                        value="{{ $filtros['fecha_desde'] ?? '' }}">
                                </div>
                                <div class="col-6">
                                    <input type="date" name="fecha_hasta" class="form-control"
                                        value="{{ $filtros['fecha_hasta'] ?? '' }}">
                                </div>
                            </div>
                                </div>

                                <div class="col-lg-4 border-lg-right mb-2 mb-lg-0">
                                    <div class="form-group mb-2">
                                        <label for="moneda_id" class="small font-weight-bold requerido mb-1">Expresar en</label>
                            <select name="moneda_id" id="moneda_id" class="form-control" required>
                                @foreach ($moneda_query as $mon)
                                    <option value="{{ $mon->id }}" @selected((int) ($filtros['moneda_id'] ?? 1) === (int) $mon->id)>
                                        {{ $mon->nombre }} ({{ $mon->abreviatura }})
                                    </option>
                                @endforeach
                            </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="modo_inclusion_asientos" class="small font-weight-bold mb-1">Asientos de cierre</label>
                                        <select name="modo_inclusion_asientos" id="modo_inclusion_asientos" class="form-control">
                                            <option value="sin_cierre_ni_inflacion" @selected(($filtros['modo_inclusion_asientos'] ?? '') === 'sin_cierre_ni_inflacion')>
                                                Excluir cierre e inflaci&oacute;n
                                            </option>
                                            <option value="sin_cierre" @selected(($filtros['modo_inclusion_asientos'] ?? '') === 'sin_cierre')>Excluir solo cierre</option>
                                            <option value="sin_inflacion" @selected(($filtros['modo_inclusion_asientos'] ?? '') === 'sin_inflacion')>Excluir solo inflaci&oacute;n</option>
                                            <option value="todos" @selected(($filtros['modo_inclusion_asientos'] ?? '') === 'todos')>Incluir todos</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <label class="small font-weight-bold d-block mb-2">Origen de movimientos</label>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="solo_moneda_origen" id="solo_moneda_origen" value="1"
                                            @checked(! empty($filtros['solo_moneda_origen']))>
                                        <label class="form-check-label" for="solo_moneda_origen">
                                            Solo movimientos en moneda origen
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input type="hidden" name="incluye_subdiario" value="0">
                                        <input class="form-check-input" type="checkbox" name="incluye_subdiario" id="incluye_subdiario" value="1"
                                            @checked($filtros['incluye_subdiario'] ?? true)>
                                        <label class="form-check-label" for="incluye_subdiario">
                                            Incluir movimientos de subdiario
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="solo_movimientos_ventas" id="solo_movimientos_ventas" value="1"
                                            @checked(! empty($filtros['solo_movimientos_ventas']))>
                                        <label class="form-check-label" for="solo_movimientos_ventas">
                                            Solo movimientos de ventas (totales)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input type="hidden" name="mostrar_columna_centrocosto" value="0">
                                        <input class="form-check-input" type="checkbox" name="mostrar_columna_centrocosto" id="mostrar_columna_centrocosto" value="1"
                                            @checked(\App\Support\Contable\MayorPlanoCuentaListadoFiltros::mostrarColumnaCentrocosto($filtros ?? []))>
                                        <label class="form-check-label" for="mostrar_columna_centrocosto">
                                            Mostrar columna centro de costo
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        El subdiario completa las imputaciones que no existen en ctamov.
                                        Con &laquo;Solo movimientos de ventas&raquo; se usa subdiario sistema V del mes
                                        m&aacute;s ctamov de facturas ERP (asi_mon_ref=-1); totales por cuenta, sin tramo de saldo.
                                        La columna de centro de costo queda grabada como preferencia del usuario.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row align-items-stretch mb-3">
                        <div class="col-lg-6 mb-3 mb-lg-0">
                            @include('contable.mayor_plano_cuenta.partials.campo_consulta_cuentas', [
                                'filtros' => $filtros ?? [],
                                'cuentas_iniciales' => $cuentas_iniciales ?? [],
                                'cuenta_desde_meta' => $cuenta_desde_meta ?? ['codigo' => '', 'nombre' => ''],
                                'cuenta_hasta_meta' => $cuenta_hasta_meta ?? ['codigo' => '', 'nombre' => ''],
                            ])
                        </div>
                        <div class="col-lg-6">
                            @include('contable.mayor_plano_cuenta.partials.campo_consulta_centrocostos', [
                                'filtros' => $filtros ?? [],
                                'centrocostos_iniciales' => $centrocostos_iniciales ?? [],
                                'cc_desde_meta' => $cc_desde_meta ?? ['codigo' => '', 'nombre' => ''],
                                'cc_hasta_meta' => $cc_hasta_meta ?? ['codigo' => '', 'nombre' => ''],
                            ])
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div class="mb-2 mb-md-0 mr-3" id="mpc-excel-solapas-wrap">
                            <div class="form-check mb-0">
                                <input type="hidden" name="excel_solapas_separadas" value="0">
                                <input class="form-check-input" type="checkbox" name="excel_solapas_separadas"
                                    id="excel_solapas_separadas" value="1"
                                    @checked(! empty($filtros['excel_solapas_separadas']))
                                    @disabled(! \App\Support\Contable\MayorPlanoCuentaListadoFiltros::puedeExcelSolapasSeparadas($filtros ?? []))>
                                <label class="form-check-label" for="excel_solapas_separadas" id="excel_solapas_separadas_label">
                                    Excel en solapas separadas (una por cuenta o centro de costo)
                                </label>
                            </div>
                            <small class="text-muted d-block" id="excel_solapas_separadas_ayuda">
                                Se habilita al elegir cuentas o centros de costo en particular.
                                <span class="d-none d-md-inline"> · F1 en los c&oacute;digos abre la consulta.</span>
                            </small>
                        </div>
                        <div>
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary" id="btn-consultar">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado)
                @php $tot = $totales ?? []; @endphp
                <div class="card-body p-0 border-top">
                    @if (!empty($errores_bridge))
                        <div class="alert alert-warning mx-3 mt-3 mb-0">
                            <strong>Advertencias bridge Anita:</strong>
                            <ul class="mb-0">
                                @foreach ($errores_bridge as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-1 small">
                            <strong>Empresas:</strong> {{ $empresas_texto }}
                            @if (count($filtros['empresa_ids'] ?? []) > 1)
                                · <strong>Modo:</strong>
                                @if ($filtros['consolidar_empresas'] ?? true)
                                    consolidado
                                @else
                                    un reporte por empresa
                                @endif
                            @endif
                            · <strong>Período:</strong> {{ $periodo_texto }}
                            · <strong>Expresado en:</strong> {{ $moneda->nombre ?? '' }} ({{ $moneda->abreviatura ?? '' }})
                        </p>
                        <p class="mb-0 small text-muted">
                            {{ $inclusion_asientos_texto }}
                            · {{ $centrocostos_texto }}
                            @if (($origen_movimientos_texto ?? '') !== '')
                                · {{ $origen_movimientos_texto }}
                            @endif
                            · {{ $tot['stats']['ctamov_filas'] ?? 0 }} ctamov
                            · {{ $tot['stats']['subdiario_filas'] ?? 0 }} subdiario
                        </p>
                    </div>

                    @if (empty($solo_totales_ventas))
                    <form method="get" action="{{ route('mayor_plano_cuenta') }}" id="form-mayor-plano-cuenta-filtro" class="px-3 py-2 border-bottom bg-light">
                        @foreach ($filtrosQuery ?? [] as $key => $val)
                            @if (is_array($val))
                                @foreach ($val as $v)
                                    <input type="hidden" name="{{ $key }}" value="{{ $v }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                            @endif
                        @endforeach
                        <input type="hidden" name="consultar" value="1">
                        <div class="form-row align-items-center">
                            <div class="col-auto">
                                <label for="filtro_texto" class="col-form-label col-form-label-sm">Buscar en listado</label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="filtro_texto" id="filtro_texto" class="form-control form-control-sm"
                                    placeholder="Cuenta, centro de costo, comprobante, descripción, OC…"
                                    value="{{ $filtros['filtro_texto'] ?? '' }}">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <i class="fa fa-filter"></i> Filtrar
                                </button>
                            </div>
                        </div>
                    </form>
                    @endif

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0" id="mayor-plano-cuenta-exportar">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_mayor_plano_cuenta',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                            @php
                                $paramsPlano = array_filter($filtrosQuery ?? [], fn ($v) => $v !== null && $v !== '');
                                $suffixPlano = count($paramsPlano) ? '?'.http_build_query($paramsPlano) : '';
                            @endphp
                            <a href="{{ route('listar_mayor_plano_cuenta', ['formato' => 'EXCEL_PLANO']).$suffixPlano }}"
                                class="btn btn-app bg-info" title="Una fila por movimiento, con observación de OC y facturas (formato Anita)">
                                <i class="fas fa-file-excel"></i> Excel plano
                            </a>
                        </div>
                        <div class="small mb-1 mb-md-0 text-md-right">
                            <span class="text-muted">Totales filtro:</span>
                            <strong>{{ (int) ($tot['cantidad_cuentas'] ?? 0) }}</strong> cuentas
                            · <strong>{{ (int) ($tot['cantidad_filas'] ?? 0) }}</strong> líneas
                            · Debe <strong>{{ number_format((float) ($tot['total_debe'] ?? 0), 2, ',', '.') }}</strong>
                            · Haber <strong>{{ number_format((float) ($tot['total_haber'] ?? 0), 2, ',', '.') }}</strong>
                        </div>
                    </div>

                    @php
                        $filasVista = $filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                            ? $filas->items()
                            : ($filas ?? []);
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filasVista);
                    @endphp
                    @if (count($logosVista) > 0)
                        <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    @include('contable.mayor_plano_cuenta.partials.resumen_totales', [
                        'resumen' => $resumen ?? [],
                        'resumen_cc' => $resumen_cc ?? [],
                        'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                        'expandido' => ! empty($solo_totales_ventas),
                    ])

                    @include('contable.mayor_plano_cuenta.partials.cuadre_cobro_ventas', [
                        'cuadre_cobro_ventas' => $cuadre_cobro_ventas ?? null,
                    ])

                    @if (empty($solo_totales_ventas))
                    <div class="px-3 pt-2 pb-1">
                        <h6 class="mb-0 font-weight-bold">Detalle de movimientos</h6>
                        <small class="text-muted">Listado paginado por cuenta contable</small>
                    </div>

                    <style>
                        #tabla-mayor-plano thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-mayor-plano thead th { font-weight: 600; border-color: #7fb3d5; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-mayor-plano" class="table table-striped table-bordered table-hover table-sm mb-0" style="font-size: 0.75rem;">
                            @include('contable.mayor_plano_cuenta.partials.tabla_datos', [
                                'filas' => $filasVista,
                                'puede_ver_asiento' => $puede_ver_asiento ?? false,
                                'puede_ver_cuenta' => $puede_ver_cuenta ?? false,
                                'puede_ver_ordencompra' => $puede_ver_ordencompra ?? false,
                                'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                                'puede_ver_cliente' => $puede_ver_cliente ?? false,
                                'puede_ver_cuentacaja' => $puede_ver_cuentacaja ?? false,
                                'puede_ver_comprobante_proveedor' => $puede_ver_comprobante_proveedor ?? false,
                                'puede_ver_factura' => $puede_ver_factura ?? false,
                                'puede_ver_remesa' => $puede_ver_remesa ?? false,
                                'puede_ver_jornada_gastronomia' => $puede_ver_jornada_gastronomia ?? false,
                                'puede_ver_rendicion_estacionamiento' => $puede_ver_rendicion_estacionamiento ?? false,
                                'puede_ver_transferencia_mercaderia' => $puede_ver_transferencia_mercaderia ?? false,
                                'puede_ver_cobranza' => $puede_ver_cobranza ?? false,
                                'puede_ver_pagoproveedor' => $puede_ver_pagoproveedor ?? false,
                                'puede_ver_recepcion_proveedor' => $puede_ver_recepcion_proveedor ?? false,
                                'puede_ver_movimientostock' => $puede_ver_movimientostock ?? false,
                                'puede_ver_caja_movimiento' => $puede_ver_caja_movimiento ?? false,
                                'puede_ver_solicitudpago' => $puede_ver_solicitudpago ?? false,
                                'multiempresa' => $multiempresa ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} registros
                                @else
                                    Sin registros
                                @endif
                            </span>
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'mayor-plano-cuenta-overlay',
    'tituloId' => 'mayor-plano-cuenta-overlay-titulo',
    'subtituloId' => 'mayor-plano-cuenta-overlay-subtitulo',
    'titulo' => 'Calculando el mayor…',
    'subtitulo' => 'Puede demorar según el período y las empresas seleccionadas. No cierre la página.',
])
@include('includes.contable.modalconsultacuentacontable')
@include('includes.contable.modalconsultacentrocosto')
@endsection
