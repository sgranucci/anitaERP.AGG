@extends("theme.$theme.layout")
@section('titulo')
Recepciones de proveedores
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/filtro.js') }}" type="text/javascript"></script>
@include('stock.recepcion_proveedor.partials.banner_confirmando_styles')
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/confirmar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor/confirmar.js')) ?: time() }}" type="text/javascript"></script>
@endsection

<?php use App\Support\Stock\RecepcionProveedorListadoFiltros; ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-truck"></i> Recepciones de proveedores</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.stock.boton-manual-recepcion-movstock')
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-recepcion',
                        'filtroValor' => $filtros['filtro_valor'] ?? '',
                        'tieneCriterios' => RecepcionProveedorListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('recepcion_proveedor'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-recepcion',
                        'toggleId' => 'btn-toggle-filtros-recepcion',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_recepcion_proveedor'),
                        'nuevoRegistroCan' => 'crear-recepcion-proveedor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('recepcion_proveedor') }}" id="form-filtros-recepcion" class="mb-0">
                @include('stock.recepcion_proveedor.partials.filtros_listado', [
                    'limpiarUrl' => route('recepcion_proveedor'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', ['ruta' => 'lista_recepcion_proveedor', 'queryparams' => $filtrosQuery ?? []])
                <table id="tabla-paginada" class="table table-hover table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Nº recepción</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>OC</th>
                            <th>Proveedor</th>
                            <th>Empresa</th>
                            <th>Estado</th>
                            <th>Diferencias</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($coleccion as $row)
                        @php
                            $tieneDiff = $row->fl_precio_diferencia || $row->fl_diferencia_cantidad || $row->fl_articulo_extra || $row->fl_faltante_oc;
                            $esBorrador = ($row->estado ?? '') === 'BORRADOR';
                        @endphp
                        <tr class="@if($esBorrador) table-secondary @elseif($tieneDiff) table-warning @endif">
                            <td>
                                @if($esBorrador)
                                    <strong>{{ $row->numerorecepcion }}</strong>
                                @else
                                    {{ $row->numerorecepcion }}
                                @endif
                            </td>
                            <td>{{ $row->fecha ? date('d/m/Y', strtotime($row->fecha)) : '' }}</td>
                            <td>{{ $row->tipo }}</td>
                            <td>
                                @if($row->ordencompra_id && (can('editar-ordencompra', false) || can('listar-ordencompra', false)))
                                <a href="{{ route('editar_ordencompra', ['id' => $row->ordencompra_id]) }}"
                                   class="text-primary" target="_blank" rel="noopener" title="Abrir orden de compra">
                                    {{ $row->numeroordencompra }}
                                </a>
                                @else
                                {{ $row->numeroordencompra }}
                                @endif
                            </td>
                            <td>{{ $row->nombreproveedor }}</td>
                            <td>{{ $row->nombreempresa }}</td>
                            <td>
                                @include('stock.recepcion_proveedor.partials.estado_badge', ['estado' => $row->estado ?? ''])
                            </td>
                            <td class="text-nowrap">
                                @if($row->fl_precio_diferencia)<span class="badge badge-warning" title="Precio">P</span>@endif
                                @if($row->fl_diferencia_cantidad)<span class="badge badge-warning" title="Cantidad">C</span>@endif
                                @if($row->fl_articulo_extra)<span class="badge badge-info" title="Extra/sustituto">A</span>@endif
                                @if($row->fl_faltante_oc)<span class="badge badge-danger" title="Faltante OC">F</span>@endif
                                @if($row->fl_laboratorio)<span class="badge badge-primary" title="Laboratorio">LAB</span>@endif
                                @if(!$tieneDiff && !$row->fl_laboratorio)—@endif
                            </td>
                            <td class="text-nowrap">
                                @include('stock.recepcion_proveedor.partials.boton_imprimir_com_pdf', [
                                    'recepcionId' => $row->id,
                                    'modo' => 'tabla',
                                ])
                                @if (can('editar-recepcion-proveedor', false) || can('actualizar-recepcion-proveedor', false))
                                <a href="{{ url('stock/recepcion-proveedor/'.$row->id.'/editar') }}" class="btn-accion-tabla tooltipsC" title="{{ $row->estado === 'BORRADOR' ? 'Editar borrador' : 'Ver recepción' }}">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endif
                                @if($row->estado === 'BORRADOR' && can('actualizar-recepcion-proveedor', false))
                                <a href="{{ route('editar_recepcion_proveedor', ['id' => $row->id, 'enfocar_oc' => 1]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Cambiar orden de compra">
                                    <i class="fa fa-exchange text-warning"></i>
                                </a>
                                @endif
                                @if($row->estado === 'BORRADOR' && can('confirmar-recepcion-proveedor', false))
                                <form action="{{ route('confirmar_recepcion_proveedor', $row->id) }}" class="d-inline form-confirmar-recepcion" method="POST"
                                      data-confirm-msg="¿Confirmar recepción {{ $row->numerorecepcion }}? Generará movimiento de stock y asiento contable.">
                                    @csrf
                                    <button type="submit" class="btn-accion-tabla tooltipsC" title="Confirmar recepción">
                                        <i class="fa fa-check text-success"></i>
                                    </button>
                                </form>
                                @endif
                                @if($row->estado === 'BORRADOR' && can('borrar-recepcion-proveedor', false))
                                <form action="{{ route('eliminar_recepcion_proveedor', ['id' => $row->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar borrador">
                                        <i class="fa fa-trash text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                {{ $coleccion->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
