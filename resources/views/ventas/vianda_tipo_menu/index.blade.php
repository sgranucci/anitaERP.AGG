@extends("theme.$theme.layout")
@section('titulo')
    Tipos de men&uacute; de vianda
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/vianda_tipo_menu/replicar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/vianda_tipo_menu/replicar.js')) }}" type="text/javascript"></script>
<script>
(function ($) {
    'use strict';
    $(function () {
        $('#form-filtro-empresa-vianda-tipo-menu').find('#empresa_id').on('change', function () {
            this.form.submit();
        });
    });
})(jQuery);
</script>
@endsection

@section('contenido')
@php
    $puedeReplicar = ! empty($puede_replicar_vianda_tipo_menu)
        && ($empresa_query_replicar ?? collect())->count() > 1;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($sinRegistros ?? false))
        <div class="alert alert-info">
            No hay tipos de men&uacute; de vianda en el ERP. Para importar desde Anita ejecute
            <code>php artisan vianda:sincronizar-tipos-menu-anita</code>
            o cree registros con <strong>Nuevo registro</strong>.
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tipos de men&uacute; de vianda</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (! empty($mostrarFiltroEmpresa))
                    <form method="get" action="{{ route('consultar_vianda_tipo_menu_gastronomia') }}" class="form-inline mr-2 mb-1 mb-sm-0" id="form-filtro-empresa-vianda-tipo-menu">
                        @include('includes.listado.filtro_empresa_asignada_inline', [
                            'empresa_query' => $empresa_query ?? [],
                            'empresa_id' => $empresa_id ?? null,
                            'label_class' => 'mr-1 mb-0 small text-white-50',
                            'select_class' => 'form-control-sm',
                            'permite_todas' => true,
                            'opcion_todas' => 'Todas (asignadas)',
                        ])
                        <button type="submit" class="btn btn-light btn-sm" title="Consultar">
                            <i class="fa fa-search"></i>
                        </button>
                    </form>
                    @endif
                    @if (can('crear-vianda-tipo-menu-gastronomia', false))
                    <a href="{{ route('crear_vianda_tipo_menu_gastronomia') }}" class="btn btn-outline-secondary btn-sm mb-1 mb-sm-0">
                        <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Nombre</th>
                            <th class="width80">Estado</th>
                            <th>C&oacute;d. Anita</th>
                            @foreach ($diasSemana as $dia => $etiqueta)
                            <th>{{ $etiqueta }}</th>
                            @endforeach
                            <th class="text-nowrap" data-orderable="false" style="min-width: 160px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ optional($data->empresa)->nombre ?: '—' }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->etiquetaEstado() }}</td>
                            <td>{{ $data->codigo_anita ?: '—' }}</td>
                            @foreach ($diasSemana as $dia => $etiqueta)
                            <td class="small">
                                @php
                                    $itemsDia = $data->articulos
                                        ->where('dia_semana', $dia)
                                        ->sortBy('orden')
                                        ->map(function ($linea) {
                                            $art = $linea->articulo;
                                            if ($art === null) {
                                                return null;
                                            }
                                            return trim($art->sku.' — '.$art->descripcion);
                                        })
                                        ->filter()
                                        ->values();
                                @endphp
                                @if ($itemsDia->isEmpty())
                                    <span class="text-muted">—</span>
                                @else
                                    @foreach ($itemsDia as $item)
                                        <div>{{ $item }}</div>
                                    @endforeach
                                @endif
                            </td>
                            @endforeach
                            <td class="text-nowrap">
                                <span class="d-inline-flex flex-nowrap align-items-center" style="gap: 4px;">
                                @if (can('editar-vianda-tipo-menu-gastronomia', false))
                                    <a href="{{ route('editar_vianda_tipo_menu_gastronomia', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if ($puedeReplicar)
                                    <button type="button"
                                       class="btn btn-outline-primary btn-xs btn-replicar-vianda-tipo-menu"
                                       title="Replicar men&uacute; a otras empresas"
                                       data-id="{{ $data->id }}"
                                       data-nombre="{{ $data->nombre }}"
                                       data-empresa-id="{{ (int) $data->empresa_id }}"
                                       data-empresa-nombre="{{ optional($data->empresa)->nombre }}"
                                       data-url="{{ route('replicar_vianda_tipo_menu_gastronomia', ['id' => $data->id]) }}">
                                        <i class="fa fa-copy"></i> Replicar
                                    </button>
                                @endif
                                @if (can('borrar-vianda-tipo-menu-gastronomia', false))
                                <form action="{{ route('eliminar_vianda_tipo_menu_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('ventas.vianda_tipo_menu.partials.modal_replicar')
@endsection
