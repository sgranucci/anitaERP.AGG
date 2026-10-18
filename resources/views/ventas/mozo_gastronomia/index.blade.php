@extends("theme.$theme.layout")
@section('titulo')
    Mozos gastronomía
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/mozo_gastronomia/filtro.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Ventas\MozoGastronomiaListadoFiltros;
    $esCrown = $esCrown ?? false;
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('consultar_mozo_gastronomia', MozoGastronomiaListadoFiltros::paraQueryString(array_merge(
        MozoGastronomiaListadoFiltros::filtrosVacios(),
        [
            'sala_id' => $filtros['sala_id'] ?? null,
            'sala_scope' => $filtros['sala_scope'] ?? 'todas',
        ]
    )));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($sinMozosCargados ?? false))
        <div class="alert alert-info">
            @if (config('app.anita_sync_mozo_gastronomia_index'))
            No hay mozos en el ERP. Para importar desde Anita ejecute en el servidor:
            <code>php artisan mozo-gastronomia:sincronizar-anita</code>
            @else
            No hay mozos en el ERP. Cree registros con <strong>Nuevo registro</strong>.
            @endif
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Mozos gastronomía</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-mozo-gastronomia',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => MozoGastronomiaListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-mozo-gastronomia',
                        'toggleId' => 'btn-toggle-filtros-mozo-gastronomia',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_mozo_gastronomia', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-mozo-gastronomia',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_mozo_gastronomia') }}" id="form-filtros-mozo-gastronomia" class="mb-0">
                @include('ventas.mozo_gastronomia.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('ventas.partials.filtros_externos_sala_crown', [
                'rutaIndex' => 'consultar_mozo_gastronomia',
                'exportRuta' => 'lista_mozo_gastronomia',
                'exportQueryparams' => $filtrosQuery ?? [],
                'sala_query' => $sala_query ?? collect(),
            ])
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:3.2rem;">ID</th>
                            <th>Nombre</th>
                            <th>Código Anita</th>
                            @if ($esCrown)
                                <th>Sala</th>
                            @else
                                <th>Empresa</th>
                            @endif
                            <th class="text-nowrap" style="width:7.5rem;min-width:7.5rem;" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->codigo }}</td>
                            @if ($esCrown)
                                <td>{{ optional($data->sala)->codigo }} {{ optional($data->sala)->nombre }}</td>
                            @else
                                <td>{{ optional($data->empresa)->nombre }}</td>
                            @endif
                            <td class="text-nowrap" style="width:7.5rem;min-width:7.5rem;white-space:nowrap;">
                                @if (can('editar-mozo-gastronomia', false))
                                    <a href="{{ route('editar_mozo_gastronomia', array_merge(['id' => $data->id], $retornoListadoQuery)) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-mozo-gastronomia', false))
                                <form action="{{ route('eliminar_mozo_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
                            <td colspan="5" class="text-center text-muted py-3">Sin registros</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($datas instanceof \Illuminate\Pagination\AbstractPaginator && $datas->hasPages())
            <div class="card-footer clearfix">
                <div class="float-left">
                    <p class="text-sm text-muted mb-0">
                        Mostrando {{ $datas->firstItem() }} a {{ $datas->lastItem() }} de {{ $datas->total() }} registros
                    </p>
                </div>
                <div class="float-right">
                    {{ $datas->links() }}
                </div>
            </div>
            @elseif ($datas instanceof \Illuminate\Pagination\AbstractPaginator && $datas->total() > 0)
            <div class="card-footer clearfix">
                <p class="text-sm text-muted mb-0">{{ $datas->total() }} registro(s)</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
