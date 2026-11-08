@extends("theme.$theme.layout")

@section('titulo')
    Kardex de valores
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tesoreria-crown-overlay',
    'tituloId' => 'tesoreria-crown-overlay-titulo',
    'subtituloId' => 'tesoreria-crown-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo.',
])
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-exchange mr-1"></i> Kardex unificado de valores</h3>
                <div class="card-tools">
                    <span class="small text-white-50 mr-2" title="Maestros: tipo de valor + valor + depósito. CashBox confirmado entra como INGR de bóveda.">Ledger de stock (bóveda / pre-depósito / caja chica)</span>
                    @if ($jornada)
                        <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}" class="btn btn-outline-info btn-sm tc-tool">
                            <i class="fa fa-desktop"></i> Escritorio
                        </a>
                    @elseif ($sala && can('listar-tesoreria-crown-boveda', false))
                        <a href="{{ route('tesoreria_crown_boveda', array_filter([
                            'sala_id' => $sala->id,
                            'deposito_id' => ($filtros['deposito_id'] ?? 0) ?: null,
                        ])) }}" class="btn btn-outline-info btn-sm tc-tool">
                            <i class="fa fa-reply-all"></i> Volver a bóveda
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_kardex_valores',
                'exportRuta' => ($sala && ($filtros['consultar'] ?? false)) ? 'listar_tesoreria_crown_kardex_valores' : null,
                'exportQueryparams' => array_merge($filtrosQuery ?? [], [
                    'columnas' => implode(',', $columnasVisibles ?? []),
                ]),
            ])
            <div class="card-body">
                @if (! $sala)
                    <p>Elegí una sala.</p>
                @else
                    <div class="lw-workbench mb-2" data-form="#form-filtros-tesoreria-crown-kardex">
                        <form method="get" action="{{ route('tesoreria_crown_kardex_valores') }}" id="form-filtros-tesoreria-crown-kardex" class="mb-0">
                            <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                            <input type="hidden" name="consultar" value="1">
                            <input type="hidden" name="vista_id" value="{{ $vistaActiva->id ?? '' }}">
                            <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                            <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                            <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">

                            <div class="form-row mb-2">
                                <div class="form-group col-md-2 mb-2">
                                    <label class="small mb-0" for="fecha_desde">Desde</label>
                                    <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
                                </div>
                                <div class="form-group col-md-2 mb-2">
                                    <label class="small mb-0" for="fecha_hasta">Hasta</label>
                                    <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                                </div>
                                <div class="form-group col-md-2 mb-2">
                                    <label class="small mb-0" for="operacion">Operación</label>
                                    <select name="operacion" id="operacion" class="form-control form-control-sm">
                                        <option value="">Todas</option>
                                        @foreach ($operaciones as $op)
                                            <option value="{{ $op['codigo'] }}" @selected(($filtros['operacion'] ?? '') === $op['codigo'])>
                                                {{ $op['codigo'] }} — {{ $op['nombre'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-3 mb-2">
                                    @include('caja.tesoreria_crown.kardex_valores.partials.campo_consulta_deposito', [
                                        'depositoId' => ($filtros['deposito_id'] ?? 0) ?: '',
                                        'codigo' => $filtros['deposito_codigo'] ?? '',
                                        'nombre' => $filtros['deposito_nombre'] ?? '',
                                    ])
                                </div>
                                <div class="form-group col-md-3 mb-2">
                                    @include('caja.tesoreria_crown.kardex_valores.partials.campo_consulta_tipo', [
                                        'tipoId' => ($filtros['tipo_id'] ?? 0) ?: '',
                                        'codigo' => $filtros['tipo_codigo'] ?? '',
                                        'nombre' => $filtros['tipo_nombre'] ?? '',
                                    ])
                                </div>
                                <div class="form-group col-md-3 mb-2">
                                    @include('caja.tesoreria_crown.kardex_valores.partials.campo_consulta_subtipo', [
                                        'subtipoId' => ($filtros['subtipo_id'] ?? 0) ?: '',
                                        'codigo' => $filtros['subtipo_codigo'] ?? '',
                                        'nombre' => $filtros['subtipo_nombre'] ?? '',
                                    ])
                                </div>
                                <div class="form-group col-md-3 mb-2">
                                    @include('caja.tesoreria_crown.kardex_valores.partials.campo_consulta_valor', [
                                        'valorId' => ($filtros['valor_id'] ?? 0) ?: '',
                                        'codigo' => $filtros['valor_codigo'] ?? '',
                                        'nombre' => $filtros['valor_nombre'] ?? '',
                                    ])
                                </div>
                                <div class="form-group col-md-2 mb-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
                                </div>
                            </div>

                            @include('caja.tesoreria_crown.partials.workbench_barra', [
                                'rutaListado' => 'tesoreria_crown_kardex_valores',
                                'baseUrl' => route('tesoreria_crown_kardex_valores', ['sala_id' => $sala->id]),
                                'limpiarParams' => ['sala_id' => $sala->id, 'vista_estandar' => 1],
                            ])
                            @include('caja.tesoreria_crown.partials.workbench_qbe')
                        </form>
                    </div>

                    @if ($filtros['consultar'] ?? false)
                        <p class="small text-muted mb-2">{{ $filas->total() }} movimiento(s) de valor.</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        @foreach ($columnasVisibles as $keyColumna)
                                            @include('caja.tesoreria_crown.partials.workbench_th', ['key' => $keyColumna, 'rutaListado' => 'tesoreria_crown_kardex_valores'])
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($enriquecidas as $fila)
                                        <tr>
                                            @foreach ($columnasVisibles as $keyColumna)
                                                <td>
                                                    @include('caja.tesoreria_crown.kardex_valores.partials.workbench_celda', ['key' => $keyColumna])
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ max(1, count($columnasVisibles ?? [])) }}" class="text-muted">
                                                Sin movimientos con estos filtros.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2">
                            {{ $filas->links() }}
                        </div>
                    @endif

                    @include('caja.tesoreria_crown.partials.workbench_modales', [
                        'rutaGuardarColumnas' => 'guardar_columnas_tesoreria_crown_kardex_valores',
                        'rutaGuardarVista' => 'guardar_vista_tesoreria_crown_kardex_valores',
                        'rutaEliminarVista' => 'eliminar_vista_tesoreria_crown_kardex_valores',
                        'rutaPreview' => 'preview_tesoreria_crown_kardex_valores',
                        'rutaGuardarEtiquetas' => 'guardar_etiquetas_tesoreria_crown_kardex_valores',
                    ])
                @endif
            </div>
        </div>
    </div>
</div>
@include('includes.caja.modalconsultakardeposito')
@include('includes.caja.modalconsultakartipo')
@include('includes.caja.modalconsultakarsubtipo')
@include('includes.caja.modalconsultakarvalor')
@endsection

@section('scripts')
@if ($sala)
    <script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-orden.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-agrupar.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-disenador-preview.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-vista-guardar.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/workbench.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/workbench.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/kardex_consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/kardex_consulta.js')) }}"></script>
@endif
@endsection
