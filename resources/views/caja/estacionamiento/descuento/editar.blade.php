@extends("theme.$theme.layout")
@section('titulo')
    Descuentos estacionamiento
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}"></script>
<script>$(function () { if (typeof activa_eventos_consultacliente === 'function') activa_eventos_consultacliente(); });</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar descuento</h3>
                <div class="card-tools">
                    <a href="{{ route('estacionamiento_descuento') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_estacionamiento_descuento', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    @include('caja.estacionamiento.descuento.form')
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
@include('includes.ventas.modalconsultacliente')
@endsection
