@extends("theme.$theme.layout")
@section('titulo')
    Corridas de liquidaci&oacute;n
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar corrida N&deg; {{ $data->numero }} <span class="badge badge-info">{{ $data->estadoLabel() }}</span></h3>
                <div class="card-tools">
                    @if (can('listar-novedad-sueldos', false))
                        <a href="{{ route('novedades_liquidacion_sueldos', ['id' => $data->id]) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-bolt"></i> Novedades
                        </a>
                    @endif
                    <a href="{{route('consultar_liquidacion_sueldos')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('actualizar_liquidacion_sueldos', ['id' => $data->id])}}" id="form-general" class="form-horizontal" method="POST" autocomplete="off">
                @csrf @method('PUT')
                <div class="card-body">
                    @include('sueldos.liquidacion.form', ['data' => $data])
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-editar')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
