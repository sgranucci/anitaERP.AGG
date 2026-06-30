@extends("theme.$theme.layout")
@section('titulo')
    Informe de requisiciones de compra
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_dual.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion_reporte/filtro.js') }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Informe de requisiciones de compra</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('reporte_requisicion_compras') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_requisicion_compras') }}" id="form-requisicion-compras-reporte" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Informe de l&iacute;neas de requisici&oacute;n (formato Anita <em>l-reqmae</em>) con totales por usuario, art&iacute;culo o centro de costo.
                        Agrupaciones colapsables en pantalla. Filtre por usuario o centro de costo con <kbd>F1</kbd> o la lupa del campo.
                        Por defecto lista requisiciones <strong>en compras</strong> del mes en curso.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    <style>
                        #form-requisicion-compras-reporte .rc-campo-fecha { max-width: 11.5rem; }
                        #form-requisicion-compras-reporte .rc-campo-corto { max-width: 10rem; }
                    </style>

                    @include('includes.reportes.asignacion_empresas_dual', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'reporte_clave' => 'requisicion_compras_reporte',
                        'mostrar_consolidar' => false,
                        'col_label' => 'col-lg-2 col-form-label text-right pr-2',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde"
                                class="form-control rc-campo-fecha"
                                value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }}">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta"
                                class="form-control rc-campo-fecha"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                            <small class="form-text text-muted">Mes en curso por defecto. Vac&iacute;o = sin tope.</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="agrupacion" class="{{ $colLabel }}">Agrupar por</label>
                        <div class="{{ $colInput }}">
                            <select name="agrupacion" id="agrupacion" class="form-control rc-campo-corto">
                                @foreach ($opciones_agrupacion ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['agrupacion'] ?? 'usuario') === $opcion['valor'])>
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
                        <label for="estado_requisicion" class="{{ $colLabel }}">Estado req.</label>
                        <div class="{{ $colInput }}">
                            <select name="estado_requisicion" id="estado_requisicion" class="form-control">
                                @foreach ($opciones_estado ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['estado_requisicion'] ?? 'en_compras') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="urgente" class="{{ $colLabel }}">Urgencia</label>
                        <div class="{{ $colInput }}">
                            <select name="urgente" id="urgente" class="form-control">
                                @foreach ($opciones_urgente ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['urgente'] ?? 'todas') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="contratacion" class="{{ $colLabel }}">Contrataci&oacute;n</label>
                        <div class="{{ $colInput }}">
                            <select name="contratacion" id="contratacion" class="form-control">
                                @foreach ($opciones_contratacion ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['contratacion'] ?? 'todas') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row" id="rc-reporte-requisicion-campo">
                        <label for="requisicion_desde" class="{{ $colLabel }}">Desde requis.</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="requisicion_desde" id="requisicion_desde"
                                class="form-control rc-campo-corto codigonumero-desde"
                                placeholder="100,105 o 100/110" autocomplete="off"
                                value="{{ $filtros['requisicion_desde'] ?? '' }}">
                        </div>
                        <label for="requisicion_hasta" class="{{ $colLabel }}">Hasta requis.</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="requisicion_hasta" id="requisicion_hasta"
                                class="form-control rc-campo-corto codigonumero-hasta"
                                placeholder="110 (rango)" autocomplete="off"
                                value="{{ $filtros['requisicion_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="usuarios" class="{{ $colLabel }}">Usuarios</label>
                        <div class="{{ $colInput }}">
                            <div id="rc-reporte-usuario-campo">
                                <div class="input-group mb-1">
                                    <input type="text" name="usuarios" id="usuarios" class="form-control codigousuario"
                                        placeholder="Vac&iacute;o = todos; 2,5,8" autocomplete="off"
                                        title="F1 o lupa: consulta usuarios"
                                        value="{{ $filtros['usuarios'] ?? '' }}">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta usuarios (F1)"
                                            class="btn btn-outline-secondary consultausuario-rc tooltipsC">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control metausuario" readonly
                                    value="{{ $meta_usuarios ?? 'Todos los usuarios' }}">
                            </div>
                        </div>
                        <label class="{{ $colLabel }}">Centros de costo</label>
                        <div class="{{ $colInput }}">
                            <div id="rc-reporte-centrocosto-campo">
                                <div class="input-group mb-1">
                                    <input type="text" name="centrocostos_codigo" id="centrocostos_codigo"
                                        class="form-control codigocentrocosto"
                                        placeholder="Vac&iacute;o = todos; 85,91,96" autocomplete="off"
                                        title="F1 o lupa: consulta centros de costo"
                                        value="{{ $filtros['centrocostos_codigo'] ?? '' }}">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta centros de costo (F1)"
                                            class="btn btn-outline-secondary consultacentrocosto-rc tooltipsC">
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
                                <button type="button" class="btn btn-outline-secondary btn-sm ml-1" id="rc-reporte-toggle-grupos" title="Colapsar o expandir todos los grupos">
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
                                &middot; <strong>Requisiciones:</strong> {{ (int) ($resultado['totales']['total_requisiciones'] ?? 0) }}
                                &middot; <strong>Cantidad:</strong> {{ number_format((float) ($resultado['totales']['total_cantidad'] ?? 0), 0, ',', '.') }}
                                &middot; <strong>Pendiente:</strong> {{ number_format((float) ($resultado['totales']['total_pendiente'] ?? 0), 0, ',', '.') }}
                                &middot; <strong>Importe:</strong> {{ number_format((float) ($resultado['totales']['total_importe'] ?? 0), 2, ',', '.') }}
                            @endif
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_reporte_requisicion_compras',
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
                        #tabla-requisicion-compras-reporte thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-requisicion-compras-reporte thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: 0.68rem; }
                        #tabla-requisicion-compras-reporte tbody td { font-size: 0.68rem; vertical-align: middle; }
                        #tabla-requisicion-compras-reporte .req-reporte-grupo-detalle.req-reporte-colapsado { display: none; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-requisicion-compras-reporte" class="table table-striped table-bordered table-hover table-sm mb-0">
                            @include('compras.requisicion_reporte.partials.tabla_datos', [
                                'filas' => $filasVista ?? [],
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                                'puede_ver_requisicion' => $puede_ver_requisicion ?? false,
                                'puede_ver_centrocosto' => $puede_ver_centrocosto ?? false,
                                'puede_ver_ordencompra' => $puede_ver_ordencompra ?? false,
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
@endsection
