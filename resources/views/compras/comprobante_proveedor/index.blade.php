@extends("theme.$theme.layout")
@section('titulo')
    Comprobantes de proveedor
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $workbenchJs = public_path('assets/pages/scripts/compras/comprobante_proveedor/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/comprobante_proveedor/workbench.js') }}?v={{ file_exists($workbenchJs) ? filemtime($workbenchJs) : time() }}"></script>
@include('compras.partials.documentos_relacionados_circuito_script')
@endsection

@php
    use App\Support\Compras\ComprobanteProveedorEstados;
    use App\Support\Compras\ComprobanteProveedorListadoFiltros;
    use App\Support\Listado\QueryRetornoListado;

    $columnasVisibles = $columnasVisibles ?? [];
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $estadoActual = (string) ($filtros['estado'] ?? ComprobanteProveedorEstados::FILTRO_TODOS);
    $limpiarQ = ComprobanteProveedorListadoFiltros::paraQueryStringExternos($filtros ?? []);
    if ($vistaActiva ?? null) {
        $limpiarQ['vista_id'] = $vistaActiva->id;
    } elseif (! empty($filtros['vista_estandar'])) {
        $limpiarQ['vista_estandar'] = 1;
    }
    $limpiarQ['filtro_limpiar'] = 1;
    $limpiarUrl = route('comprobante_proveedor', $limpiarQ);
    $retornoListadoQuery = QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $puedeVerComprobante = can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false);
@endphp

@section('contenido')
@include('compras.partials.documentos_relacionados_circuito_modal')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('compras.precarga_comprobante_proveedor.partials.aviso_ya_en_anita')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Comprobantes de proveedor</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    @if (can('editar-configuracion-comprobante-proveedor', false))
                    <a href="{{ route('configuracion_comprobante_proveedor') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-cog"></i> Configuración
                    </a>
                    @endif
                    @if (can('listar-precarga-proveedores', false))
                    <a href="{{ route('precarga_comprobante_proveedor') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-list"></i> Precargas
                    </a>
                    @endif
                    @if (can('crear-comprobante-proveedor', false) || can('listar-precarga-proveedores', false))
                    <a href="{{ route('comprobante_proveedor_opciones_carga') }}" class="btn btn-light btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Cargar factura
                    </a>
                    @endif
                </div>
            </div>
            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif
            <form method="get" action="{{ route('comprobante_proveedor') }}" id="form-filtros-comprobante-proveedor" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if ($empresaScope === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif ($empresaActual > 0)
                    <input type="hidden" name="empresa_id" value="{{ $empresaActual }}">
                @endif
                @if ($estadoActual === ComprobanteProveedorEstados::FILTRO_TODOS)
                    <input type="hidden" name="estado_todas" value="1">
                @else
                    <input type="hidden" name="estado" value="{{ $estadoActual }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @elseif (! empty($filtros['vista_estandar']))
                    <input type="hidden" name="vista_estandar" value="1">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('comprobante_proveedor') }}"
                                title="Vistas guardadas"
                                @if (! ($workbenchListo ?? false)) disabled @endif>
                            <option value="">Vista estándar</option>
                            @foreach (($vistasListado ?? []) as $vista)
                                <option value="{{ $vista->id }}" @if (($vistaActiva ?? null) && (int) $vistaActiva->id === (int) $vista->id) selected @endif>
                                    {{ $vista->nombre }}
                                    @if ($vista->es_default) ★ @endif
                                    @if ($vista->compartida) (compartida) @endif
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-lw-grilla"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-th"></i> Diseñar vista
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="false" aria-controls="lw-qbe-panel">
                            <i class="fa fa-filter"></i> QBE
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-font"></i> Defaults instalación
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="Texto o número"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if (ComprobanteProveedorListadoFiltros::tieneCriteriosTexto($filtros ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                    </div>
                </div>
                @include('compras.comprobante_proveedor.partials.filtros_externos')
                @include('compras.comprobante_proveedor.partials.workbench_qbe')
            </form>
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body py-2 border-bottom bg-white">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_comprobante_proveedor',
                    'queryparams' => $filtrosQuery ?? [],
                ])
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('compras.comprobante_proveedor.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            <th class="width120" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('compras.comprobante_proveedor.partials.workbench_celda', [
                                    'key' => $keyColumna,
                                    'data' => $data,
                                    'puedeVerComprobante' => $puedeVerComprobante,
                                    'retornoListadoQuery' => $retornoListadoQuery,
                                ])
                            @endforeach
                            <td class="text-nowrap">
                                @if (can('editar-comprobante-proveedor', false))
                                <a href="{{ route('editar_comprobante_proveedor', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endif
                                @if (can('listar-comprobante-proveedor', false) || can('editar-comprobante-proveedor', false))
                                <button type="button"
                                        class="btn-accion-tabla tooltipsC text-primary js-circuito-documentos-relacionados"
                                        title="Documentos relacionados (RQ, OC, COM, OP)"
                                        data-url="{{ route('comprobante_proveedor_documentos_relacionados', ['id' => $data->id]) }}"
                                        data-numero="Factura #{{ $data->id }}">
                                    <i class="fa fa-sitemap"></i>
                                </button>
                                @endif
                                @if (($data->estado ?? '') !== ComprobanteProveedorEstados::CONTABILIZADO
                                    && ($data->estado ?? '') !== ComprobanteProveedorEstados::ANULADO
                                    && can('contabilizar-comprobante-proveedor', false))
                                <form action="{{ route('contabilizar_comprobante_proveedor', ['id' => $data->id] + $retornoListadoQuery) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('¿Confirmar / contabilizar el comprobante #{{ $data->id }}?');">
                                    @csrf
                                    <button type="submit" class="btn-accion-tabla tooltipsC text-success" title="Confirmar / Contabilizar">
                                        <i class="fa fa-check"></i>
                                    </button>
                                </form>
                                @endif
                                @if (can('borrar-comprobante-proveedor', false))
                                <form action="{{ route('eliminar_comprobante_proveedor', ['id' => $data->id] + $retornoListadoQuery) }}" method="POST" class="d-inline form-eliminar">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="{{ ComprobanteProveedorEstados::textoBorrarTooltip(ComprobanteProveedorEstados::tieneHuellaAnita($data)) }}">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-4">No hay comprobantes con estos filtros.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@include('compras.comprobante_proveedor.partials.workbench_modales')
@endsection
