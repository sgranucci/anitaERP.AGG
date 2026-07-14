@extends("theme.$theme.layout")
@section('titulo')
    Certificado {{ $data->etiqueta }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Certificado {{ $data->etiqueta }}</h3>
                <div class="card-tools">
                    <a href="{{route('consultar_certificado_sanitario')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
                    </a>
                    @if ($data->xml_frio)
                    <a href="{{route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'S'])}}" class="btn btn-outline-secondary btn-sm">XML frío</a>
                    @endif
                    @if ($data->xml_sin_frio)
                    <a href="{{route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => 'N'])}}" class="btn btn-outline-secondary btn-sm">XML sin frío</a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Fecha:</strong> {{ optional($data->fecha)->format('d/m/Y') }}</div>
                    <div class="col-md-4"><strong>Camión:</strong> {{ $data->camion->dominio ?? '' }} ({{ $data->camion->habilitacion ?? '' }})</div>
                    <div class="col-md-4"><strong>Temp.:</strong> {{ $data->temperatura }} · <strong>Precinto:</strong> {{ $data->precinto }}</div>
                </div>

                <h5>Artículos</h5>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Línea</th><th>SKU</th><th>Kilos</th><th>Cajas</th></tr></thead>
                    <tbody>
                        @foreach($data->articulos as $a)
                        <tr>
                            <td>{{ $a->linea }}</td>
                            <td>{{ $a->sku }}</td>
                            <td class="text-right">{{ number_format($a->cantidad, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($a->cajas, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <h5>Clientes</h5>
                <ul>
                    @foreach($data->clientes as $c)
                    <li>{{ $c->codigo_cliente }} {{ optional($c->cliente)->nombre }}</li>
                    @endforeach
                </ul>

                <h5>Destinos</h5>
                <ul>
                    @foreach($data->destinos as $d)
                    <li>{{ $d->localidad }} - {{ $d->provincia }} @if($d->patagonico) (patagónico) @endif</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
