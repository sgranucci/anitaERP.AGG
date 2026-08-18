@extends("theme.$theme.layout")
@section('titulo')
    Reportes definibles de sueldos
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $limpiarUrl = route('reporte_sueldos_definible');
    $tieneCriteriosListado = \App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleListadoFiltros::tieneCriteriosAplicados($filtros ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-table"></i> Reportes definibles de sueldos
                </h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('manual_reporte_sueldos_definible') }}" class="btn btn-outline-info btn-sm mr-2" target="_blank" rel="noopener">
                        <i class="fa fa-book"></i> Manual
                    </a>
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-reporte-sueldos-definible',
                        'filtroValor' => $filtros['filtro_valor'] ?? '',
                        'tieneCriterios' => $tieneCriteriosListado,
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (título, código…)',
                        'toggleTarget' => '#panel-filtros-reporte-sueldos-definible',
                        'toggleId' => 'btn-toggle-filtros-reporte-sueldos-definible',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_reporte_sueldos_definible'),
                        'nuevoRegistroCan' => 'crear-reporte-sueldos-definible',
                        'nuevoRegistroLabel' => 'Nuevo listado',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('reporte_sueldos_definible') }}" id="form-filtros-reporte-sueldos-definible" class="mb-0">
                @include('sueldos.reporte_definible.partials.filtros_listado', [
                    'filtros' => $filtros,
                    'camposFiltro' => $camposFiltro,
                ])
            </form>
            <div class="card-body">
                <div class="mb-3 d-flex flex-wrap align-items-center">
                    @if ($puede_importar ?? false)
                        <form method="post" action="{{ route('importar_reporte_sueldos_definible_anita') }}" class="form-inline d-inline-flex align-items-center mr-2"
                              onsubmit="return confirm('Importar desde Anita (dry-run primero si no marca Ejecutar). ¿Continuar?');">
                            @csrf
                            <input type="number" name="listado" class="form-control form-control-sm mr-1" placeholder="Nro listado" style="width:110px">
                            <div class="custom-control custom-checkbox mr-2">
                                <input type="checkbox" class="custom-control-input" name="ejecutar" id="imp_ejecutar" value="1">
                                <label class="custom-control-label" for="imp_ejecutar">Ejecutar</label>
                            </div>
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-download"></i> Importar Anita
                            </button>
                        </form>
                    @endif
                    @if (($puede_crear ?? false) && ($plantillas ?? collect())->isNotEmpty())
                        <form method="post" action="{{ route('crear_desde_plantilla_reporte_sueldos_definible') }}" class="form-inline d-inline-flex align-items-center mr-2">
                            @csrf
                            <select name="plantilla_id" class="form-control form-control-sm mr-1">
                                @foreach ($plantillas as $p)
                                    <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->titulo }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-outline-primary btn-sm">Desde plantilla</button>
                        </form>
                    @endif
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_reporte_sueldos_definible',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>
                @include('includes.listado.filtros_aviso_activos', [
                    'tieneCriterios' => $tieneCriteriosListado,
                    'limpiarUrl' => $limpiarUrl,
                ])
                <div class="table-responsive">
                    <table id="tabla-paginada" class="table table-hover table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Origen</th>
                                <th>Columnas</th>
                                <th>Activo</th>
                                <th style="width:160px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($coleccion as $item)
                                <tr>
                                    <td>{{ $item->codigo }}</td>
                                    <td>{{ $item->titulo }}</td>
                                    <td>{{ $tiposListado[$item->tipo] ?? $item->tipo }}</td>
                                    <td>{{ $item->origen }}</td>
                                    <td>{{ $item->columnas_count ?? 0 }}</td>
                                    <td>{{ $item->activo ? 'Sí' : 'No' }}</td>
                                    <td class="text-nowrap">
                                        @if ($puede_ejecutar ?? false)
                                            <a href="{{ route('ejecutar_reporte_sueldos_definible', ['id' => $item->id]) }}"
                                               class="btn-accion-tabla tooltipsC" title="Ejecutar">
                                                <i class="fa fa-play"></i>
                                            </a>
                                        @endif
                                        @if ($puede_editar ?? false)
                                            <a href="{{ route('editar_reporte_sueldos_definible', ['id' => $item->id]) }}"
                                               class="btn-accion-tabla tooltipsC" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if ($puede_crear ?? false)
                                            <form action="{{ route('copiar_reporte_sueldos_definible', ['id' => $item->id]) }}" method="post" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn-accion-tabla tooltipsC" title="Copiar">
                                                    <i class="fa fa-copy"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if ($puede_eliminar ?? false)
                                            <form action="{{ route('eliminar_reporte_sueldos_definible', ['id' => $item->id]) }}" method="post" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar listado?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-accion-tabla tooltipsC" title="Eliminar">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Sin listados. Importe desde Anita o cree uno nuevo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $coleccion->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
