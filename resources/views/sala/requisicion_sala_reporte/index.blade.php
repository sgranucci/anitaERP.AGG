@extends("theme.$theme.layout")
@section('titulo')
    Requisiciones de SALA
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/sala/requisicion_sala_reporte/filtro.js') }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Requisiciones de SALA</h3>
                <a href="{{ route('reporte_requisicion_sala') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                    <i class="fa fa-eraser"></i> Limpiar
                </a>
            </div>
            <form method="get" action="{{ route('reporte_requisicion_sala') }}" id="form-requisicion-sala-reporte" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Listado de requisiciones de sala agrupadas por n&uacute;mero de requisici&oacute;n o por usuario.
                        <strong>Todas las requisiciones:</strong> deje vac&iacute;os Desde y Hasta.
                        <strong>Puntuales:</strong> en Desde indique n&uacute;meros separados por coma (ej. <strong>100,105,110</strong>).
                        <strong>Rango:</strong> Desde y Hasta o atajo <strong>100/110</strong> en Desde.
                        <strong>Usuarios:</strong> vac&iacute;o = todos; el modal agrega IDs a la lista.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    <style>
                        #form-requisicion-sala-reporte .rs-campo-fecha { max-width: 11.5rem; }
                        #form-requisicion-sala-reporte .rs-campo-agrupacion { max-width: 9.5rem; }
                        #form-requisicion-sala-reporte .rs-campo-requisicion { max-width: 10rem; }
                    </style>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'requisicion_sala_reporte',
                        'mostrar_consolidar' => true,
                        'col_label' => 'col-lg-2 col-form-label text-right pr-2',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde"
                                class="form-control rs-campo-fecha"
                                value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }}">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta"
                                class="form-control rs-campo-fecha"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                            <small class="form-text text-muted">Mes en curso por defecto. Vac&iacute;o = sin tope.</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="agrupacion" class="{{ $colLabel }}">Agrupar por</label>
                        <div class="{{ $colInput }}">
                            <select name="agrupacion" id="agrupacion" class="form-control rs-campo-agrupacion">
                                @foreach ($opciones_agrupacion ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['agrupacion'] ?? 'requisicion') === $opcion['valor'])>
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

                    <div class="form-group row" id="rs-reporte-requisicion-campo">
                        <label for="requisicion_desde" class="{{ $colLabel }}">Desde requis.</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="requisicion_desde" id="requisicion_desde"
                                class="form-control rs-campo-requisicion codigonumero-desde"
                                placeholder="100,105 o 100/110" autocomplete="off"
                                value="{{ $filtros['requisicion_desde'] ?? '' }}">
                        </div>
                        <label for="requisicion_hasta" class="{{ $colLabel }}">Hasta requis.</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="requisicion_hasta" id="requisicion_hasta"
                                class="form-control rs-campo-requisicion codigonumero-hasta"
                                placeholder="110 (rango)" autocomplete="off"
                                value="{{ $filtros['requisicion_hasta'] ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row mt-n2">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <small class="text-muted">
                                Vac&iacute;o = todas. Lista: <strong>100,105,110</strong>.
                                Rango: <strong>100/110</strong> en Desde (se completa Hasta) o Desde <strong>100</strong> y Hasta <strong>110</strong>.
                            </small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="usuarios" class="{{ $colLabel }}">Usuarios</label>
                        <div class="{{ $colInput }}">
                            <div id="rs-reporte-usuario-campo">
                                <div class="input-group mb-1">
                                    <input type="text" name="usuarios" id="usuarios" class="form-control codigousuario"
                                        placeholder="Vac&iacute;o = todos; 2,5,8" autocomplete="off"
                                        value="{{ $filtros['usuarios'] ?? '' }}">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta usuarios"
                                            class="btn btn-outline-secondary consultausuario-rs tooltipsC">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control metausuario" readonly
                                    value="{{ $meta_usuarios ?? 'Todos los usuarios' }}">
                            </div>
                        </div>
                        <label for="estado_linea" class="{{ $colLabel }}">Estado &iacute;tem</label>
                        <div class="{{ $colInput }}">
                            <select name="estado_linea" id="estado_linea" class="form-control">
                                @foreach ($opciones_estado_linea ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['estado_linea'] ?? 'todos') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm">
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
                            @if (! empty($subtitulo_estado))
                                · <strong>{{ $subtitulo_estado }}</strong>
                            @endif
                            @if (! empty($resultado['totales']))
                                · <strong>Requisiciones:</strong> {{ (int) ($resultado['totales']['total_requisiciones'] ?? 0) }}
                                · <strong>Cantidad:</strong> {{ number_format((float) ($resultado['totales']['total_cantidad'] ?? 0), 0, ',', '.') }}
                                · <strong>Pendiente:</strong> {{ number_format((float) ($resultado['totales']['total_pendiente'] ?? 0), 0, ',', '.') }}
                            @endif
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_reporte_requisicion_sala',
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
                        #tabla-requisicion-sala-reporte thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-requisicion-sala-reporte thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: 0.72rem; }
                        #tabla-requisicion-sala-reporte tbody td { font-size: 0.72rem; vertical-align: middle; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-requisicion-sala-reporte" class="table table-striped table-bordered table-hover table-sm mb-0">
                            @include('sala.requisicion_sala_reporte.partials.tabla_datos', [
                                'filas' => $filasVista ?? [],
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                                'puede_ver_requisicion' => $puede_ver_requisicion ?? false,
                                'puede_ver_centrocosto' => $puede_ver_centrocosto ?? false,
                                'para_pdf' => false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $filas->hasPages())
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} filas
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
@endsection
