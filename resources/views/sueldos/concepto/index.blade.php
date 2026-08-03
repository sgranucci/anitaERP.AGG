@extends("theme.$theme.layout")
@section('titulo')
    Conceptos de liquidaci&oacute;n
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/concepto/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Sueldos\ConceptoSueldosListadoFiltros; use App\Support\Sueldos\ConceptoTipo; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Conceptos de liquidaci&oacute;n</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('actualizar-concepto-sueldos', false))
                        <form action="{{ route('sincronizar_concepto_sueldos') }}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Sincronizar conceptos desde Anita (haberes + habformula)? Se agregarán los faltantes y se refrescarán los seeds sin fórmulas Anita importadas.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                            </button>
                        </form>
                        <form action="{{ route('retraducir_formulas_concepto_sueldos') }}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Retraducir las fórmulas Anita ya importadas a sintaxis ERP? Actualiza formula/cantidad/valor de cada concepto con líneas.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-fw fa-magic"></i> Retraducir f&oacute;rmulas
                            </button>
                        </form>
                        <form action="{{ route('reclasificar_papo_concepto_sueldos') }}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Reclasificar conceptos ≥3000 según Anita? Contribución empleador (va al recibo CE) vs informativo (solo reportes). Corrige también va_recibo.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                <i class="fa fa-fw fa-tags"></i> Reclasificar papo (recibo CE)
                            </button>
                        </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-concepto-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ConceptoSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_concepto_sueldos'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-concepto-sueldos',
                        'toggleId' => 'btn-toggle-filtros-concepto-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_concepto_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-concepto-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_concepto_sueldos') }}" id="form-filtros-concepto-sueldos" class="mb-0">
                @include('sueldos.concepto.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_concepto_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_concepto_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Tipo</th>
                            <th>Momento</th>
                            <th class="text-center" style="width:90px">Recibo</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->descripcion }}</td>
                            <td>{{ ConceptoTipo::etiquetaTipo($data->tipo) }}</td>
                            <td>{{ ConceptoTipo::etiquetaMomento($data->momento) }}</td>
                            <td class="text-center">
                                @if ($data->va_recibo)
                                    <span class="badge badge-success">S&iacute;</span>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-concepto-sueldos', false))
                                    <a href="{{route('editar_concepto_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-concepto-sueldos', false))
                                    <form action="{{route('eliminar_concepto_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
