@extends("theme.$theme.layout")
@section('titulo')
    Tipos de Empresa
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tipos de Empresa</h3>
                <div class="card-tools">
                    <a href="{{route('tipoempresa_cliente')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('tipoempresa_cliente_actualizar', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method('PUT')
                <div class="card-body">
                    @include('ventas.tipoempresa_cliente.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-formulario', ['ruta' => 'tipoempresa_cliente'])
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
