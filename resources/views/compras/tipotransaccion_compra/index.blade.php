@extends("theme.$theme.layout")
@section('titulo')
    Tipos de comprobante de compras
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section("scripts")
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $filtroJs = public_path('assets/pages/scripts/compras/tipotransaccion_compra/filtro.js');
@endphp
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/tipotransaccion_compra/filtro.js') }}?v={{ file_exists($filtroJs) ? filemtime($filtroJs) : time() }}"></script>
@endsection

@php
    use App\Support\Compras\TipotransaccionCompraListadoFiltros;
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench">
            <div class="card-header">
                <h3 class="card-title">Tipos de comprobante de compras</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-tipotransaccion-compra',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => TipotransaccionCompraListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('tipotransaccion_compra'),
                        'placeholder' => 'Nombre, abreviatura o «no retiene»…',
                        'toggleTarget' => '#panel-filtros-tipotransaccion-compra',
                        'toggleId' => 'btn-toggle-filtros-tipotransaccion-compra',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_tipotransaccion_compra'),
                        'nuevoRegistroCan' => 'crear-tipo-transaccion-compra',
                    ])
                </div>
            </div>
            @php
                $qbeAbierto = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
                $ordenGuardado = $filtros['sort'] ?? [];
                $ordenParaFlecha = $ordenGuardado !== []
                    ? $ordenGuardado
                    : [['campo' => 'nombre', 'dir' => 'asc']];
                $camposOrdenablesThead = TipotransaccionCompraListadoFiltros::camposOrdenables();
                $filtrosSinOrden = $filtrosQuery ?? [];
                unset($filtrosSinOrden['sort']);
            @endphp
            <form method="get" action="{{ route('tipotransaccion_compra') }}" id="form-filtros-tipotransaccion-compra" class="mb-0">
                @include('compras.tipotransaccion_compra.partials.filtros_listado', [
                    'limpiarUrl' => route('tipotransaccion_compra'),
                ])
                <div class="px-3 py-2 border-bottom d-flex flex-wrap align-items-center" style="gap:.35rem;">
                    <button type="button"
                            class="btn btn-sm {{ $qbeAbierto ? 'btn-info' : 'btn-outline-info collapsed' }}"
                            data-toggle="collapse"
                            data-target="#lw-qbe-panel"
                            aria-expanded="{{ $qbeAbierto ? 'true' : 'false' }}"
                            aria-controls="lw-qbe-panel">
                        <i class="fa fa-filter"></i> QBE
                    </button>
                    @if ($ordenGuardado !== [])
                        <a href="{{ route('tipotransaccion_compra', $filtrosSinOrden) }}" class="btn btn-sm btn-outline-secondary">
                            Quitar orden
                        </a>
                    @endif
                </div>
                @include('compras.tipotransaccion_compra.partials.qbe')
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_tipotransaccion_compra',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach (TipotransaccionCompraListadoFiltros::COLUMNAS_GRILLA as $keyColumna => $tituloCol)
                                @php
                                    $dirCol = \App\Support\Listado\ListadoOrdenamientoSupport::direccionDeCampo($ordenParaFlecha, $keyColumna);
                                    $idxCol = $ordenGuardado !== []
                                        ? \App\Support\Listado\ListadoOrdenamientoSupport::indiceDeCampo($ordenGuardado, $keyColumna)
                                        : null;
                                    if ($ordenGuardado === [] && $keyColumna === 'nombre') {
                                        $ordenToggle = [['campo' => 'nombre', 'dir' => 'desc']];
                                    } else {
                                        $ordenToggle = \App\Support\Listado\ListadoOrdenamientoSupport::togglePrimario(
                                            $ordenGuardado,
                                            $keyColumna,
                                            $camposOrdenablesThead
                                        );
                                    }
                                    $qsSort = array_merge(
                                        $filtrosSinOrden,
                                        \App\Support\Listado\ListadoOrdenamientoSupport::paraQueryString($ordenToggle)
                                    );
                                @endphp
                                <th class="lw-col lw-col-sortable {{ $dirCol ? 'lw-col-sorted' : '' }}"
                                    title="{{ $tituloCol }} — clic para ordenar">
                                    <a href="{{ route('tipotransaccion_compra', $qsSort) }}" class="lw-sort-link">
                                        {{ $tituloCol }}
                                        @if ($dirCol === 'asc')
                                            <i class="fa fa-sort-up lw-sort-icon"></i>
                                        @elseif ($dirCol === 'desc')
                                            <i class="fa fa-sort-down lw-sort-icon"></i>
                                        @else
                                            <i class="fa fa-sort lw-sort-icon lw-sort-muted"></i>
                                        @endif
                                        @if ($idxCol !== null && count($ordenGuardado) > 1)
                                            <sup class="lw-sort-prio">{{ $idxCol + 1 }}</sup>
                                        @endif
                                    </a>
                                </th>
                            @endforeach
                            <th style="width:5.5rem;" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            @foreach (TipotransaccionCompraListadoFiltros::COLUMNAS_GRILLA as $keyColumna => $tituloCol)
                                @switch($keyColumna)
                                    @case('id')
                                        <td>{{ $data->id }}</td>
                                        @break
                                    @case('nombre')
                                        <td>{{ $data->nombre }}</td>
                                        @break
                                    @case('operacion')
                                        <td>{{ $data->desc_operacion }}</td>
                                        @break
                                    @case('abreviatura')
                                        <td>{{ $data->abreviatura }}</td>
                                        @break
                                    @case('codigoafip')
                                        <td>{{ $data->codigoafip }}</td>
                                        @break
                                    @case('signo')
                                        <td>{{ $data->desc_signo }}</td>
                                        @break
                                    @case('subdiario')
                                        <td>{{ $data->desc_subdiario }}</td>
                                        @break
                                    @case('asientocontable')
                                        <td>{{ $data->desc_asientocontable }}</td>
                                        @break
                                    @case('estado')
                                        <td>{{ $data->desc_estado }}</td>
                                        @break
                                    @case('retieneiva')
                                        <td>{{ $data->desc_retieneiva }}</td>
                                        @break
                                    @case('retieneganancia')
                                        <td>{{ $data->desc_retieneganancia }}</td>
                                        @break
                                    @case('retieneIIBB')
                                        <td>{{ $data->desc_retieneiibb }}</td>
                                        @break
                                @endswitch
                            @endforeach
                            <td class="text-nowrap text-center">
                                @if (can('editar-tipo-transaccion-compra', false))
                                    <a href="{{ route('editar_tipotransaccion_compra', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-tipo-transaccion-compra', false))
                                <form action="{{ route('eliminar_tipotransaccion_compra', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        {{ $datas->appends($filtrosQuery ?? [])->links() }}
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tipotransaccion-compra-overlay',
    'tituloId' => 'tipotransaccion-compra-titulo',
    'subtituloId' => 'tipotransaccion-compra-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo. Pulse Esc para cerrar este aviso.',
])
@endsection
