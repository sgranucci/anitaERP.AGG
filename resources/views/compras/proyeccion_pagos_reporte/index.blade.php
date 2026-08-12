@extends("theme.$theme.layout")
@section('titulo')
    Proyecci&oacute;n de pagos a proveedores
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/proyeccion_pagos_reporte/filtro.js') }}"></script>
@endsection

@section('contenido')
@php
    use App\Support\Compras\ProyeccionPagosReporteFiltros as FiltrosProy;

    $colLabel = 'col-lg-2 control-label text-right pr-2';
    $colInput = 'col-lg-4';
    $totales = $resultado['totales'] ?? [];
    $importesTotales = $totales['importes'] ?? [];
    $tramosDef = $resultado['tramos'] ?? [];
    $porMes = ($filtros['tipo_vencimiento'] ?? '') === FiltrosProy::VENCIMIENTO_MES;
    $gruposPanel = collect($panel_columnas ?? [])->groupBy('grupo');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Proyecci&oacute;n de pagos a proveedores</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.compras.boton-manual-propuesta-pago')
                    <button type="button" class="btn btn-outline-primary btn-sm mr-1 ml-1" data-toggle="modal"
                        data-target="#modalColumnasProyeccion" title="Elegir, ordenar y guardar columnas">
                        <i class="fa fa-table"></i> Columnas
                        <span class="badge badge-primary ml-1" id="proy-columnas-contador">{{ count($columnas ?? []) }}</span>
                    </button>
                    <a href="{{ route('reporte_proyeccion_pagos') }}" class="btn btn-outline-secondary btn-sm"
                        title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="get" action="{{ route('reporte_proyeccion_pagos') }}" id="form-proyeccion-pagos" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Deuda abierta de proveedores clasificada por tramos de vencimiento (formato Anita
                        <em>l-proy</em>), leyendo cuenta corriente y aplicaciones de anitaERP.
                        Elija <strong>A vencer</strong> para proyectar hacia adelante o <strong>Vencidos</strong> para
                        antig&uuml;edad de deuda. Con <kbd>F1</kbd> o la lupa consulta proveedores.
                    </p>

                    <style>
                        #form-proyeccion-pagos .proy-campo-fecha { max-width: 11.5rem; }
                        #form-proyeccion-pagos .proy-campo-corto { max-width: 10rem; }
                    </style>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'proyeccion_pagos_compras',
                        'mostrar_consolidar' => true,
                        'col_label' => 'col-lg-2 col-form-label text-right pr-2',
                    ])

                    <div class="form-group row">
                        <label for="tipo_informe" class="{{ $colLabel }}">Tipo de informe</label>
                        <div class="{{ $colInput }}">
                            <select name="tipo_informe" id="tipo_informe" class="form-control">
                                @foreach ($opciones_informe as $opcion)
                                    <option value="{{ $opcion['valor'] }}"
                                        @selected(($filtros['tipo_informe'] ?? '') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="fecha_base" class="{{ $colLabel }} requerido">Fecha base</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_base" id="fecha_base" class="form-control proy-campo-fecha"
                                value="{{ $filtros['fecha_base'] ?? '' }}" required>
                            <small class="form-text text-muted">Corte de la proyecci&oacute;n (hoy por defecto).</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="tipo_vencimiento" class="{{ $colLabel }}">Tramos</label>
                        <div class="{{ $colInput }}">
                            <select name="tipo_vencimiento" id="tipo_vencimiento" class="form-control proy-campo-corto">
                                @foreach ($opciones_vencimiento as $opcion)
                                    <option value="{{ $opcion['valor'] }}"
                                        @selected(($filtros['tipo_vencimiento'] ?? '') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="tramos_dias" class="{{ $colLabel }}">Cortes</label>
                        <div class="{{ $colInput }}">
                            <div id="proy-campo-tramos-dias" class="{{ $porMes ? 'd-none' : '' }}">
                                <input type="text" name="tramos_dias" id="tramos_dias" class="form-control"
                                    placeholder="7,15,30,60,90,120" autocomplete="off"
                                    value="{{ $filtros['tramos_dias'] ?? '' }}">
                                <small class="form-text text-muted">
                                    Hasta {{ FiltrosProy::MAX_TRAMOS }} cortes en d&iacute;as, separados por coma.
                                </small>
                            </div>
                            <div id="proy-campo-tramos-meses" class="{{ $porMes ? '' : 'd-none' }}">
                                <input type="text" name="tramos_meses" id="tramos_meses" class="form-control"
                                    placeholder="8,9,10,11,12,1" autocomplete="off"
                                    value="{{ $filtros['tramos_meses'] ?? '' }}">
                                <small class="form-text text-muted">
                                    N&uacute;meros de mes (1&ndash;12) en el orden de la proyecci&oacute;n.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="moneda_id" class="{{ $colLabel }}">Expresado en</label>
                        <div class="{{ $colInput }}">
                            <select name="moneda_id" id="moneda_id" class="form-control proy-campo-corto">
                                @foreach ($moneda_query as $moneda)
                                    <option value="{{ $moneda->id }}"
                                        @selected((int) ($filtros['moneda_id'] ?? 0) === (int) $moneda->id)>
                                        {{ $moneda->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="modo_moneda" class="{{ $colLabel }}">Cotizaci&oacute;n</label>
                        <div class="{{ $colInput }}">
                            <select name="modo_moneda" id="modo_moneda" class="form-control">
                                @foreach ($opciones_moneda as $opcion)
                                    <option value="{{ $opcion['valor'] }}"
                                        @selected(($filtros['modo_moneda'] ?? '') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row" id="proy-campo-proveedor">
                        <label for="proveedores_codigo" class="{{ $colLabel }}">Proveedores</label>
                        <div class="{{ $colInput }}">
                            <div class="input-group">
                                <input type="text" name="proveedores_codigo" id="proveedores_codigo"
                                    class="form-control codigoproveedor" placeholder="Vac&iacute;o = todos; 12,45 o 10/99"
                                    autocomplete="off" title="F1 o lupa: consulta proveedores"
                                    value="{{ $filtros['proveedores_codigo'] ?? '' }}">
                                <div class="input-group-append">
                                    <button type="button" title="Consulta proveedores (F1)"
                                        class="btn btn-outline-secondary consultaproveedor-proy">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">C&oacute;digos por coma o rango con <code>/</code>.</small>
                        </div>
                        <label for="proveedor_nombre" class="{{ $colLabel }}">Nombre contiene</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="proveedor_nombre" id="proveedor_nombre" class="form-control"
                                placeholder="Parte del nombre" autocomplete="off"
                                value="{{ $filtros['proveedor_nombre'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="agrupacion" class="{{ $colLabel }}">Clasificar por</label>
                        <div class="{{ $colInput }}">
                            <select name="agrupacion" id="agrupacion" class="form-control">
                                @foreach ($opciones_agrupacion as $opcion)
                                    <option value="{{ $opcion['valor'] }}"
                                        @selected(($filtros['agrupacion'] ?? '') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="orden" class="{{ $colLabel }}">Orden</label>
                        <div class="{{ $colInput }}">
                            <select name="orden" id="orden" class="form-control">
                                @foreach ($opciones_orden as $opcion)
                                    <option value="{{ $opcion['valor'] }}"
                                        @selected(($filtros['orden'] ?? '') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="salida" class="{{ $colLabel }}">Salida</label>
                        <div class="{{ $colInput }}">
                            <select name="salida" id="salida" class="form-control">
                                @foreach ($opciones_salida as $opcion)
                                    <option value="{{ $opcion['valor'] }}"
                                        @selected(($filtros['salida'] ?? '') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="estado_aprobacion" class="{{ $colLabel }}">Aprobaci&oacute;n</label>
                        <div class="{{ $colInput }}">
                            <select name="estado_aprobacion" id="estado_aprobacion" class="form-control">
                                @foreach ($opciones_aprobacion as $opcion)
                                    <option value="{{ $opcion['valor'] }}"
                                        @selected(($filtros['estado_aprobacion'] ?? '') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="collapse @if (! empty($filtros['abre_anterior']) || ($filtros['tipotransaccion_ids'] ?? []) !== [] || ! empty($filtros['fecha_carga_desde']) || ($filtros['condiciones_compensar'] ?? '') !== '') show @endif"
                        id="proy-filtros-avanzados">
                        <div class="form-group row">
                            <label class="{{ $colLabel }}">Ventana abierta</label>
                            <div class="{{ $colInput }}">
                                <div class="custom-control custom-checkbox mb-1">
                                    <input type="hidden" name="abre_anterior" value="0">
                                    <input type="checkbox" class="custom-control-input" id="abre_anterior"
                                        name="abre_anterior" value="1" @checked(! empty($filtros['abre_anterior']))>
                                    <label class="custom-control-label" for="abre_anterior">
                                        Abrir columna de saldo fuera de la ventana
                                    </label>
                                </div>
                                <input type="number" min="0" max="999" name="dias_anterior" id="dias_anterior"
                                    class="form-control proy-campo-corto"
                                    value="{{ $filtros['dias_anterior'] ?? 30 }}">
                                <small class="form-text text-muted">
                                    D&iacute;as desde la fecha base que se muestran aparte.
                                </small>
                            </div>
                            <label for="condiciones_compensar" class="{{ $colLabel }}">Cond. a compensar</label>
                            <div class="{{ $colInput }}">
                                <input type="text" name="condiciones_compensar" id="condiciones_compensar"
                                    class="form-control" placeholder="C&oacute;digos de condici&oacute;n de pago"
                                    autocomplete="off" value="{{ $filtros['condiciones_compensar'] ?? '' }}">
                                <small class="form-text text-muted">
                                    Se informan en <em>A compensar</em> y no suman al total adeudado.
                                </small>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="tipotransaccion_ids" class="{{ $colLabel }}">Tipos de comprobante</label>
                            <div class="{{ $colInput }}">
                                <select name="tipotransaccion_ids[]" id="tipotransaccion_ids" class="form-control"
                                    multiple size="4">
                                    @foreach ($tipotransaccion_query as $tipo)
                                        <option value="{{ $tipo->id }}"
                                            @selected(in_array((int) $tipo->id, $filtros['tipotransaccion_ids'] ?? [], true))>
                                            {{ $tipo->abreviatura ? $tipo->abreviatura.' — ' : '' }}{{ $tipo->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Vac&iacute;o = todos.</small>
                            </div>
                            <label for="fecha_carga_desde" class="{{ $colLabel }}">Cargados desde</label>
                            <div class="{{ $colInput }}">
                                <div class="form-inline">
                                    <input type="date" name="fecha_carga_desde" id="fecha_carga_desde"
                                        class="form-control proy-campo-fecha mr-2"
                                        value="{{ $filtros['fecha_carga_desde'] ?? '' }}">
                                    <input type="time" name="hora_carga_desde" id="hora_carga_desde"
                                        class="form-control proy-campo-corto"
                                        value="{{ $filtros['hora_carga_desde'] ?? '' }}">
                                </div>
                                <small class="form-text text-muted">
                                    Solo comprobantes cargados a partir de esa fecha y hora.
                                </small>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="{{ $colLabel }}">Adelantos</label>
                            <div class="{{ $colInput }}">
                                <div class="custom-control custom-checkbox">
                                    <input type="hidden" name="incluir_adelantos" value="0">
                                    <input type="checkbox" class="custom-control-input" id="incluir_adelantos"
                                        name="incluir_adelantos" value="1"
                                        @checked(! empty($filtros['incluir_adelantos']))>
                                    <label class="custom-control-label" for="incluir_adelantos">
                                        Restar pagos a cuenta sin aplicar
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <input type="hidden" name="columnas" id="proy-columnas-config"
                                value="{{ $filtros['columnas'] ?? '' }}">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ml-1" data-toggle="collapse"
                                data-target="#proy-filtros-avanzados">
                                <i class="fa fa-sliders-h"></i> Filtros avanzados
                            </button>
                            @if ($consultado ?? false)
                                <button type="button" class="btn btn-outline-secondary btn-sm ml-1"
                                    id="proy-toggle-grupos" title="Colapsar o expandir todos los grupos">
                                    <i class="fa fa-compress"></i> Colapsar / expandir
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado ?? false)
                <div class="card-body p-0 border-top">
                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-1 small">{{ $subtitulo ?? '' }}</p>
                        <div class="d-flex flex-wrap">
                            @php
                                $kpis = [
                                    ['Total adeudado', $importesTotales['total_adeudado'] ?? 0, 'text-dark'],
                                    ['Aprobado', $importesTotales['total_aprobado'] ?? null, 'text-success'],
                                    ['Pendiente de aprobación', $importesTotales['pend_aprobacion'] ?? null, 'text-warning'],
                                    ['Adelantos', $importesTotales['adelantos'] ?? null, 'text-info'],
                                    ['A compensar', $importesTotales['a_compensar'] ?? null, 'text-secondary'],
                                ];
                            @endphp
                            @foreach ($kpis as [$etiqueta, $valor, $clase])
                                @continue($valor === null)
                                <div class="mr-4 mb-1">
                                    <small class="text-muted d-block">{{ $etiqueta }}</small>
                                    <span class="{{ $clase }} font-weight-bold">
                                        {{ number_format((float) $valor, 2, ',', '.') }}
                                    </span>
                                </div>
                            @endforeach
                            <div class="mr-4 mb-1">
                                <small class="text-muted d-block">Proveedores</small>
                                <span class="font-weight-bold">{{ (int) ($totales['proveedores'] ?? 0) }}</span>
                            </div>
                            <div class="mb-1">
                                <small class="text-muted d-block">Movimientos</small>
                                <span class="font-weight-bold">{{ (int) ($totales['cantidad'] ?? 0) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_reporte_proyeccion_pagos',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        <div class="small text-muted">
                            Tramos:
                            @foreach ($tramosDef['tramos'] ?? [] as $tramo)
                                <span class="badge badge-light border">{{ $tramo['etiqueta'] }}</span>
                            @endforeach
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
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1"
                                    style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    <style>
                        #tabla-proyeccion-pagos thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-proyeccion-pagos thead th {
                            font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: 0.68rem;
                            position: sticky; top: 0; z-index: 2; background-color: #85C1E9;
                        }
                        #tabla-proyeccion-pagos tbody td { font-size: 0.68rem; vertical-align: middle; white-space: nowrap; }
                        #tabla-proyeccion-pagos .proy-grupo-detalle.proy-colapsado,
                        #tabla-proyeccion-pagos .proy-grupo-spacer.proy-colapsado { display: none; }
                        #tabla-proyeccion-pagos .proy-total-general td { border-top: 2px solid #5499c7; }
                    </style>
                    <div class="table-responsive" style="max-height: 70vh;">
                        <table id="tabla-proyeccion-pagos"
                            class="table table-striped table-bordered table-hover table-sm mb-0">
                            @include('compras.proyeccion_pagos_reporte.partials.tabla_datos', [
                                'filas' => $filasVista ?? [],
                                'columnas' => $columnas ?? [],
                                'para_pdf' => false,
                                'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                                'puede_ver_comprobante' => $puede_ver_comprobante ?? false,
                                'puede_ver_ordencompra' => $puede_ver_ordencompra ?? false,
                                'puede_ver_requisicion' => $puede_ver_requisicion ?? false,
                                'puede_ver_concepto' => $puede_ver_concepto ?? false,
                                'puede_ver_cuentacontable' => $puede_ver_cuentacontable ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $filas->hasPages())
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                Mostrando {{ $filas->firstItem() }}&ndash;{{ $filas->lastItem() }}
                                de {{ $filas->total() }} filas
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

<div class="modal fade" id="modalColumnasProyeccion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configuraci&oacute;n de columnas</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center mb-3">
                    <div class="mr-3 mb-2">
                        <label class="small text-muted mb-1 d-block">Vistas predefinidas</label>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary proy-preset" data-preset="ejecutivo">
                                Ejecutiva
                            </button>
                            <button type="button" class="btn btn-outline-primary proy-preset" data-preset="tesoreria">
                                Tesorer&iacute;a
                            </button>
                            <button type="button" class="btn btn-outline-primary proy-preset" data-preset="analisis">
                                An&aacute;lisis de origen
                            </button>
                            <button type="button" class="btn btn-outline-primary proy-preset" data-preset="cashflow">
                                Cash flow
                            </button>
                            <button type="button" class="btn btn-outline-primary proy-preset" data-preset="completo">
                                Todo
                            </button>
                        </div>
                    </div>
                    <div class="mr-3 mb-2 flex-grow-1">
                        <label for="proy-buscar-columna" class="small text-muted mb-1 d-block">Buscar columna</label>
                        <input type="text" id="proy-buscar-columna" class="form-control form-control-sm"
                            placeholder="Nombre de columna&hellip;" autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label class="small text-muted mb-1 d-block">&nbsp;</label>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="proy-columnas-reset">
                            <i class="fa fa-undo"></i> Restaurar por defecto
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-7">
                        <h6 class="text-muted">Disponibles</h6>
                        <div id="proy-columnas-grupos" style="max-height: 52vh; overflow-y: auto;">
                            @foreach ($gruposPanel as $grupo => $columnasGrupo)
                                <div class="card card-outline card-info mb-2 proy-grupo-columnas">
                                    <div class="card-header py-1">
                                        <h6 class="card-title mb-0">{{ $grupo }}</h6>
                                        <div class="card-tools">
                                            <button type="button" class="btn btn-tool btn-sm proy-grupo-todas"
                                                title="Activar todas del grupo">
                                                <i class="fa fa-check-double"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body py-2">
                                        @foreach ($columnasGrupo as $columna)
                                            <div class="custom-control custom-checkbox proy-columna-item"
                                                data-etiqueta="{{ mb_strtolower($columna['etiqueta']) }}">
                                                <input type="checkbox" class="custom-control-input proy-columna-check"
                                                    id="proy-col-{{ $columna['clave'] }}"
                                                    data-clave="{{ $columna['clave'] }}"
                                                    data-etiqueta="{{ $columna['etiqueta'] }}"
                                                    @checked(! empty($columna['activa']))
                                                    @disabled(! empty($columna['fija']))>
                                                <label class="custom-control-label"
                                                    for="proy-col-{{ $columna['clave'] }}">
                                                    {{ $columna['etiqueta'] }}
                                                    @if (! empty($columna['fija']))
                                                        <span class="badge badge-secondary ml-1">fija</span>
                                                    @endif
                                                    @if (! empty($columna['ayuda']))
                                                        <i class="fa fa-info-circle text-muted ml-1"
                                                            title="{{ $columna['ayuda'] }}"></i>
                                                    @endif
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <h6 class="text-muted">
                            Orden de columnas
                            <small class="text-muted">(arrastr&aacute; o us&aacute; las flechas)</small>
                        </h6>
                        <ul class="list-group" id="proy-columnas-orden" style="max-height: 52vh; overflow-y: auto;"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="proy-columnas-aplicar">
                    <i class="fa fa-check"></i> Aplicar y consultar
                </button>
            </div>
        </div>
    </div>
</div>

@include('includes.compras.modalconsultaproveedor')
@endsection
