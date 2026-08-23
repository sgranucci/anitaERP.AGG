@extends("theme.$theme.layout")
@section('titulo')
    Cuentas de Caja
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/caja/cuentacaja/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Caja\CuentacajaListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('cuentacaja', CuentacajaListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cuentas de Caja</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cuentacaja',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CuentacajaListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-cuentacaja',
                        'toggleId' => 'btn-toggle-filtros-cuentacaja',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_cuentacaja', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-cuentas-de-caja',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cuentacaja') }}" id="form-filtros-cuentacaja" class="mb-0">
                @include('caja.cuentacaja.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('caja.cuentacaja.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_cuentacaja',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Desc. operaciones</th>
                            <th>Código</th>
                            <th>Orden</th>
                            <th>Tipo cuenta</th>
                            <th>Banco</th>
                            <th>Empresa</th>
                            <th>Cuenta contable</th>
                            <th>Moneda</th>
                            <th>CBU</th>
                            <th>Cuenta Interbanking</th>
                            <th>Usos</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombre}}</td>
                            <td>{{ $data->descripcion_operaciones }}</td>
                            <td>{{$data->codigo}}</td>
                            <td>{{ $data->orden ?? 0 }}</td>
                            <td>@foreach($tipocuenta_enum as $tipocuenta)
									@if ($tipocuenta['valor'] == $data->tipocuenta)
										{{ $tipocuenta['nombre'] }}
									@endif
								@endforeach
                            </td>
                            <td>{{$data->bancos->nombre ?? ''}}</td>
                            <td>{{$data->empresas->nombre ?? ''}}</td>
                            <td>{{$data->cuentacontables->codigo ?? ''}}-{{$data->cuentacontables->nombre??''}}</td>
                            <td>{{$data->monedas->nombre ?? ''}}</td>
                            <td>{{$data->cbu}}</td>
                            <td>{{$data->cuenta_interbanking}}</td>
                            <td><small>{{ $data->usocuentacajas->pluck('nombre')->implode(', ') }}</small></td>
                            <td>
                       			@if (can('editar-cuentas-de-caja', false))
                                	<a href="{{route('editar_cuentacaja', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-cuentas-de-caja', false))
                                <form action="{{route('eliminar_cuentacaja', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
