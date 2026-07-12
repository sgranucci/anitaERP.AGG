@extends("theme.$theme.layout")

@section('titulo')
    Reporte descuentos gastronomía
@endsection

@section('scripts')
<script>
    window.DESCUENTO_REPORTE = {
        descuentosIniciales: @json($descuentos_iniciales ?? []),
        clientesIniciales: @json($clientes_iniciales ?? []),
        mozosIniciales: @json($mozos_iniciales ?? []),
        vipsIniciales: @json($vips_iniciales ?? []),
        consultado: @json(! empty($consultado)),
        consultaFacturasUrl: @json(route('gastronomia_descuento_reporte_consulta_facturas')),
        consultaMozoUrl: @json(route('gastronomia_descuento_reporte_consulta_mozo')),
        leerMozoUrlBase: @json(url('ventas/gastronomia/descuento-reporte/leer-mozo')),
        consultaVipUrl: @json(route('gastronomia_descuento_reporte_consulta_clientevip')),
        leerVipUrlBase: @json(url('ventas/gastronomia/descuento-reporte/leer-clientevip')),
        filtrosConsulta: @json($filtrosQuery ?? []),
        puedeVerFactura: @json(! empty($puede_ver_factura)),
        urlVerFacturaBase: @json(url('ventas/gastronomia/facturas-dia')),
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/descuento_reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/descuento_reporte.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reporte descuentos gastronomía</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('gastronomia_descuento_reporte') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('gastronomia_descuento_reporte') }}" id="form-descuento-reporte" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Ventas facturadas con descuento de cabecera, desglosadas por artículo.
                        Costo unitario desde lista {{ (int) config('gastronomia.informe_gerente_costo_lista_base', 5000) }} + mes del rango.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => true,
                        'col_label' => 'col-lg-2 control-label text-right pr-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2 requerido">Desde jornada</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right pr-2 requerido">Hasta jornada</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}" required>
                        </div>
                    </div>

                    <div class="form-group row mb-2">
                        <label class="col-lg-2 control-label text-right pr-2">Alcance</label>
                        <div class="col-lg-8">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="listar_todos" id="listar_todos" value="1"
                                    @checked(! empty($filtros['listar_todos']))>
                                <label class="custom-control-label" for="listar_todos">
                                    Listar todos con ventas en el per&iacute;odo (sin seleccionar c&oacute;digos, clientes ni mozos)
                                </label>
                            </div>
                        </div>
                    </div>

                    @php
                        $modoClienteFiltro = ($filtros['agrupar_por'] ?? 'codigo_descuento') === 'cliente_descuento';
                        $modoMozoFiltro = ($filtros['agrupar_por'] ?? 'codigo_descuento') === 'mozo_descuento';
                        $modoVipFiltro = ($filtros['agrupar_por'] ?? 'codigo_descuento') === 'cliente_vip';
                        $modoCodigoFiltro = ! $modoClienteFiltro && ! $modoMozoFiltro && ! $modoVipFiltro;
                        $listarTodosFiltro = ! empty($filtros['listar_todos']);
                    @endphp
                    <div id="bloque-tipo-seleccion">
                        <div class="form-group row mb-2">
                            <label class="col-lg-2 control-label text-right pr-2" id="label-tipo-seleccion">{{ $listarTodosFiltro ? 'Agrupar por' : 'Filtrar por' }}</label>
                            <div class="col-lg-8">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" name="agrupar_por" id="agrupar_codigo"
                                        value="codigo_descuento"
                                        @checked($modoCodigoFiltro)>
                                    <label class="custom-control-label" for="agrupar_codigo">C&oacute;digo de descuento</label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" name="agrupar_por" id="agrupar_cliente"
                                        value="cliente_descuento"
                                        @checked($modoClienteFiltro)>
                                    <label class="custom-control-label" for="agrupar_cliente">Cliente interno de descuento</label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" name="agrupar_por" id="agrupar_mozo"
                                        value="mozo_descuento"
                                        @checked($modoMozoFiltro)>
                                    <label class="custom-control-label" for="agrupar_mozo">Mozo</label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" class="custom-control-input" name="agrupar_por" id="agrupar_vip"
                                        value="cliente_vip"
                                        @checked($modoVipFiltro)>
                                    <label class="custom-control-label" for="agrupar_vip">Cliente VIP</label>
                                </div>
                                <p class="text-muted small mb-0 mt-2" id="ayuda-tipo-seleccion">
                                    @if ($listarTodosFiltro)
                                        Define c&oacute;mo se arman las secciones del reporte para todos los c&oacute;digos/clientes/mozos/VIP con ventas en el per&iacute;odo.
                                    @elseif ($modoVipFiltro)
                                        Elija clientes VIP puntuales y/o un rango de c&oacute;digos; si no ingresa nada se listan todos los clientes VIP con ventas en el per&iacute;odo (canjes de marketing).
                                    @elseif ($modoMozoFiltro)
                                        Elija mozos puntuales y/o un rango de c&oacute;digos; si no ingresa nada se listan todos los mozos con ventas en el per&iacute;odo.
                                    @elseif ($modoClienteFiltro)
                                        Elija clientes internos y el reporte mostrar&aacute; un bloque por cada uno (ventas con descuento asignados a ese cliente en la cuenta).
                                    @else
                                        Elija c&oacute;digos de descuento de cabecera y el reporte mostrar&aacute; un bloque por cada c&oacute;digo (art&iacute;culos vendidos con ese descuento).
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div id="wrap-seleccion-descuento" @if($listarTodosFiltro || ! $modoCodigoFiltro) style="display: none;" @endif>
                            @include('ventas.gastronomia.descuento_reporte.partials.campo_consulta_descuentos', [
                                'descuentos_iniciales' => $descuentos_iniciales ?? [],
                            ])
                        </div>

                        <div id="wrap-seleccion-cliente" @if($listarTodosFiltro || ! $modoClienteFiltro) style="display: none;" @endif>
                            @include('ventas.gastronomia.descuento_reporte.partials.campo_consulta_clientes', [
                                'clientes_iniciales' => $clientes_iniciales ?? [],
                                'filtros' => $filtros ?? [],
                            ])
                        </div>

                        <div id="wrap-seleccion-mozo" @if($listarTodosFiltro || ! $modoMozoFiltro) style="display: none;" @endif>
                            @include('ventas.gastronomia.descuento_reporte.partials.campo_consulta_mozos', [
                                'mozos_iniciales' => $mozos_iniciales ?? [],
                                'filtros' => $filtros ?? [],
                            ])
                        </div>

                        <div id="wrap-seleccion-vip" @if($listarTodosFiltro || ! $modoVipFiltro) style="display: none;" @endif>
                            @include('ventas.gastronomia.descuento_reporte.partials.campo_consulta_vips', [
                                'vips_iniciales' => $vips_iniciales ?? [],
                                'filtros' => $filtros ?? [],
                            ])
                        </div>

                        @include('ventas.gastronomia.descuento_reporte.partials.campo_filtro_descuentos_cliente', [
                            'filtros' => $filtros ?? [],
                        ])
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-2 control-label text-right pr-2">Presentación</label>
                        <div class="col-lg-8">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="hidden" name="presentacion_columnas" id="presentacion_columnas_hidden"
                                    value="{{ ! empty($filtros['presentacion_columnas']) ? '1' : '0' }}">
                                <input type="checkbox" class="custom-control-input" id="presentacion_columnas" value="1"
                                    @checked(! empty($filtros['presentacion_columnas']))>
                                <label class="custom-control-label" for="presentacion_columnas">
                                    Vista consolidada: una sola tabla con columnas por cada
                                    @if ($listarTodosFiltro)
                                        grupo con ventas
                                    @else
                                        selección
                                    @endif
                                    (cliente, mozo o c&oacute;digo seg&uacute;n agrupaci&oacute;n; tambi&eacute;n con Listar todos)
                                </label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="excel_solapas" id="excel_solapas" value="1"
                                    @checked(! empty($filtros['excel_solapas']) && empty($filtros['listar_todos']))
                                    @disabled(! empty($filtros['presentacion_columnas']) || ! empty($filtros['listar_todos']))>
                                <label class="custom-control-label" for="excel_solapas">
                                    Exportar Excel con una solapa por selecci&oacute;n + totales
                                    (solo con descuentos, clientes o mozos elegidos, no con Listar todos)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <input type="hidden" name="refrescar_cache" id="refrescar_cache_descuento_reporte" value="0">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-descuento-reporte">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            <span id="aviso-reconsultar-descuento-reporte" class="text-warning small ml-2" style="display: none;">
                                Cambió presentación o alcance: pulse Consultar para aplicar.
                            </span>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado ?? false)
                <div class="card-body border-top pt-3 pb-2">
                    @foreach ($advertencias ?? [] as $aviso)
                        <div class="alert alert-warning py-2 mb-2">{{ $aviso }}</div>
                    @endforeach
                    @include('ventas.gastronomia.descuento_reporte.partials.aviso_rango_clientes_faltantes', [
                        'clientes_rango_codigos_faltantes' => $clientes_rango_codigos_faltantes ?? [],
                    ])
                    @include('ventas.gastronomia.descuento_reporte.partials.aviso_rango_mozos_faltantes', [
                        'mozos_rango_codigos_faltantes' => $mozos_rango_codigos_faltantes ?? [],
                    ])
                    @include('ventas.gastronomia.descuento_reporte.partials.aviso_rango_vips_faltantes', [
                        'vips_rango_codigos_faltantes' => $vips_rango_codigos_faltantes ?? [],
                    ])
                    @if (! empty($resultado['codigos_sin_datos'] ?? []))
                        <div class="alert alert-info py-2 mb-2">
                            Sin ventas en el per&iacute;odo para c&oacute;digos: {{ implode(', ', $resultado['codigos_sin_datos']) }}.
                        </div>
                    @endif
                    @if (! empty($resultado['clientes_sin_datos'] ?? []))
                        <div class="alert alert-info py-2 mb-2">
                            Sin ventas en el per&iacute;odo para clientes ID: {{ implode(', ', $resultado['clientes_sin_datos']) }}.
                        </div>
                    @endif
                    @if (! empty($resultado['mozos_sin_datos'] ?? []))
                        <div class="alert alert-info py-2 mb-2">
                            Sin ventas en el per&iacute;odo para mozos ID: {{ implode(', ', $resultado['mozos_sin_datos']) }}.
                        </div>
                    @endif
                    @if (! empty($resultado['vips_sin_datos'] ?? []))
                        <div class="alert alert-info py-2 mb-2">
                            Sin ventas en el per&iacute;odo para clientes VIP ID: {{ implode(', ', $resultado['vips_sin_datos']) }}.
                        </div>
                    @endif
                    <p class="mb-0 small">
                        <strong>Empresa:</strong> {{ $empresa_texto ?? '' }}
                        · <strong>Per&iacute;odo:</strong> {{ $periodo_texto ?? '' }}
                        · <strong>Agrupaci&oacute;n:</strong> {{ \App\Support\Ventas\GastronomiaDescuentoReporteFiltros::etiquetaAgrupacion($filtros ?? []) }}
                        @if (! empty($filtros['codigos_descuento_cliente_resueltos'] ?? []))
                            · <strong>Filtro desc.:</strong> {{ implode(', ', $filtros['codigos_descuento_cliente_resueltos']) }}
                        @endif
                        @php
                            $etiquetaRangoCliente = \App\Support\Ventas\GastronomiaDescuentoReporteClienteSupport::etiquetaRangoCodigo(
                                (string) ($filtros['cliente_codigo_desde'] ?? ''),
                                (string) ($filtros['cliente_codigo_hasta'] ?? ''),
                            );
                        @endphp
                        @if ($etiquetaRangoCliente !== '')
                            · <strong>Rango clientes:</strong> {{ $etiquetaRangoCliente }}
                        @endif
                        @php
                            $etiquetaRangoMozo = \App\Support\Ventas\GastronomiaDescuentoReporteMozoSupport::etiquetaRangoCodigo(
                                (string) ($filtros['mozo_codigo_desde'] ?? ''),
                                (string) ($filtros['mozo_codigo_hasta'] ?? ''),
                            );
                        @endphp
                        @if ($etiquetaRangoMozo !== '')
                            · <strong>Rango mozos:</strong> {{ $etiquetaRangoMozo }}
                        @endif
                        @php
                            $etiquetaRangoVip = \App\Support\Ventas\GastronomiaDescuentoReporteVipSupport::etiquetaRangoCodigo(
                                (string) ($filtros['vip_codigo_desde'] ?? ''),
                                (string) ($filtros['vip_codigo_hasta'] ?? ''),
                            );
                        @endphp
                        @if ($etiquetaRangoVip !== '')
                            · <strong>Rango VIP:</strong> {{ $etiquetaRangoVip }}
                        @endif
                        · <strong>Lista costo:</strong> {{ $resultado['listas_costo']['lista_actual'] ?? '' }}
                        ({{ $resultado['listas_costo']['mes_actual_label'] ?? '' }})
                    </p>
                </div>

                <div class="card-body p-0 border-top">
                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_gastronomia_descuento_reporte',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                    </div>

                    @php
                        $empresaLogo = ($empresa_query ?? collect())->firstWhere('id', (int) ($filtros['empresa_id'] ?? 0));
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            $empresaLogo
                                ? collect([(object) ['nombreempresa' => $empresaLogo->nombre]])
                                : collect()
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
                        .tabla-descuento-reporte thead tr { background-color: #85C1E9; color: #17202A; }
                        .tabla-descuento-reporte thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; }
                        .descuento-reporte-columnas-wrap {
                            overflow-x: auto;
                            max-width: 100%;
                        }
                        .tabla-descuento-reporte-col-fijas {
                            border-collapse: separate;
                            border-spacing: 0;
                        }
                        .descuento-reporte-columnas-wrap .col-fija-1 {
                            position: sticky;
                            left: 0;
                            z-index: 6;
                            min-width: 6.5rem;
                            width: 6.5rem;
                            background-color: #fff;
                            background-clip: padding-box;
                        }
                        .descuento-reporte-columnas-wrap .col-fija-2 {
                            position: sticky;
                            left: 6.5rem;
                            z-index: 7;
                            min-width: 14rem;
                            max-width: 14rem;
                            width: 14rem;
                            white-space: normal;
                            background-color: #fff;
                            background-clip: padding-box;
                        }
                        .descuento-reporte-columnas-wrap .col-fija-3 {
                            position: sticky;
                            left: calc(6.5rem + 14rem);
                            z-index: 8;
                            min-width: 5.5rem;
                            width: 5.5rem;
                            background-color: #fff;
                            background-clip: padding-box;
                        }
                        .descuento-reporte-columnas-wrap .col-fija-4 {
                            position: sticky;
                            left: calc(6.5rem + 14rem + 5.5rem);
                            z-index: 9;
                            min-width: 5.5rem;
                            width: 5.5rem;
                            background-color: #fff;
                            background-clip: padding-box;
                            box-shadow: 4px 0 8px -2px rgba(0, 0, 0, 0.15);
                        }
                        .descuento-reporte-columnas-wrap thead .col-fija-1,
                        .descuento-reporte-columnas-wrap thead .col-fija-2,
                        .descuento-reporte-columnas-wrap thead .col-fija-3,
                        .descuento-reporte-columnas-wrap thead .col-fija-4 {
                            z-index: 11;
                            background-color: #85C1E9 !important;
                        }
                        .descuento-reporte-columnas-wrap tbody tr:nth-of-type(odd) .col-fija-1,
                        .descuento-reporte-columnas-wrap tbody tr:nth-of-type(odd) .col-fija-2,
                        .descuento-reporte-columnas-wrap tbody tr:nth-of-type(odd) .col-fija-3,
                        .descuento-reporte-columnas-wrap tbody tr:nth-of-type(odd) .col-fija-4 {
                            background-color: #f5f5f5 !important;
                        }
                        .descuento-reporte-columnas-wrap tbody tr:nth-of-type(even) .col-fija-1,
                        .descuento-reporte-columnas-wrap tbody tr:nth-of-type(even) .col-fija-2,
                        .descuento-reporte-columnas-wrap tbody tr:nth-of-type(even) .col-fija-3,
                        .descuento-reporte-columnas-wrap tbody tr:nth-of-type(even) .col-fija-4 {
                            background-color: #fff !important;
                        }
                        .descuento-reporte-columnas-wrap tbody tr:hover .col-fija-1,
                        .descuento-reporte-columnas-wrap tbody tr:hover .col-fija-2,
                        .descuento-reporte-columnas-wrap tbody tr:hover .col-fija-3,
                        .descuento-reporte-columnas-wrap tbody tr:hover .col-fija-4 {
                            background-color: #ececec !important;
                        }
                        .descuento-reporte-columnas-wrap tfoot .col-fija-grupo-total {
                            position: sticky;
                            left: 0;
                            z-index: 10;
                            min-width: calc(6.5rem + 14rem + 5.5rem + 5.5rem);
                            background-color: #e9ecef !important;
                            box-shadow: 4px 0 8px -2px rgba(0, 0, 0, 0.15);
                        }
                    </style>

                    @if (\App\Support\Ventas\GastronomiaDescuentoReporteFiltros::debeUsarVistaColumnas($filtros ?? [], $resultado ?? null))
                        <div class="px-3 py-2 border-bottom bg-white">
                            <h5 class="mb-2">Vista consolidada por columnas</h5>
                            <p class="text-muted small mb-2">{{ $resultado['periodo_texto'] ?? '' }}</p>
                            @php
                                $resultadoColumnas = $resultado;
                                if (! empty($vista_columnas_pag)) {
                                    $resultadoColumnas = array_merge($resultado, ['vista_columnas' => $vista_columnas_pag]);
                                }
                            @endphp
                            @include('ventas.gastronomia.descuento_reporte.partials.tabla_columnas', [
                                'resultado' => $resultadoColumnas,
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                            ])
                            @if ($filas_columnas_pag ?? null)
                                <div class="d-flex flex-wrap align-items-center justify-content-between mt-2">
                                    <span class="small text-muted">
                                        Art&iacute;culos {{ $filas_columnas_pag->firstItem() }}–{{ $filas_columnas_pag->lastItem() }}
                                        de {{ $filas_columnas_pag->total() }}
                                    </span>
                                    {{ $filas_columnas_pag->links() }}
                                </div>
                            @endif
                        </div>
                    @else
                        @forelse ($bloques_vista ?? [] as $bloque)
                            <div class="px-3 py-2 border-bottom bg-white">
                                <h5 class="mb-1">
                                    {{ $bloque['codigo'] ?? '' }} — {{ $bloque['nombre'] ?? '' }}
                                </h5>
                                <p class="text-muted small mb-2">{{ $resultado['periodo_texto'] ?? '' }}</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered table-hover mb-0 tabla-descuento-reporte">
                                        @include('ventas.gastronomia.descuento_reporte.partials.tabla_bloque', [
                                            'bloque' => $bloque,
                                            'puede_ver_articulo' => $puede_ver_articulo ?? false,
                                        ])
                                    </table>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-center text-muted">
                                No hay ventas con los criterios indicados en el per&iacute;odo seleccionado.
                            </div>
                        @endforelse
                        @if ($filas_bloque_pag ?? null)
                            <div class="px-3 py-2 border-bottom bg-white d-flex flex-wrap align-items-center justify-content-between">
                                <span class="small text-muted">
                                    Art&iacute;culos {{ $filas_bloque_pag->firstItem() }}–{{ $filas_bloque_pag->lastItem() }}
                                    de {{ $filas_bloque_pag->total() }}
                                </span>
                                {{ $filas_bloque_pag->links() }}
                            </div>
                        @elseif ($bloques_pag ?? null)
                            <div class="px-3 py-2 border-bottom bg-white d-flex flex-wrap align-items-center justify-content-between">
                                <span class="small text-muted">
                                    @php
                                        $etiquetaPagBloques = match ($filtros['agrupar_por'] ?? 'codigo_descuento') {
                                            'cliente_descuento' => 'Clientes',
                                            'mozo_descuento' => 'Mozos',
                                            'cliente_vip' => 'Clientes VIP',
                                            default => 'Descuentos',
                                        };
                                    @endphp
                                    {{ $etiquetaPagBloques }}
                                    {{ $bloques_pag->firstItem() }}–{{ $bloques_pag->lastItem() }}
                                    de {{ $bloques_pag->total() }}
                                </span>
                                {{ $bloques_pag->links() }}
                            </div>
                        @endif
                    @endif

                    @if (! empty($resultado['totales'] ?? []))
                        <div class="px-3 py-3 border-top bg-light">
                            @include('ventas.gastronomia.descuento_reporte.partials.totales_colapsables', [
                                'resultado' => $resultado,
                                'empresa_nombre' => $empresa_texto ?? '',
                                'filtros' => $filtros ?? [],
                            ])
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'descuento-reporte-exportando-overlay',
    'tituloId' => 'descuento-reporte-exportando-titulo',
    'subtituloId' => 'descuento-reporte-exportando-subtitulo',
    'titulo' => 'Generando exportación…',
    'subtitulo' => 'Por favor espere. El Excel puede tardar varios minutos según el volumen.',
])
@include('ventas.gastronomia.descuento_reporte.partials.modal_facturas_bloque')
@include('includes.stock.modalconsultadescuento')
@include('includes.ventas.modalconsultacliente')
@include('includes.stock.modalconsultamozo')
@include('includes.ventas.modalconsultaclientevip')
@endsection
