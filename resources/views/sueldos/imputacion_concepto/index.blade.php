@extends("theme.$theme.layout")
@section('titulo') Imputación contable de conceptos @endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/imputacion_concepto/filtro.js') }}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\ConceptoImputacionSueldosListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('consultar_imputacion_concepto_sueldos', ConceptoImputacionSueldosListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Imputación contable de conceptos</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.sueldos.boton-manual')
                    @if (can('editar-cuentas-automaticas-contables', false))
                        <a href="{{ route('cuentas_automaticas_contables') }}" class="btn btn-outline-secondary btn-sm mr-1">
                            <i class="fa fa-link"></i> Cuentas automáticas
                        </a>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-imputacion-concepto-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ConceptoImputacionSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-imputacion-concepto-sueldos',
                        'toggleId' => 'btn-toggle-filtros-imputacion-concepto-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_imputacion_concepto_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-imputacion-concepto-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_imputacion_concepto_sueldos') }}" id="form-filtros-imputacion-concepto-sueldos" class="mb-0">
                @include('sueldos.imputacion_concepto.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('sueldos.imputacion_concepto.partials.filtros_externos')
            @include('sueldos.imputacion_concepto.partials.cobertura')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_imputacion_concepto_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Empresa</th>
                            <th>Alcance</th>
                            <th>Clave</th>
                            <th>Debe</th>
                            <th>Haber</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ optional($data->empresa)->nombre }}</td>
                            <td>{{ $data->alcanceLabel() }}</td>
                            <td>{{ $data->clave_label ?? $data->claveLabel() }}</td>
                            <td>
                                @if ($data->cuentaDebe)
                                    {{ $data->cuentaDebe->codigo }} — {{ $data->cuentaDebe->nombre }}
                                @endif
                            </td>
                            <td>
                                @if ($data->cuentaHaber)
                                    {{ $data->cuentaHaber->codigo }} — {{ $data->cuentaHaber->nombre }}
                                @endif
                            </td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-imputacion-concepto-sueldos', false))
                                    <a href="{{ route('editar_imputacion_concepto_sueldos', ['id' => $data->id] + $retornoListadoQuery) }}"
                                       class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-imputacion-concepto-sueldos', false))
                                    <form action="{{ route('eliminar_imputacion_concepto_sueldos', $data->id) }}" method="POST" class="d-inline form-eliminar">
                                        @csrf
                                        @method('DELETE')
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
