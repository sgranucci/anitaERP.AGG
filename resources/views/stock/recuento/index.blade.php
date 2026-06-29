@extends("theme.$theme.layout")
@section('titulo')
Recuento de inventario
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recuento/filtro.js') }}" type="text/javascript"></script>
@endsection

<?php use App\Support\Stock\RecuentoListadoFiltros; ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-clipboard"></i> Recuento de inventario</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.stock.boton-manual')
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-recuento',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => RecuentoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('recuento'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-recuento',
                        'toggleId' => 'btn-toggle-filtros-recuento',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_recuento'),
                        'nuevoRegistroCan' => 'crear-recuento',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('recuento') }}" id="form-filtros-recuento" class="mb-0">
                @if (! empty($ver_todos_recuentos))
                    <input type="hidden" name="ver_todos_recuentos" value="1">
                @endif
                @include('stock.recuento.partials.filtros_listado', [
                    'limpiarUrl' => route('recuento'),
                ])
            </form>
            @if ($ver_todos_recuentos ?? false)
                <div class="px-3 py-2 border-bottom bg-light small d-flex flex-wrap align-items-center justify-content-between">
                    <div>
                        <i class="fa fa-users"></i>
                        Mostrando <strong>todos los recuentos</strong> de los dep&oacute;sitos autorizados.
                    </div>
                    <div class="mt-1 mt-sm-0">
                        @if ($puede_ver_todos_recuentos ?? false)
                            <a href="{{ route('recuento', RecuentoListadoFiltros::paraQueryStringAlternarAlcance($filtros ?? [], false)) }}"
                               class="btn btn-secondary btn-sm text-nowrap"
                               title="Volver a ver solo los recuentos que usted carg&oacute;">
                                <i class="fa fa-user"></i> Solo mis recuentos
                            </a>
                        @else
                            <span class="btn btn-secondary btn-sm disabled text-nowrap"
                                  title="Sin permiso para ver todos los recuentos">
                                <i class="fa fa-user"></i> Solo mis recuentos
                            </span>
                        @endif
                    </div>
                </div>
            @else
                <div class="px-3 py-2 border-bottom bg-light small d-flex flex-wrap align-items-center justify-content-between">
                    <div class="text-muted">
                        <i class="fa fa-user"></i>
                        Mostrando <strong>sus recuentos</strong> en dep&oacute;sitos autorizados.
                        @if (! ($puede_ver_todos_recuentos ?? false))
                            (Para ver los de otros usuarios necesita el permiso «Listar todos los recuentos de inventario».)
                        @endif
                    </div>
                    <div class="mt-1 mt-sm-0">
                        @if ($puede_ver_todos_recuentos ?? false)
                            <a href="{{ route('recuento', RecuentoListadoFiltros::paraQueryStringAlternarAlcance($filtros ?? [], true)) }}"
                               class="btn btn-primary btn-sm text-nowrap"
                               title="Ver recuentos de todos los usuarios (dep&oacute;sitos autorizados)">
                                <i class="fa fa-users"></i> Ver todos los recuentos
                            </a>
                        @else
                            <span class="btn btn-primary btn-sm disabled text-nowrap"
                                  title="Requiere el permiso «Listar todos los recuentos de inventario»">
                                <i class="fa fa-users"></i> Ver todos los recuentos
                            </span>
                        @endif
                    </div>
                </div>
            @endif
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_recuento',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Depósito</th>
                            <th>Empresa</th>
                            <th>Usuario</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Líneas</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recuentos as $r)
                            <tr>
                                <td>{{ $r->id }}</td>
                                <td><strong>{{ $r->codigo }}</strong></td>
                                <td>{{ optional($r->fecha)->format('d/m/Y') }}</td>
                                <td>{{ optional($r->deposito)->etiqueta() }}</td>
                                <td>{{ optional($r->empresa)->nombre }}</td>
                                <td>{{ optional($r->usuario)->nombre }}</td>
                                <td>{{ $r->tipo }}</td>
                                <td>@include('stock.recuento.partials.estado_badge', ['estado' => $r->estado])</td>
                                <td class="text-right">{{ $r->items_count ?? $r->items->count() }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('ver_recuento', ['id' => $r->id]) }}" class="btn-accion-tabla tooltipsC" title="Ver detalle">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    @include('stock.recuento.partials.botones_exportar', ['recuento' => $r, 'modo' => 'fila'])
                                    @if ($r->esEditable() && can('editar-recuento', false))
                                        <a href="{{ route('editar_recuento', ['id' => $r->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if ($r->estado === 'PENDIENTE' && can('borrar-recuento', false))
                                        <form action="{{ route('eliminar_recuento', ['id' => $r->id]) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method('delete')
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
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
            @if (method_exists($recuentos, 'links'))
                <div class="card-footer clearfix">
                    {{ $recuentos->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
