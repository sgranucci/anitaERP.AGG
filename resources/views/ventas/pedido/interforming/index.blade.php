@extends("theme.$theme.layout")
@section('titulo')
Pedidos Interforming
@endsection

@section('styles')
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
    $pedidoWbJs = public_path('assets/pages/scripts/ventas/pedido/interforming/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/interforming/workbench.js') }}?v={{ file_exists($pedidoWbJs) ? filemtime($pedidoWbJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/interforming/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/pedido/interforming/index.js')) ?: time() }}"></script>
@if (can('ejecutar-importar-pedido-anita', false))
<script src="{{ asset('assets/pages/scripts/ventas/pedido/interforming/importar_anita.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/pedido/interforming/importar_anita.js')) ?: time() }}"></script>
@endif
@if (!empty($puedeFacturarIndex))
<script>window.pedidoModoIndexFacturacion = true;</script>
<script>window.pedidoSinRemitoObligatorio = true;</script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/proceso-overlay.js') }}" type="text/javascript"></script>
@include('includes.ventas.preferencias_facturacion_scripts')
@include('includes.ventas.facturacion_circuito_scripts')
@include('ventas.partials.aviso_deposito_facturacion')
<script src="{{ asset('assets/pages/scripts/ventas/pedido/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/pedido/crear.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/pedido/facturar_index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/pedido/facturar_index.js')) ?: time() }}" type="text/javascript"></script>
@endif
@endsection

@php
    use App\Support\Ventas\PedidoInterformingFacturacionSupport;
    use App\Support\Ventas\PedidoInterformingListadoFiltros;

    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $columnasVisibles = $columnasVisibles ?? [];
    $tipoActual = strtoupper(trim((string) ($filtros['tipo'] ?? 'TODOS')));
    $tituloListado = match ($tipoActual) {
        'PED' => 'Pedidos de venta',
        'PEX' => 'Pedidos de exportación',
        default => 'Pedidos de clientes',
    };
    $limpiarQ = [];
    if (in_array($tipoActual, ['PED', 'PEX'], true)) {
        $limpiarQ['tipo'] = $tipoActual;
    }
    if ($vistaActiva ?? null) {
        $limpiarQ['vista_id'] = $vistaActiva->id;
    }
    $limpiarQ['filtro_limpiar'] = 1;
    $limpiarUrl = route('pedido', $limpiarQ);
    $camposOrdenablesThead = PedidoInterformingListadoFiltros::camposOrdenables();
    $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
        $filtros['sort'] ?? [],
        $camposOrdenablesThead
    );
    $qsQuitarOrden = $filtrosQuery ?? [];
    unset($qsQuitarOrden['sort']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('pedido', $qsQuitarOrden);
    $qbeAbierto = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">{{ $tituloListado }}</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    @if (can('ejecutar-importar-pedido-anita', false))
                        <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalImportarPedidoAnita" title="Importar pedidos desde Anita">
                            <i class="fa fa-download"></i> Importar Anita
                        </button>
                    @endif
                    @if (can('listar-importar-pedido-anita', false))
                        <a href="{{ route('importar_pedido_anita') }}" class="btn btn-outline-light btn-sm" title="Pantalla de importación Anita">
                            <i class="fa fa-list"></i> Vista previa Anita
                        </a>
                    @endif
                    @if (can('crear-pedidos', false))
                        <a href="{{ route('crear_pedido', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nuevo pedido
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
            <form method="get" action="{{ route('pedido') }}" id="form-filtros-pedido-interforming" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if (in_array($tipoActual, ['PED', 'PEX'], true))
                    <input type="hidden" name="tipo" value="{{ $tipoActual }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('pedido') }}"
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
                        <button type="button" class="btn btn-sm {{ $qbeAbierto ? 'btn-info' : 'btn-outline-info' }} {{ $qbeAbierto ? '' : 'collapsed' }}" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="{{ $qbeAbierto ? 'true' : 'false' }}">
                            <i class="fa fa-filter"></i> Consulta avanzada
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
                        @if (PedidoInterformingListadoFiltros::tieneCriteriosTexto($filtros ?? []) || \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                        @if (($ordenActualThead ?? []) !== [])
                            <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary" title="Vuelve al orden por ID descendente y lo saca de la vista">
                                <i class="fa fa-sort"></i> Quitar orden
                            </a>
                        @endif
                    </div>
                </div>
                @include('ventas.pedido.interforming.partials.filtros_externos', [
                    'exportRuta' => 'lista_pedido',
                    'exportQueryparams' => $filtrosQuery ?? [],
                ])
                @include('ventas.pedido.interforming.partials.workbench_qbe')
            </form>
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('ventas.pedido.interforming.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $agruparActivo = \App\Support\Listado\ListadoAgrupacionSupport::normalizar(
                                $filtros['agrupar'] ?? [],
                                PedidoInterformingListadoFiltros::camposOrdenables()
                            );
                            $filasVista = \App\Support\Listado\ListadoAgrupacionSupport::segmentar(
                                $datas,
                                $agruparActivo,
                                static fn ($row, string $campo): string => \App\Support\Ventas\PedidoInterformingListadoColumnas::valorCelda($row, $campo),
                                $etiquetasColumnas ?? [],
                                ! empty($cortes['por_clave']) ? $cortes['por_clave'] : null
                            );
                            $colspanGrilla = count($columnasVisibles) + 1;
                        @endphp
                        @forelse ($filasVista as $filaVista)
                            @if (($filaVista['type'] ?? '') === 'header')
                                <tr class="lw-group-header lw-group-nivel-{{ (int) ($filaVista['nivel'] ?? 0) }}">
                                    <td colspan="{{ $colspanGrilla }}">
                                        <strong>{{ $filaVista['label'] }}:</strong>
                                        {{ $filaVista['valor'] !== '' ? $filaVista['valor'] : '(vacío)' }}
                                        <span class="lw-group-count">{{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }}</span>
                                    </td>
                                </tr>
                            @else
                                @php $pedido = $filaVista['row']; @endphp
                                <tr>
                                    @foreach ($columnasVisibles as $keyColumna)
                                        @include('ventas.pedido.interforming.partials.workbench_celda', ['key' => $keyColumna, 'pedido' => $pedido])
                                    @endforeach
                                    <td class="text-nowrap">
                                        @php
                                            $facturasPedido = PedidoInterformingFacturacionSupport::facturasEmitidas($pedido);
                                        @endphp
                                        <a href="{{ route('listar_pedido_pdf', $pedido->id) }}" class="btn-accion-tabla tooltipsC" title="PDF" target="_blank" rel="noopener">
                                            <i class="fa fa-file-pdf text-danger"></i>
                                        </a>
                                        @if (count($facturasPedido) > 0 && can('listar-factura', false))
                                            <a href="#"
                                               class="btn-accion-tabla tooltipsC btn-imprimir-facturas-pedido-index"
                                               data-pedido-id="{{ $pedido->id }}"
                                               data-pedido-codigo="{{ $pedido->codigo }}"
                                               data-facturas='@json($facturasPedido)'
                                               title="Imprimir factura">
                                                <i class="fa fa-print text-primary"></i>
                                            </a>
                                        @endif
                                        @if (!empty($puedeFacturarIndex) && PedidoInterformingFacturacionSupport::puedeFacturar($pedido))
                                            <a href="#" class="btn-accion-tabla tooltipsC btn-facturar-pedido-index" data-pedido-id="{{ $pedido->id }}" title="Facturar">
                                                <i class="fas fa-file-invoice text-success"></i>
                                            </a>
                                        @endif
                                        @if (can('editar-pedidos', false))
                                            <a href="{{ route('editar_pedido', ['id' => $pedido->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if (can('borrar-pedidos', false))
                                            <form action="{{ route('eliminar_pedido', $pedido->id) }}" method="POST" class="d-inline form-eliminar">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ $colspanGrilla }}" class="text-center">Sin pedidos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}

@include('ventas.pedido.interforming.partials.workbench_modales')
@include('includes.proceso-overlay-pedido')
@if (!empty($puedeFacturarIndex))
    @include('ventas.pedido.partials.facturar_desde_index')
@endif
@include('ventas.pedido.interforming.partials.modal_imprimir_facturas')
@if (can('ejecutar-importar-pedido-anita', false))
    @include('ventas.pedido.interforming.partials.modal_importar_anita')
@endif
@endsection
