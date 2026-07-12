@extends("theme.$theme.layout")
@section('titulo')
    Configuración terminal bingo
@endsection

@section("scripts")
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/admin/editar.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/bingo/configuracion_puntoventa/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/bingo/configuracion_puntoventa/form.js')) }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar configuración por PC</h3>
                <div class="card-tools">
                    <a href="{{ route('bingo_configuracion_puntoventa') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_bingo_configuracion_puntoventa', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf @method('PUT')
                <div class="card-body">
                    @include('caja.bingo.configuracion_puntoventa.form')
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
@include('includes.caja.modalconsultacuentacaja')
@endsection
