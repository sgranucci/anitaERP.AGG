@extends("theme.$theme.layout")
@section('titulo')
    Descuentos estacionamiento
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Descuentos estacionamiento</h3>
                <div class="card-tools">
                    @if (can('crear-descuento-estacionamiento', false))
                    <a href="{{ route('crear_estacionamiento_descuento') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Código Anita</th>
                            <th>Tipo valor</th>
                            <th class="text-right">Valor</th>
                            <th>Cliente consumo interno</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $tiposValor[$data->tipovalor] ?? $data->tipovalor }} ({{ $data->tipovalor }})</td>
                            <td class="text-right">{{ number_format((float) $data->valor, 4, ',', '.') }}</td>
                            <td>
                                @if ($data->cliente)
                                    {{ $data->cliente->codigo }} — {{ $data->cliente->nombre }}
                                @endif
                            </td>
                            <td>
                                @if (can('editar-descuento-estacionamiento', false))
                                    <a href="{{ route('editar_estacionamiento_descuento', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-descuento-estacionamiento', false))
                                <form action="{{ route('eliminar_estacionamiento_descuento', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
@endsection
