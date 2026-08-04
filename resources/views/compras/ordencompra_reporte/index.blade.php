@extends("theme.$theme.layout")
@section('titulo')
    Informe de órdenes de compra
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{asset('assets/pages/scripts/compras/proveedor/consulta.js')}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra_reporte/filtro.js') }}"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'oc-reporte-overlay',
    'tituloId' => 'oc-reporte-overlay-titulo',
    'subtituloId' => 'oc-reporte-overlay-subtitulo',
    'titulo' => 'Consultando pedidos…',
    'subtitulo' => 'Puede demorar según el período y el volumen. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Informe de &oacute;rdenes de compra</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('reporte_ordencompra') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_ordencompra') }}" id="form-ordencompra-reporte" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Informe de l&iacute;neas de pedidos a proveedores (formato Anita <em>l-pedprov</em>) con totales por pedido, proveedor, art&iacute;culo, requisici&oacute;n, partida o CAPEX.
                        Agrupaciones colapsables en pantalla. Filtre por proveedor, usuario o centro de costo con <kbd>F1</kbd> o la lupa del campo.
                        Por defecto lista OC <strong>activas pendientes de recepci&oacute;n</strong> del mes en curso.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    <style>
                        #form-ordencompra-reporte .oc-campo-fecha { max-width: 11.5rem; }
                        #form-ordencompra-reporte .oc-campo-corto { max-width: 10rem; }
                    </style>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'ordencompra_compras_reporte',
                        'mostrar_consolidar' => true,
                        'col_label' => 'col-lg-2 col-form-label text-right pr-2',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde"
                                class="form-control oc-campo-fecha"
                                value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }}">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta"
                                class="form-control oc-campo-fecha"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                            <small class="form-text text-muted">Mes en curso por defecto. Vac&iacute;o = sin tope.</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="agrupacion" class="{{ $colLabel }}">Agrupar por</label>
                        <div class="{{ $colInput }}">
                            <select name="agrupacion" id="agrupacion" class="form-control">
                                @foreach ($opciones_agrupacion ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['agrupacion'] ?? 'pedido') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="modo_listado" class="{{ $colLabel }}">Salida</label>
                        <div class="{{ $colInput }}">
                            <select name="modo_listado" id="modo_listado" class="form-control">
                                @foreach ($opciones_modo_listado ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['modo_listado'] ?? 'movimientos') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="estado_oc" class="{{ $colLabel }}">Estado OC</label>
                        <div class="{{ $colInput }}">
                            <select name="estado_oc" id="estado_oc" class="form-control">
                                @foreach ($opciones_estado ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['estado_oc'] ?? 'activos') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="pendiente" class="{{ $colLabel }}">Pendiente</label>
                        <div class="{{ $colInput }}">
                            <select name="pendiente" id="pendiente" class="form-control">
                                @foreach ($opciones_pendiente ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['pendiente'] ?? 'pendientes') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="anticipada" class="{{ $colLabel }}">Anticipadas</label>
                        <div class="{{ $colInput }}">
                            <select name="anticipada" id="anticipada" class="form-control">
                                @foreach ($opciones_anticipada ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['anticipada'] ?? 'todas') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row" id="oc-reporte-ordencompra-campo">
                        <label for="ordencompra_desde" class="{{ $colLabel }}">Desde OC</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="ordencompra_desde" id="ordencompra_desde"
                                class="form-control oc-campo-corto codigonumero-desde"
                                placeholder="100,105 o 100/110" autocomplete="off"
                                value="{{ $filtros['ordencompra_desde'] ?? '' }}">
                        </div>
                        <label for="ordencompra_hasta" class="{{ $colLabel }}">Hasta OC</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="ordencompra_hasta" id="ordencompra_hasta"
                                class="form-control oc-campo-corto codigonumero-hasta"
                                placeholder="110 (rango)" autocomplete="off"
                                value="{{ $filtros['ordencompra_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="proveedores" class="{{ $colLabel }}">Proveedores</label>
                        <div class="{{ $colInput }}">
                            <div id="oc-reporte-proveedor-campo">
                                <div class="input-group mb-1">
                                    <input type="text" name="proveedores" id="proveedores" class="form-control codigoproveedor"
                                        placeholder="Vac&iacute;o = todos; c&oacute;digos separados por coma" autocomplete="off"
                                        title="F1 o lupa: consulta proveedores"
                                        value="{{ $filtros['proveedores'] ?? '' }}">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta proveedores (F1)"
                                            class="btn btn-outline-secondary consultaproveedor-oc tooltipsC">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control metaproveedor" readonly
                                    value="{{ $meta_proveedores ?? 'Todos los proveedores' }}">
                            </div>
                        </div>
                        <label for="usuarios" class="{{ $colLabel }}">Usuarios</label>
                        <div class="{{ $colInput }}">
                            <div id="oc-reporte-usuario-campo">
                                <div class="input-group mb-1">
                                    <input type="text" name="usuarios" id="usuarios" class="form-control codigousuario"
                                        placeholder="Vac&iacute;o = todos; 2,5,8" autocomplete="off"
                                        title="F1 o lupa: consulta usuarios"
                                        value="{{ $filtros['usuarios'] ?? '' }}">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta usuarios (F1)"
                                            class="btn btn-outline-secondary consultausuario-oc tooltipsC">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control metausuario" readonly
                                    value="{{ $meta_usuarios ?? 'Todos los usuarios' }}">
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Centros de costo</label>
                        <div class="{{ $colInput }}">
                            <div id="oc-reporte-centrocosto-campo">
                                <div class="input-group mb-1">
                                    <input type="text" name="centrocostos_codigo" id="centrocostos_codigo"
                                        class="form-control codigocentrocosto"
                                        placeholder="Vac&iacute;o = todos; 85,91,96" autocomplete="off"
                                        title="F1 o lupa: consulta centros de costo"
                                        value="{{ $filtros['centrocostos_codigo'] ?? '' }}">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta centros de costo (F1)"
                                            class="btn btn-outline-secondary consultacentrocosto-oc tooltipsC">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control metacentrocosto" readonly
                                    value="{{ $meta_centrocostos ?? 'Todos los centros de costo' }}">
                            </div>
                            <small class="form-text text-muted">C&oacute;digos separados por coma. Filtra CC cabecera o destino de la l&iacute;nea.</small>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            @if ($consultado ?? false)
                                <button type="button" class="btn btn-outline-secondary btn-sm ml-1" id="oc-reporte-toggle-grupos" title="Colapsar o expandir todos los grupos">
                                    <i class="fa fa-compress"></i> Colapsar / expandir grupos
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado ?? false)
                <div class="card-body p-0 border-top">
                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-0 small">
                            <strong>Per&iacute;odo:</strong> {{ $periodo_texto ?? '' }}
                            @if (! empty($subtitulo_estado))
                                &middot; <strong>{{ $subtitulo_estado }}</strong>
                            @endif
                            @if (! empty($resultado['totales']))
                                &middot; <strong>OC:</strong> {{ (int) ($resultado['totales']['total_ordenes'] ?? 0) }}
                                &middot; <strong>Cantidad:</strong> {{ number_format((float) ($resultado['totales']['total_cantidad'] ?? 0), 0, ',', '.') }}
                                &middot; <strong>Pendiente:</strong> {{ number_format((float) ($resultado['totales']['total_pendiente'] ?? 0), 0, ',', '.') }}
                                &middot; <strong>Tot.pend.:</strong> {{ number_format((float) ($resultado['totales']['total_importe_pendiente'] ?? 0), 2, ',', '.') }}
                                &middot; <strong>Tot.OC:</strong> {{ number_format((float) ($resultado['totales']['total_importe_oc'] ?? 0), 2, ',', '.') }}
                            @endif
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_reporte_ordencompra',
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
                        #tabla-ordencompra-reporte thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-ordencompra-reporte thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: 0.65rem; }
                        #tabla-ordencompra-reporte tbody td { font-size: 0.65rem; vertical-align: middle; }
                        #tabla-ordencompra-reporte .oc-reporte-grupo-detalle.oc-reporte-colapsado { display: none; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-ordencompra-reporte" class="table table-striped table-bordered table-hover table-sm mb-0">
                            @include('compras.ordencompra_reporte.partials.tabla_datos', [
                                'filas' => $filasVista ?? [],
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                                'puede_ver_requisicion' => $puede_ver_requisicion ?? false,
                                'puede_ver_centrocosto' => $puede_ver_centrocosto ?? false,
                                'puede_ver_ordencompra' => $puede_ver_ordencompra ?? false,
                                'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                                'puede_ver_capex' => $puede_ver_capex ?? false,
                                'puede_ver_recepcion' => $puede_ver_recepcion ?? false,
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
@include('includes.admin.modalconsultausuario')
@include('includes.contable.modalconsultacentrocosto')
@include('includes.compras.modalconsultaproveedor')
@endsection
