@extends("theme.$theme.layout")
@section('titulo')
    Acumuladores de liquidaci&oacute;n
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/acumulador/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\AcumuladorSueldosListadoFiltros; ?>
<?php use App\Support\Sueldos\ConceptoTipo; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Acumuladores de liquidaci&oacute;n</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-acumulador-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => AcumuladorSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_acumulador_sueldos'),
                        'placeholder' => 'Búsqueda rápida (código, descripción)…',
                        'toggleTarget' => '#panel-filtros-acumulador-sueldos',
                        'toggleId' => 'btn-toggle-filtros-acumulador-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_acumulador_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-acumulador-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_acumulador_sueldos') }}" id="form-filtros-acumulador-sueldos" class="mb-0">
                @include('sueldos.acumulador.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_acumulador_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_acumulador_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Tipos incluidos</th>
                            <th class="text-center" style="width:70px">Signo</th>
                            <th class="text-center" style="width:70px">Activo</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        @php
                            $tiposTexto = collect($data->tipos_incluye ?? [])
                                ->map(fn ($t) => ConceptoTipo::etiquetaTipo($t))
                                ->implode(', ');
                        @endphp
                        <tr>
                            <td>
                                {{ $data->codigo }}
                                @if ($data->reservado)
                                    <span class="badge badge-secondary ml-1">Reservado</span>
                                @endif
                            </td>
                            <td>{{ $data->descripcion }}</td>
                            <td>{{ $tiposTexto }}</td>
                            <td class="text-center">{{ (int) $data->signo === -1 ? '-1' : '+1' }}</td>
                            <td class="text-center">{{ $data->activo ? 'Sí' : 'No' }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-acumulador-sueldos', false))
                                    <a href="{{route('editar_acumulador_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-acumulador-sueldos', false) && ! $data->reservado)
                                    <form action="{{route('eliminar_acumulador_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@endsection
