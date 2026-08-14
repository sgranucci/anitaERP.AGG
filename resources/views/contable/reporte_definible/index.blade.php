@extends("theme.$theme.layout")
@section('titulo')
    Reportes contables definibles
@endsection

@section('scripts')
<script src="{{asset('assets/pages/scripts/admin/index.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/pages/scripts/includes/listado-filtros.js')}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $limpiarUrl = route('reporte_definible');
    $tieneCriteriosListado = \App\Support\Contable\ReporteDefinibleListadoFiltros::tieneCriteriosAplicados($filtros ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-sitemap"></i> Reportes contables definibles
                </h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-reporte-definible',
                        'filtroValor' => $filtros['filtro_valor'] ?? '',
                        'tieneCriterios' => $tieneCriteriosListado,
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (nombre, código, título…)',
                        'toggleTarget' => '#panel-filtros-reporte-definible',
                        'toggleId' => 'btn-toggle-filtros-reporte-definible',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_reporte_definible'),
                        'nuevoRegistroCan' => 'crear-reporte-definible',
                        'nuevoRegistroLabel' => 'Nuevo informe',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('reporte_definible') }}" id="form-filtros-reporte-definible" class="mb-0">
                @include('contable.reporte_definible.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body">
                @include('includes.mensaje')
                @if (session('advertencias_import'))
                    <div class="alert alert-warning">
                        <strong>Advertencias de importación:</strong>
                        <ul class="mb-0">
                            @foreach (session('advertencias_import') as $adv)
                                <li>{{ $adv }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="alert alert-light border mb-3">
                    <strong>Qué es esto.</strong>
                    Defina la <em>estructura</em> de un estado contable (balance, resultados u otro)
                    como árbol de rubros y cuentas — igual que Financial Statement Version (SAP) —
                    y luego <strong>Ejecutar</strong> sobre los saldos de anitaERP.
                    Una sola pantalla de catálogo: diseñar, copiar, importar desde Anita y correr.
                </div>

                <div class="mb-3">
                    @if ($puede_editar ?? false)
                        <a href="{{ route('reporte_definible_conjunto') }}" class="btn btn-outline-secondary btn-sm mr-2">
                            <i class="fa fa-layer-group"></i> Sets de cuentas
                        </a>
                    @endif
                    @if (($puede_crear ?? false) && !empty($plantillas) && $plantillas->count())
                        <form method="post" action="{{ route('crear_desde_plantilla_reporte_definible') }}" class="form-inline d-inline-flex align-items-center mr-2">
                            @csrf
                            <select name="plantilla_id" class="form-control form-control-sm mr-1" required>
                                <option value="">Desde plantilla…</option>
                                @foreach ($plantillas as $pl)
                                    <option value="{{ $pl->id }}">{{ $pl->codigo }} — {{ $pl->nombre }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-outline-primary btn-sm">Crear</button>
                        </form>
                    @endif
                    @if ($puede_importar)
                        <form method="post" action="{{ route('importar_reporte_definible_anita') }}" class="form-inline d-inline-flex align-items-center"
                              onsubmit="return confirm('¿Importar / actualizar definiciones desde Anita? Se reemplaza la estructura de los informes tocados.');">
                            @csrf
                            <label class="mr-2 mb-0">Importar Anita</label>
                            <input type="number" name="informe_desde" class="form-control form-control-sm mr-1" placeholder="Desde nro." style="width:100px">
                            <input type="number" name="informe_hasta" class="form-control form-control-sm mr-2" placeholder="Hasta nro." style="width:100px">
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-download"></i> Traer de Anita
                            </button>
                            <span class="text-muted small ml-2">Vacío = todos los informes</span>
                        </form>
                    @endif
                </div>

                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_reporte_definible',
                    'queryparams' => $filtrosQuery ?? [],
                ])

                <div class="table-responsive">
                    <table id="tabla-paginada" class="table table-hover table-sm">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Títulos</th>
                                <th>Tipo</th>
                                <th>Origen</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Rubros</th>
                                <th class="text-center">Activo</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($coleccion as $item)
                                <tr>
                                    <td>{{ $item->codigo }}</td>
                                    <td>{{ $item->nombre }}</td>
                                    <td class="small text-muted">
                                        {{ $item->titulo1 }}
                                        @if ($item->titulo2)
                                            <br>{{ $item->titulo2 }}
                                        @endif
                                    </td>
                                    <td>{{ $tiposReporte[$item->tipo] ?? $item->tipo }}</td>
                                    <td>{{ $item->origen === 'anita' ? 'Anita' : 'Manual' }}</td>
                                    <td class="text-center">
                                        @if (($item->estado_publicacion ?? 'borrador') === 'publicado')
                                            <span class="badge badge-primary">Publicado</span>
                                        @else
                                            <span class="badge badge-secondary">Borrador</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $item->rubros_count }}</td>
                                    <td class="text-center">
                                        @if ($item->activo)
                                            <span class="badge badge-success">Sí</span>
                                        @else
                                            <span class="badge badge-secondary">No</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-nowrap">
                                        @if ($puede_ejecutar)
                                            <a href="{{ route('ejecutar_reporte_definible', ['id' => $item->id]) }}"
                                               class="btn-accion-tabla tooltipsC" title="Ejecutar informe">
                                                <i class="fa fa-play text-success"></i>
                                            </a>
                                        @endif
                                        @if ($puede_editar)
                                            <a href="{{ route('editar_reporte_definible', ['id' => $item->id]) }}"
                                               class="btn-accion-tabla tooltipsC" title="Diseñar estructura">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if ($puede_crear)
                                            <form action="{{ route('copiar_reporte_definible', ['id' => $item->id]) }}" method="post" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn-accion-tabla tooltipsC border-0 bg-transparent" title="Copiar">
                                                    <i class="fa fa-copy"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if ($puede_eliminar)
                                            <form action="{{ route('eliminar_reporte_definible', ['id' => $item->id]) }}" method="post" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar el informe y toda su estructura?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-accion-tabla tooltipsC border-0 bg-transparent" title="Eliminar">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No hay informes. Cree uno nuevo o impórtelos desde Anita.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">
                    {{ $coleccion->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
