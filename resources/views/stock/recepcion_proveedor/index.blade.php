@extends("theme.$theme.layout")
@section('titulo')
Recepciones de proveedores
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor/filtro.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-truck"></i> Recepciones de proveedores</h3>
                <div class="card-tools">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-recepcion',
                        'nuevoRegistroUrl' => route('crear_recepcion_proveedor'),
                        'nuevoRegistroCan' => 'crear-recepcion-proveedor',
                    ])
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('stock.recepcion_proveedor.partials.filtros_listado')
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
                        @endphp
                        <tr class="@if($tieneDiff) table-warning @endif">
                            <td>{{ $row->numerorecepcion }}</td>
                            <td>{{ $row->fecha ? date('d/m/Y', strtotime($row->fecha)) : '' }}</td>
                            <td>{{ $row->tipo }}</td>
                            <td>{{ $row->numeroordencompra }}</td>
                            <td>{{ $row->nombreproveedor }}</td>
                            <td>{{ $row->nombreempresa }}</td>
                            <td>{{ $row->estado }}</td>
                            <td class="text-nowrap">
                                @if($row->fl_precio_diferencia)<span class="badge badge-warning" title="Precio">P</span>@endif
                                @if($row->fl_diferencia_cantidad)<span class="badge badge-warning" title="Cantidad">C</span>@endif
                                @if($row->fl_articulo_extra)<span class="badge badge-info" title="Extra/sustituto">A</span>@endif
                                @if($row->fl_faltante_oc)<span class="badge badge-danger" title="Faltante OC">F</span>@endif
                                @if($row->fl_laboratorio)<span class="badge badge-primary" title="Laboratorio">LAB</span>@endif
                                @if(!$tieneDiff && !$row->fl_laboratorio)—@endif
                            </td>
                            <td class="text-nowrap">
                                @can('listar-recepcion-proveedor')
                                <a href="{{ route('recepcion_proveedor_com_pdf', $row->id) }}" class="btn-accion-tabla tooltipsC" title="Imprimir COM PDF" target="_blank">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @endcan
                                @can('editar-recepcion-proveedor')
                                <a href="{{ url('stock/recepcion-proveedor/'.$row->id.'/editar') }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endcan
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
