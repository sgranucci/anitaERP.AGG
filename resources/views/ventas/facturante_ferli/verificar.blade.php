@extends("theme.$theme.layout")
@section('titulo')
    Verificacion importacion Facturante
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="card card-{{ $resumen['todo_ok'] ? 'success' : 'danger' }}">
            <div class="card-header">
                <h3 class="card-title">Verificacion del {{date('d/m/Y', strtotime($desdefecha))}} al {{date('d/m/Y', strtotime($hastafecha))}}</h3>
                <div class="card-tools">
                    <a href="{{route('crear_importacion_facturas_tiendanube')}}" class="btn btn-default btn-sm">Volver</a>
                </div>
            </div>
            <div class="card-body">
                <p><strong>{{ $resumen['mensaje'] }}</strong></p>
                <div class="row">
                    <div class="col-md-2">
                        <div class="info-box bg-info">
                            <span class="info-box-icon"><i class="fas fa-file-invoice"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Facturante</span>
                                <span class="info-box-number">{{ $resumen['total'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="info-box bg-success">
                            <span class="info-box-icon"><i class="fas fa-check"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Completos</span>
                                <span class="info-box-number">{{ $resumen['completos'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="info-box bg-warning">
                            <span class="info-box-icon"><i class="fas fa-box"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Sin stock</span>
                                <span class="info-box-number">{{ $resumen['sin_stock'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="info-box bg-danger">
                            <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Sin admin</span>
                                <span class="info-box-number">{{ $resumen['sin_admin'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="info-box bg-secondary">
                            <span class="info-box-icon"><i class="fas fa-minus-circle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">No importa</span>
                                <span class="info-box-number">{{ $resumen['no_importa'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                @if(!$resumen['todo_ok'])
                <div class="alert alert-warning">
                    Hay comprobantes pendientes.
                    @if($resumen['sin_admin'] > 0)
                        Use <strong>Leer comprobantes del periodo</strong> e <strong>Importar a Anita</strong>.
                    @endif
                    @if($resumen['sin_stock'] > 0)
                        Use <strong>Recuperar stock local (Lugano)</strong>.
                    @endif
                </div>
                @endif
            </div>
        </div>

        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Detalle por comprobante</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Comprobante</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Total</th>
                            <th>Medio pago</th>
                            <th>Admin</th>
                            <th>Stock Lugano</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detalle as $fila)
                        <tr>
                            <td>{{ $fila['comprobante'] }}</td>
                            <td>{{ $fila['fecha'] }}</td>
                            <td>{{ $fila['cliente'] }}</td>
                            <td>{{ $fila['total'] }}</td>
                            <td>{{ $medioPago_enum[$fila['mediopago']] ?? $fila['mediopago'] }}</td>
                            <td>{!! $fila['en_admin'] ? '<span class="badge badge-success">Si</span>' : '<span class="badge badge-danger">No</span>' !!}</td>
                            <td>{!! $fila['en_stock'] ? '<span class="badge badge-success">Si</span>' : '<span class="badge badge-warning">No</span>' !!}</td>
                            <td>
                                @if($fila['estado'] === 'completo')
                                    <span class="badge badge-success">{{ $fila['estado_texto'] }}</span>
                                @elseif($fila['estado'] === 'sin_stock')
                                    <span class="badge badge-warning">{{ $fila['estado_texto'] }}</span>
                                @elseif($fila['estado'] === 'sin_admin')
                                    <span class="badge badge-danger">{{ $fila['estado_texto'] }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ $fila['estado_texto'] }}</span>
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
@endsection
