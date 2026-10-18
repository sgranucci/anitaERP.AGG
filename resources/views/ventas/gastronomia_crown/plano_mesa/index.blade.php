@extends("theme.$theme.layout")
@section('titulo')
    Planos de mesas
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia_crown/plano_mesa_filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia_crown/plano_mesa_filtro.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Ventas\GastronomiaCrown\GastroPlanoMesaListadoFiltros;
    $limpiarUrl = route('consultar_plano_mesa_gastronomia_crown', GastroPlanoMesaListadoFiltros::paraQueryString(array_merge(
        GastroPlanoMesaListadoFiltros::filtrosVacios(),
        [
            'sala_id' => $filtros['sala_id'] ?? null,
            'sala_scope' => $filtros['sala_scope'] ?? 'todas',
            'empresa_id' => $filtros['empresa_id'] ?? null,
            'empresa_scope' => $filtros['empresa_scope'] ?? 'una',
        ]
    )));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-th-large"></i> Planos de mesas</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-plano-mesa',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => GastroPlanoMesaListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-plano-mesa',
                        'toggleId' => 'btn-toggle-filtros-plano-mesa',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_plano_mesa_gastronomia_crown', $filtrosQuery ?? []),
                        'nuevoRegistroCan' => 'crear-plano-mesa-gastronomia-crown',
                        'nuevoRegistroLabel' => 'Nuevo plano',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_plano_mesa_gastronomia_crown') }}" id="form-filtros-plano-mesa" class="mb-0">
                @include('ventas.gastronomia_crown.plano_mesa.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('ventas.partials.filtros_externos_sala_crown', [
                'rutaIndex' => 'consultar_plano_mesa_gastronomia_crown',
                'exportRuta' => 'lista_plano_mesa_gastronomia_crown',
                'exportQueryparams' => $filtrosQuery ?? [],
                'sala_query' => $sala_query ?? collect(),
                'empresa_query' => $empresa_query ?? collect(),
            ])
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:3.2rem;">ID</th>
                            <th style="width:6rem;">Código</th>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>Sala</th>
                            <th class="text-right" style="width:6rem;">Mesas</th>
                            <th style="width:5rem;">Orden</th>
                            <th style="width:6rem;">Estado</th>
                            <th class="text-nowrap" style="width:7.5rem;min-width:7.5rem;" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ optional($data->empresa)->nombre }}</td>
                            <td>{{ optional($data->sala)->codigo }} {{ optional($data->sala)->nombre }}</td>
                            <td class="text-right">{{ $data->cantidadMesas() }}</td>
                            <td>{{ $data->orden }}</td>
                            <td>
                                @if ($data->activo)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-secondary">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-nowrap" style="width:7.5rem;min-width:7.5rem;white-space:nowrap;">
                                @if (can('editar-plano-mesa-gastronomia-crown', false))
                                    <a href="{{ route('editar_plano_mesa_gastronomia_crown', array_merge(['id' => $data->id], $filtrosQuery ?? [])) }}"
                                       class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('eliminar-plano-mesa-gastronomia-crown', false))
                                <form action="{{ route('eliminar_plano_mesa_gastronomia_crown', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method('delete')
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-3">Sin planos cargados</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($datas->hasPages())
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
            @elseif ($datas->total() > 0)
            <div class="card-footer clearfix">
                <p class="text-sm text-muted mb-0">{{ $datas->total() }} registro(s)</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
