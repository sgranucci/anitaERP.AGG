@extends("theme.$theme.layout")
@section('titulo')
    Ejecutar {{ $data->codigo }} — {{ $data->titulo }}
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/sueldos/liquidacion/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/reporte_definible/ejecutar.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'rsd-overlay',
    'tituloId' => 'rsd-overlay-titulo',
    'subtituloId' => 'rsd-overlay-subtitulo',
    'titulo' => 'Generando listado…',
    'subtitulo' => 'Puede demorar según la liquidación. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ejecutar: {{ $data->codigo }} — {{ $data->titulo }}</h3>
                <div class="card-tools">
                    <a href="{{ route('editar_reporte_sueldos_definible', ['id' => $data->id]) }}" class="btn btn-outline-primary btn-sm mr-1">
                        <i class="fa fa-edit"></i> Editar
                    </a>
                    <a href="{{ route('reporte_sueldos_definible') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if($variantes->isNotEmpty())
                    <div class="mb-2">
                        <span class="small text-muted mr-2">Variantes:</span>
                        @foreach($variantes as $variante)
                            <a class="btn btn-outline-info btn-xs mr-1"
                               href="{{ route('ejecutar_reporte_sueldos_definible', ['id' => $data->id, 'consultar' => 1, 'variante_id' => $variante->id]) }}">
                                {{ $variante->nombre }}{{ $variante->compartida ? ' · compartida' : '' }}
                            </a>
                        @endforeach
                        @if(!empty($varianteAplicada) && !empty($varianteAplicada->pivot_spec))
                            <a class="btn btn-outline-primary btn-xs ml-1"
                               href="{{ route('dashboard_reporte_sueldos_definible', ['id' => $data->id]) }}">Pivot 2D</a>
                        @endif
                    </div>
                @endif
                <form method="get" action="{{ route('ejecutar_reporte_sueldos_definible', ['id' => $data->id]) }}" id="form-ejecutar-rsd" class="mb-3">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="consultar" value="1">
                    @if(!empty($varianteAplicada))
                        <input type="hidden" name="variante_id" value="{{ $varianteAplicada->id }}">
                        <input type="hidden" name="pivot_spec_json" value="{{ e(json_encode($varianteAplicada->pivot_spec ?? [])) }}">
                    @endif
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Origen</label>
                            <select name="origen" class="form-control form-control-sm">
                                <option value="liquidacion" {{ ($filtrosEjec['origen'] ?? '') === 'liquidacion' ? 'selected' : '' }}>De liquidación</option>
                                <option value="abm" {{ ($filtrosEjec['origen'] ?? '') === 'abm' ? 'selected' : '' }}>De ABM empleados</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            @include('sueldos.partials.campo_consulta_liquidacion_sueldos', [
                                'inputName' => 'liquidacion_id',
                                'inputId' => 'reporte_liquidacion_id',
                                'label' => 'Liquidación',
                                'liquidacionId' => $filtrosEjec['liquidacion_id'] ?? null,
                                'numero' => $liquidacionSeleccionada->numero ?? '',
                                'descripcion' => $liquidacionSeleccionada->descripcion ?? '',
                            ])
                        </div>
                        <div class="form-group col-md-3">
                            @include('sueldos.partials.campo_consulta_liquidacion_sueldos', [
                                'inputName' => 'liquidacion_id_comparar',
                                'inputId' => 'reporte_liquidacion_comparar_id',
                                'label' => 'Comparar vs liquidación (Δ)',
                                'liquidacionId' => $filtrosEjec['liquidacion_id_comparar'] ?? null,
                                'numero' => $liquidacionCompararSeleccionada->numero ?? '',
                                'descripcion' => $liquidacionCompararSeleccionada->descripcion ?? '',
                            ])
                        </div>
                        <div class="form-group col-md-3">
                            @include('includes.form-empresa-asignada', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => $filtrosEjec['empresa_id'] ?? null,
                                'required' => false,
                                'permite_vacio' => true,
                                'opcion_vacia' => '— Empresa —',
                                'col_label' => 'col-lg-12 small mb-1',
                                'col_input' => 'col-lg-12',
                            ])
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Agrupaci&oacute;n nivel 1</label>
                            <select name="agrupacion" class="form-control form-control-sm">
                                @foreach ($agrupaciones as $k => $v)
                                    <option value="{{ $k }}" {{ ($filtrosEjec['agrupacion'] ?? '') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Nivel 2</label>
                            <select name="agrupaciones[]" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($agrupaciones as $k => $v)
                                    @if($k !== 'empleado')<option value="{{ $k }}" {{ (($filtrosEjec['agrupaciones'][1] ?? '') === $k) ? 'selected' : '' }}>{{ $v }}</option>@endif
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Nivel 3</label>
                            <select name="agrupaciones[]" class="form-control form-control-sm">
                                <option value="">—</option>
                                @foreach ($agrupaciones as $k => $v)
                                    @if($k !== 'empleado')<option value="{{ $k }}" {{ (($filtrosEjec['agrupaciones'][2] ?? '') === $k) ? 'selected' : '' }}>{{ $v }}</option>@endif
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Estado (ABM)</label>
                            <select name="filtro_estado" class="form-control form-control-sm">
                                <option value="activo" {{ ($filtrosEjec['filtro_estado'] ?? '') === 'activo' ? 'selected' : '' }}>Activo</option>
                                <option value="baja" {{ ($filtrosEjec['filtro_estado'] ?? '') === 'baja' ? 'selected' : '' }}>Baja</option>
                                <option value="todos" {{ ($filtrosEjec['filtro_estado'] ?? '') === 'todos' ? 'selected' : '' }}>Todos</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="resumido" id="resumido" value="1"
                                       {{ !empty($filtrosEjec['resumido']) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="resumido">Resumido</label>
                            </div>
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="incluir_confidencial"
                                       id="incluir_confidencial" value="1"
                                       {{ !empty($filtrosEjec['incluir_confidencial']) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="incluir_confidencial">Incluir confidencial</label>
                            </div>
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm btn-block">Consultar</button>
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit"
                                    formmethod="post"
                                    formaction="{{ route('encolar_reporte_sueldos_definible', ['id' => $data->id]) }}"
                                    class="btn btn-outline-primary btn-sm btn-block">
                                Ejecutar en cola
                            </button>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Ordenar por</label>
                            <select name="orden_columna" class="form-control form-control-sm">
                                <option value="0">Orden natural</option>
                                @foreach($data->columnas as $columna)
                                    @if($columna->contenido !== 'campo_empleado')
                                        <option value="{{ $columna->nro_columna }}" {{ (int)($filtrosEjec['orden_columna'] ?? 0) === (int)$columna->nro_columna ? 'selected' : '' }}>
                                            C{{ $columna->nro_columna }} · {{ $columna->descripcion }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Direcci&oacute;n</label>
                            <select name="orden_direccion" class="form-control form-control-sm">
                                <option value="desc" {{ ($filtrosEjec['orden_direccion'] ?? 'desc') === 'desc' ? 'selected' : '' }}>Mayor a menor</option>
                                <option value="asc" {{ ($filtrosEjec['orden_direccion'] ?? '') === 'asc' ? 'selected' : '' }}>Menor a mayor</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-1">Top N</label>
                            <input type="number" name="top_n" class="form-control form-control-sm" min="0" max="10000"
                                   value="{{ (int)($filtrosEjec['top_n'] ?? 0) }}" placeholder="0 = todos">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Columnas visibles</label>
                            <select name="columnas_visibles[]" class="form-control form-control-sm" multiple size="4">
                                @foreach($data->columnas as $columna)
                                    <option value="{{ $columna->nro_columna }}"
                                        {{ in_array((int) $columna->nro_columna, $columnasVisibles ?? [], true) ? 'selected' : '' }}>
                                        C{{ $columna->nro_columna }} · {{ $columna->descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small mb-1">Guardar como variante</label>
                            <input type="text" name="nombre" class="form-control form-control-sm" maxlength="80" placeholder="Nombre de esta vista">
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="submit"
                                    formmethod="post"
                                    formaction="{{ route('guardar_variante_reporte_sueldos_definible', ['id' => $data->id]) }}"
                                    class="btn btn-outline-info btn-sm btn-block">
                                Guardar variante
                            </button>
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" name="compartida" id="variante-compartida" value="1">
                                <label class="custom-control-label" for="variante-compartida">Compartir</label>
                            </div>
                        </div>
                    </div>
                </form>

                    @if ($resultado)
                    @php
                        $qp = array_filter([
                            'consultar' => 1,
                            'origen' => $filtrosEjec['origen'] ?? null,
                            'liquidacion_id' => $filtrosEjec['liquidacion_id'] ?? null,
                            'liquidacion_id_comparar' => $filtrosEjec['liquidacion_id_comparar'] ?? null,
                            'empresa_id' => $filtrosEjec['empresa_id'] ?? null,
                            'agrupacion' => $filtrosEjec['agrupacion'] ?? null,
                            'agrupaciones' => $filtrosEjec['agrupaciones'] ?? null,
                            'filtro_estado' => $filtrosEjec['filtro_estado'] ?? null,
                            'resumido' => !empty($filtrosEjec['resumido']) ? 1 : null,
                            'incluir_confidencial' => !empty($filtrosEjec['incluir_confidencial']) ? 1 : null,
                            'orden_columna' => $filtrosEjec['orden_columna'] ?? null,
                            'orden_direccion' => $filtrosEjec['orden_direccion'] ?? null,
                            'top_n' => $filtrosEjec['top_n'] ?? null,
                        ], fn ($v) => $v !== null && $v !== '');
                        $suffix = count($qp) ? '?'.http_build_query($qp) : '';
                    @endphp
                    <div class="mb-2">
                        <a href="{{ route('listar_reporte_sueldos_definible', ['id' => $data->id, 'formato' => 'PDF']).$suffix }}" class="btn btn-app bg-danger">
                            <i class="fas fa-file-pdf"></i> Pdf
                        </a>
                        <a href="{{ route('listar_reporte_sueldos_definible', ['id' => $data->id, 'formato' => 'EXCEL']).$suffix }}" class="btn btn-app bg-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="{{ route('listar_reporte_sueldos_definible', ['id' => $data->id, 'formato' => 'CSV']).$suffix }}" class="btn btn-app bg-warning">
                            <i class="fas fa-file-csv"></i> Csv
                        </a>
                        <span class="text-muted small ml-2">
                            {{ $resultado['meta']['cantidad_filas'] ?? 0 }} filas
                        </span>
                    </div>
                    @if($ejecucion)
                        <div class="alert alert-light border py-2">
                            Ejecuci&oacute;n auditable <strong>#{{ $ejecucion->id }}</strong>
                            &middot; {{ number_format($ejecucion->duracion_ms / 1000, 2, ',', '.') }} s
                            &middot; hash <code>{{ substr((string) $ejecucion->resultado_hash, 0, 16) }}&hellip;</code>
                            @if($ejecucion->advertencias_count)
                                &middot; <span class="badge badge-warning">{{ $ejecucion->advertencias_count }} control(es)</span>
                            @endif
                        </div>
                    @endif
                    @if (!empty($resultado['meta']['error']))
                        <div class="alert alert-warning">{{ $resultado['meta']['error'] }}</div>
                    @endif
                    @include('sueldos.reporte_definible.partials.tabla_datos', [
                        'resultado' => $resultado,
                        'pagina' => $pagina,
                        'reporteId' => $data->id,
                        'liquidacionId' => $filtrosEjec['liquidacion_id'] ?? null,
                        'puedeDrill' => true,
                        'puede_ver_empleado' => $puede_ver_empleado ?? false,
                    ])
                @endif
            </div>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultaliquidacion_sueldos')
<div class="modal fade" id="modal-drill-rsd" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de celda</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-bordered">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr><th>Concepto</th><th>Desc.</th><th>Cant.</th><th>Valor</th><th>Importe</th></tr>
                    </thead>
                    <tbody id="drill-rsd-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
