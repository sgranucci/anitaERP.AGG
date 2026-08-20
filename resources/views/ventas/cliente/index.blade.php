@extends("theme.$theme.layout")
@section('titulo')
Clientes
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
@php
    $clienteFiltroJs = public_path('assets/pages/scripts/ventas/cliente/filtro.js');
@endphp
<script src="{{ asset('assets/pages/scripts/ventas/cliente/filtro.js') }}?v={{ file_exists($clienteFiltroJs) ? filemtime($clienteFiltroJs) : time() }}" type="text/javascript"></script>
@endsection

<?php use App\Helpers\biblioteca;
use App\Support\Ventas\ClienteListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $esBierzo = \App\Support\Configuracion\EntornoEmpresaSupport::esElBierzo();
    $filtroCodigo = trim((string) ($filtros['codigo'] ?? ''));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Clientes</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if ($esBierzo)
                        <input type="text"
                               name="filtro_codigo"
                               id="filtro_codigo"
                               form="form-filtros-cliente"
                               class="form-control form-control-sm d-inline-block mr-1{{ $filtroCodigo !== '' ? ' listado-filtros-input-activo' : '' }}"
                               style="width: 110px; vertical-align: middle;"
                               value="{{ $filtroCodigo }}"
                               placeholder="C&oacute;digo"
                               autocomplete="off"
                               title="Filtrar por c&oacute;digo de cliente (al tipear)">
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cliente',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ClienteListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('cliente'),
                        'placeholder' => 'Nombre, CUIT, domicilio… (parecido desde 2 letras)',
                        'toggleTarget' => '#panel-filtros-cliente',
                        'toggleId' => 'btn-toggle-filtros-cliente',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_cliente', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-clientes',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cliente') }}" id="form-filtros-cliente" class="mb-0">
                @include('ventas.cliente.partials.filtros_listado')
            </form>
            <div class="card-body table-responsive p-0">
                <div id="cliente-export-toolbar">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_cliente',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>
                <table class="table table-striped table-bordered table-hover table-sm" id="tabla-paginada" style="font-size: 0.8125rem;">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @if ($esBierzo)
                                <th class="width10">C&oacute;d.</th>
                            @else
                                <th class="width10">ID</th>
                            @endif
                            <th style="min-width: 120px;">Nombre</th>
                            <th style="min-width: 90px;">Vendedor</th>
                            @if ($esBierzo)
                                <th style="min-width: 90px;">Reparto</th>
                            @endif
                            <th style="min-width: 95px;">C.U.I.T.</th>
                            <th style="min-width: 120px;">Domicilio</th>
                            <th style="min-width: 85px;">Localidad</th>
                            <th style="min-width: 85px;">Provincia</th>
                            @if (! $esBierzo)
                                <th class="width10">C&oacute;d.</th>
                            @endif
                            <th class="text-center" style="width: 2.25rem;" title="Estado">St.</th>
                            <th class="width10">APOC</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody id="cliente-listado-filas">
                        @include('ventas.cliente.partials.tabla_filas', [
                            'clientes' => $clientes,
                            'filtrosQuery' => $filtrosQuery ?? [],
                            'retornoListadoQuery' => $retornoListadoQuery,
                            'esBierzo' => $esBierzo,
                        ])
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@include('ventas.cliente.partials.paginacion', [
    'clientes' => $clientes,
    'filtrosQuery' => $filtrosQuery ?? [],
])
@endsection
