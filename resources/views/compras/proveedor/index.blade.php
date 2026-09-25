@extends("theme.$theme.layout")
@section('titulo')
Proveedores
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}">
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/workbench.js")}}" type="text/javascript"></script>
@endsection

@php
    use App\Helpers\biblioteca;
    use App\Support\Compras\ProveedorListadoFiltros;
    use App\Support\Compras\ProveedorListadoColumnas;

    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('proveedor');
    $filtroEmpresaActivo = ProveedorListadoFiltros::filtroEmpresaActivo();
    $columnasVisibles = $columnasVisibles ?? ProveedorListadoColumnas::defaultsVisibles();
    $catalogoColumnas = $catalogoColumnas ?? ProveedorListadoColumnas::catalogoActivo();
    $etiquetasColumnas = $etiquetasColumnas ?? [];
    $qbe = (array) ($filtros['qbe'] ?? []);
    $tieneQbe = ProveedorListadoFiltros::tieneCriteriosAplicados($filtros ?? []);
    $vistasListado = $vistasListado ?? collect();
    $vistaActiva = $vistaActiva ?? null;
    $workbenchListo = $workbenchListo ?? false;
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">
                    <i class="fa fa-address-book mr-1"></i> Proveedores
                    <small class="ml-2" style="opacity:.85;font-weight:400;">Workbench · consulta multi-campo</small>
                </h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    @include('includes.compras.boton-manual')
                    @if (can('crear-proveedor', false))
                        <a href="{{ route('crear_proveedor', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nuevo
                        </a>
                    @endif
                </div>
            </div>

            @if (! $workbenchListo)
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif

            <form method="get" action="{{ route('proveedor') }}" id="form-filtros-proveedor" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if ($vistaActiva)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif

                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('proveedor') }}"
                                title="Vistas guardadas"
                                @if (! $workbenchListo) disabled @endif>
                            <option value="">Vista estándar</option>
                            @foreach ($vistasListado as $vista)
                                <option value="{{ $vista->id }}" @if ($vistaActiva && (int) $vistaActiva->id === (int) $vista->id) selected @endif>
                                    {{ $vista->nombre }}
                                    @if ($vista->es_default)
                                        ★
                                    @endif
                                    @if ($vista->compartida)
                                        (compartida)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-lw-grilla"
                                @if (! $workbenchListo) disabled title="Requiere migración" @endif>
                            <i class="fa fa-th"></i> Configurar grilla
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="true">
                            <i class="fa fa-filter"></i> QBE
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! $workbenchListo) disabled title="Requiere migración" @endif>
                            <i class="fa fa-font"></i> Defaults instalación
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="Búsqueda rápida (Enter)…"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if ($tieneQbe || (($filtros['valor'] ?? '') !== ''))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                    </div>
                </div>

                @include('compras.proveedor.partials.workbench_qbe')

                @if ($tieneQbe)
                    <div class="lw-chips">
                        @if (($filtros['modo'] ?? '') === 'qbe')
                            @foreach ($qbe as $c)
                                @if (is_array($c))
                                    <span class="lw-chip">
                                        <strong>{{ $etiquetasColumnas[$c['campo'] ?? ''] ?? ($c['campo'] ?? '') }}</strong>
                                        {{ $c['op'] ?? 'contiene' }}
                                        @if (($c['op'] ?? '') !== 'vacio')
                                            «{{ $c['valor'] ?? '' }}»
                                        @endif
                                    </span>
                                @elseif (is_string($c) || is_numeric($c))
                                    <span class="lw-chip"><strong>{{ $loop->key }}</strong> {{ $c }}</span>
                                @endif
                            @endforeach
                        @elseif (($filtros['valor'] ?? '') !== '')
                            <span class="lw-chip">
                                <strong>Texto</strong> {{ $filtros['valor'] }}
                            </span>
                        @endif
                    </div>
                @endif

                @include('compras.proveedor.partials.filtros_externos')
            </form>

            @php
                use App\Support\Listado\ListadoGrillaConfigSupport as LwGrilla;
                $layoutPorKey = [];
                foreach (($grillaLayout ?? []) as $filaLayout) {
                    $layoutPorKey[$filaLayout['key']] = $filaLayout;
                }
                // Anchos de pantalla: preferencia relativa, escalada al presupuesto legible.
                $layoutPantalla = LwGrilla::escalarAnchosParaPantalla($grillaLayout ?? []);
                $layoutPantallaPorKey = [];
                foreach ($layoutPantalla as $filaP) {
                    $layoutPantallaPorKey[$filaP['key']] = $filaP;
                }
                $sumaAnchosPantalla = max(1, LwGrilla::sumaAnchosVisibles($layoutPantalla));
                $usaScrollHorizontal = $sumaAnchosPantalla > LwGrilla::ANCHO_PRESUPUESTO_PANTALLA;
            @endphp
            <div class="card-body p-0 lw-table-wrap {{ $usaScrollHorizontal ? 'table-responsive lw-table-scroll' : 'lw-table-fit' }}">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_proveedor',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover mb-0 lw-tabla-grilla" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $key)
                                @php
                                    $cfg = $layoutPorKey[$key] ?? null;
                                    $cfgPantalla = $layoutPantallaPorKey[$key] ?? $cfg;
                                    $alinea = $cfg['alinea'] ?? 'izquierda';
                                    $ancho = (int) ($cfgPantalla['ancho'] ?? 100);
                                    $minLegible = LwGrilla::anchoMinimoLegible($key, $catalogoColumnas[$key] ?? []);
                                    $cls = LwGrilla::claseAlineacion($alinea);
                                    $tituloCol = $etiquetasColumnas[$key] ?? ($catalogoColumnas[$key]['label'] ?? $key);
                                    if ($usaScrollHorizontal) {
                                        $styleCol = 'width:'.$ancho.'px;min-width:'.$minLegible.'px;';
                                    } else {
                                        $pct = round(($ancho / $sumaAnchosPantalla) * 100, 2);
                                        $styleCol = 'width:'.$pct.'%;min-width:'.$minLegible.'px;';
                                    }
                                @endphp
                                <th class="{{ $cls }} lw-col" style="{{ $styleCol }}" title="{{ $tituloCol }}">
                                    {{ $tituloCol }}
                                </th>
                            @endforeach
                            <th class="lw-col-acciones" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($proveedores as $data)
                            <tr @if ($data->estado == '1') class="table-danger" @endif>
                                @foreach ($columnasVisibles as $key)
                                    @include('compras.proveedor.partials.workbench_celda', [
                                        'key' => $key,
                                        'data' => $data,
                                        'cfg' => $layoutPorKey[$key] ?? null,
                                    ])
                                @endforeach
                                <td class="lw-col-acciones text-nowrap">
                                    @if (can('editar-proveedor', false))
                                        <a href="{{route('editar_proveedor', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('listar-cuentacorriente-proveedor', false))
                                        <a href="{{route('listar_cuentacorriente_proveedor', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'])}}" target="_blank" rel="noopener" class="btn-accion-tabla tooltipsC" title="Cuenta Corriente (se abre en modo consulta)">
                                            <i class="fa fa-folder-open"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-proveedor', false))
                                        <form action="{{route('eliminar_proveedor', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method("delete")
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columnasVisibles) + 1 }}">
                                    <div class="lw-empty">
                                        <div><i class="fa fa-search"></i></div>
                                        <div>Sin resultados con esos criterios.</div>
                                        <div class="small mt-1">Probá ampliar el QBE o usar la búsqueda rápida.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $proveedores->appends($filtrosQuery ?? [])->links() }}

@include('compras.proveedor.partials.workbench_modales')
@endsection
