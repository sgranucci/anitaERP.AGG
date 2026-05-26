@extends("theme.$theme.layout")
@section('titulo')
Préstamos de materiales
@endsection

@section("scripts")
<script src="{{asset('assets/pages/scripts/admin/index.js')}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-handshake-o"></i> Préstamos de materiales</h3>
                <div class="card-tools">
                    @if (can('crear-prestamo', false))
                        <a href="{{ route('crear_prestamo') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-fw fa-plus-circle"></i> Nuevo préstamo
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data-2">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Fecha préstamo</th>
                            <th>Devuelve</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Solicitante</th>
                            <th>Estado</th>
                            <th>Items</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prestamos as $p)
                            <tr>
                                <td>{{ $p->id }}</td>
                                <td><strong>{{ $p->codigo }}</strong></td>
                                <td>{{ optional($p->fecha_prestamo)->format('d/m/Y') }}</td>
                                <td>
                                    @php
                                        $vencido = $p->estaVencido();
                                    @endphp
                                    <span @if ($vencido) class="text-danger" title="Vencido" @endif>
                                        {{ optional($p->fecha_devolucion_prometida)->format('d/m/Y') }}
                                        @if ($vencido)
                                            <i class="fa fa-exclamation-circle"></i>
                                        @endif
                                    </span>
                                </td>
                                <td>{{ optional($p->depositoOrigen)->nombre }}</td>
                                <td>{{ optional($p->depositoDestino)->nombre }}</td>
                                <td>{{ optional($p->solicitante)->nombre }}</td>
                                <td>
                                    @include('stock.prestamo.partials.estado_badge', ['estado' => $p->estado])
                                </td>
                                <td class="text-right">{{ $p->items->count() }}</td>
                                <td>
                                    <a href="{{ route('ver_prestamo', ['id' => $p->id]) }}" class="btn-accion-tabla tooltipsC" title="Ver detalle">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    @if ($p->estado === 'BORRADOR' && can('editar-prestamo', false))
                                        <a href="{{ route('editar_prestamo', ['id' => $p->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if ($p->estado === 'BORRADOR' && can('borrar-prestamo', false))
                                        <form action="{{ route('eliminar_prestamo', ['id' => $p->id]) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method("delete")
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
        </div>
    </div>
</div>
@endsection
