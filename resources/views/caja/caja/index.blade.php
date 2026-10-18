@extends("theme.$theme.layout")
@section('titulo')
    Cajas
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/caja/filtro.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Caja\CajaListadoFiltros;
    use App\Support\Configuracion\EntornoEmpresaSupport;
    $mostrarSala = EntornoEmpresaSupport::esCrown();
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('consulta_caja', CajaListadoFiltros::paraQueryStringSala($filtros ?? []));
@endphp
<style>
    #tabla-paginada th.col-acciones,
    #tabla-paginada td.col-acciones {
        position: sticky;
        right: 0;
        z-index: 2;
        background: #fff;
        box-shadow: -4px 0 6px -4px rgba(0, 0, 0, 0.25);
        white-space: nowrap;
        width: 4.5rem;
        min-width: 4.5rem;
    }
    #tabla-paginada thead th.col-acciones {
        background: #85C1E9;
        z-index: 3;
    }
    #tabla-paginada tbody tr:nth-of-type(odd) td.col-acciones {
        background: #f2f2f2;
    }
    #tabla-paginada tbody tr:hover td.col-acciones {
        background: #e8f4fc;
    }
</style>
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cajas</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-caja',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CajaListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-caja',
                        'toggleId' => 'btn-toggle-filtros-caja',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crea_caja', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-caja',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consulta_caja') }}" id="form-filtros-caja" class="mb-0">
                @include('caja.caja.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('caja.caja.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_caja',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:3.2rem;">ID</th>
                            <th style="width:5rem;">C&oacute;digo</th>
                            <th style="min-width:10rem;">Nombre</th>
                            @if ($mostrarSala)
                                <th style="min-width:8rem;">Sala</th>
                            @endif
                            <th class="col-acciones" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo ?? '' }}</td>
                            <td>{{ $data->nombre }}</td>
                            @if ($mostrarSala)
                                <td>
                                    @if ($data->sala)
                                        {{ $data->sala->codigo }} — {{ $data->sala->nombre }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endif
                            <td class="col-acciones">
                                @if (can('editar-caja', false))
                                    <a href="{{ route('edita_caja', ['id' => $data->id] + $retornoListadoQuery) }}"
                                       class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-caja', false))
                                    <form action="{{ route('elimina_caja', ['id' => $data->id]) }}"
                                          class="d-inline form-eliminar" method="POST">
                                        @csrf @method("delete")
                                        <button type="submit" class="btn-accion-tabla eliminar tooltipsC"
                                                title="Eliminar este registro">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $mostrarSala ? 5 : 4 }}" class="text-center text-muted py-4">
                                No hay cajas con los filtros actuales.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@endsection
