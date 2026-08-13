@extends("theme.$theme.layout")
@section('titulo')
    XML {{ $data->etiqueta }} {{ $tipo === 'S' ? 'frío' : 'sin frío' }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $nombre }} — certificado {{ $data->etiqueta }}</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_certificado_sanitario') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver
                    </a>
                    <a href="{{ route('ver_certificado_sanitario', ['id' => $data->id]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-eye"></i> Ver certificado
                    </a>
                    <a href="{{ route('descargar_certificado_sanitario_xml', ['id' => $data->id, 'tipo' => $tipo]) }}"
                        class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-download"></i> Descargar ZIP SENASA
                    </a>
                </div>
            </div>
            <div class="card-body p-2">
                <pre class="bg-light border p-2 mb-0" style="max-height: 70vh; overflow: auto; white-space: pre-wrap;">{{ $contenido }}</pre>
            </div>
        </div>
    </div>
</div>
@endsection
