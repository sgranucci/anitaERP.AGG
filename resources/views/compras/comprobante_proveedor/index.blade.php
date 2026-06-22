@extends("theme.$theme.layout")
@section('titulo')
    Comprobantes de proveedor
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Comprobantes de proveedor</h3>
                <div class="card-tools">
                    @if (can('crear-comprobante-proveedor', false) || can('listar-precarga-proveedores', false))
                    <a href="{{ route('comprobante_proveedor_opciones_carga') }}" class="btn btn-outline-success btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Cargar factura
                    </a>
                    @endif
                </div>
                <div class="d-md-flex justify-content-md-end">
                    <form action="{{ route('comprobante_proveedor') }}" method="GET">
                        <div class="btn-group">
                            <input type="text" name="busqueda" class="form-control" placeholder="Búsqueda ..." value="{{ $busqueda ?? '' }}">
                            <button type="submit" class="btn btn-default">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla', ['ruta' => 'lista_comprobante_proveedor', 'busqueda' => $busqueda ?? null])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Empresa</th>
                            <th>Proveedor</th>
                            <th>Tipo</th>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Origen</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td><small>{{ $row->empresas->nombre ?? '' }}</small></td>
                            <td><small>{{ $row->proveedores->nombre ?? '' }}</small></td>
                            <td><small>{{ $row->tipotransaccion_compras->nombre ?? '' }}</small></td>
                            <td><small>{{ $row->letra }}{{ $row->sucursal }}-{{ $row->numerocomprobante }}</small></td>
                            <td><small>{{ $row->fechacomprobante ? $row->fechacomprobante->format('d/m/Y') : '' }}</small></td>
                            <td><small>{{ number_format((float) $row->total, 2) }}</small></td>
                            <td><small>{{ $row->estado }}</small></td>
                            <td><small>{{ \App\Support\Compras\ComprobanteProveedorOrigenEntrada::etiqueta($row->origen_entrada ?? '') }}</small></td>
                            <td>
                                @if (can('editar-comprobante-proveedor', false))
                                <a href="{{ route('editar_comprobante_proveedor', ['id' => $row->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                    <i class="fa fa-edit"></i>
                                </a>
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
{{ $datas->appends(['busqueda' => $busqueda])->links() }}
@endsection
