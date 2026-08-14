@extends("theme.$theme.layout")
@section('titulo')
    Imputaciones de p&eacute;rdida
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/imputacion_perdida/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\ImputacionPerdidaListadoFiltros;
@endphp

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Imputaciones de p&eacute;rdida</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-imputacion-perdida',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ImputacionPerdidaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('imputacion_perdida'),
                        'placeholder' => 'B&uacute;squeda r&aacute;pida (tolera errores de tipeo)&hellip;',
                        'toggleTarget' => '#panel-filtros-imputacion-perdida',
                        'toggleId' => 'btn-toggle-filtros-imputacion-perdida',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_imputacion_perdida', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-imputacion-perdida',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('imputacion_perdida') }}" id="form-filtros-imputacion-perdida" class="mb-0">
                @include('caja.imputacion_perdida.partials.filtros_listado', [
                    'limpiarUrl' => route('imputacion_perdida'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_imputacion_perdida',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th class="width80">C&oacute;digo</th>
                            <th>Nombre</th>
                            <th>Empresas / cuentas</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>
                                @forelse (($data->empresas ?? []) as $lineaEmp)
                                    <div class="small mb-1">
                                        <strong>{{ $lineaEmp->empresa->nombre ?? ('#'.$lineaEmp->empresa_id) }}</strong>
                                        <span class="text-muted">—</span>
                                        {{ $lineaEmp->cuentacontable->codigo ?? '' }}
                                        {{ $lineaEmp->cuentacontable->nombre ?? '' }}
                                    </div>
                                @empty
                                    <span class="text-muted">Sin empresas</span>
                                @endforelse
                            </td>
                            <td>
                                @if (can('editar-imputacion-perdida', false))
                                    <a href="{{ route('editar_imputacion_perdida', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-imputacion-perdida', false))
                                <form action="{{ route('eliminar_imputacion_perdida', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
            @if (method_exists($datas, 'links'))
                <div class="card-footer clearfix">
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
