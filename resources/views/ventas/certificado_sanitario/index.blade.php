@extends("theme.$theme.layout")
@section('titulo')
    Certificado sanitario SENASA
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
                <h3 class="card-title">Certificados sanitarios SENASA</h3>
                <div class="card-tools">
                    @if (can('crear-certificado-sanitario', false))
                    <a href="{{route('crear_certificado_sanitario')}}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Generar certificado WEB
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-data">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nro</th>
                            <th>Fecha</th>
                            <th>Camión</th>
                            <th>Transporte</th>
                            <th>Precinto</th>
                            <th>XML</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->etiqueta}}</td>
                            <td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
                            <td>{{$data->camion->dominio ?? ''}}</td>
                            <td>{{$data->transporte->nombre ?? ''}}</td>
                            <td>{{$data->precinto}}</td>
                            <td>
                                @if ($data->xml_frio)
                                <a href="{{route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'S'])}}">Frio</a>
                                @endif
                                @if ($data->xml_sin_frio)
                                @if ($data->xml_frio) | @endif
                                <a href="{{route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'N'])}}">Sin frio</a>
                                @endif
                            </td>
                            <td>
                                <a href="{{route('ver_certificado_sanitario', ['id' => $data->id])}}" class="btn-accion-tabla tooltipsC" title="Ver">
                                    <i class="fa fa-eye"></i>
                                </a>
                                @if (can('borrar-certificado-sanitario', false))
                                <form action="{{route('eliminar_certificado_sanitario', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
