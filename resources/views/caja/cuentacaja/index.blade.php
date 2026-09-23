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
<style>
    /* Acciones siempre visibles al scrollear horizontal */
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
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:3.2rem;">ID</th>
                            <th style="min-width:8rem;">Nombre</th>
                            <th style="width:5rem;">Código</th>
                            <th style="width:3.5rem;" title="Orden">Ord</th>
                            <th style="width:5.5rem;">Tipo</th>
                            <th style="width:3.5rem;" title="Es tarjeta / pide cupón">Tarj.</th>
                            <th style="min-width:6rem;">Banco</th>
                            <th style="min-width:6rem;">Empresa</th>
                            <th style="min-width:8rem;">Cta. contable</th>
                            <th style="width:3.5rem;" title="Moneda">Mon</th>
                            <th style="min-width:7rem;">CBU</th>
                            <th style="min-width:6rem;" title="Cuenta Interbanking">Interb.</th>
                            <th style="min-width:7rem;">Usos</th>
                            <th class="col-acciones" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        @php
                            $tipoNombre = '';
                            foreach ($tipocuenta_enum as $tipocuenta) {
                                if (($tipocuenta['valor'] ?? null) == $data->tipocuenta) {
                                    $tipoNombre = (string) ($tipocuenta['nombre'] ?? '');
                                    break;
                                }
                            }
                            $cuentaContable = trim(
                                (string) ($data->cuentacontables->codigo ?? '')
                                .'-'
                                .(string) ($data->cuentacontables->nombre ?? ''),
                                '-'
                            );
                            $usos = $data->usocuentacajas->pluck('nombre')->implode(', ');
                            $nombreTitle = trim((string) ($data->nombre ?? ''));
                            if (trim((string) ($data->descripcion_operaciones ?? '')) !== '') {
                                $nombreTitle .= ' — '.(string) $data->descripcion_operaciones;
                            }
                        @endphp
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td class="small" title="{{ $nombreTitle }}">
                                {{ \Illuminate\Support\Str::limit((string) ($data->nombre ?? ''), 36) }}
                            </td>
                            <td>{{ $data->codigo }}</td>
                            <td class="text-center">{{ $data->orden ?? 0 }}</td>
                            <td class="small" title="{{ $tipoNombre }}">{{ \Illuminate\Support\Str::limit($tipoNombre, 14) }}</td>
                            <td class="text-center small" title="{{ !empty($data->es_tarjeta) ? 'Es tarjeta (pide cupón)' : 'No' }}">
                                {{ !empty($data->es_tarjeta) ? 'Sí' : '' }}
                            </td>
                            <td class="small" title="{{ $data->bancos->nombre ?? '' }}">
                                {{ \Illuminate\Support\Str::limit((string) ($data->bancos->nombre ?? ''), 18) }}
                            </td>
                            <td class="small" title="{{ $data->empresas->nombre ?? '' }}">
                                {{ \Illuminate\Support\Str::limit((string) ($data->empresas->nombre ?? ''), 16) }}
                            </td>
                            <td class="small" title="{{ $cuentaContable }}">
                                {{ \Illuminate\Support\Str::limit($cuentaContable, 28) }}
                            </td>
                            <td class="text-center small" title="{{ $data->monedas->nombre ?? '' }}">
                                {{ $data->monedas->abreviatura ?? \Illuminate\Support\Str::limit((string) ($data->monedas->nombre ?? ''), 4) }}
                            </td>
                            <td class="small" title="{{ $data->cbu }}">
                                {{ \Illuminate\Support\Str::limit((string) ($data->cbu ?? ''), 14) }}
                            </td>
                            <td class="small" title="{{ $data->cuenta_interbanking }}">
                                {{ \Illuminate\Support\Str::limit((string) ($data->cuenta_interbanking ?? ''), 12) }}
                            </td>
                            <td class="small" title="{{ $usos }}">
                                {{ \Illuminate\Support\Str::limit($usos, 24) }}
                            </td>
                            <td class="col-acciones">
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
