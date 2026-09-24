@extends("theme.$theme.layout")
@section('titulo')
    Puntos de Venta
@endsection

@section("scripts")
@php
    $formJs = public_path('assets/pages/scripts/ventas/puntoventa/form.js');
@endphp
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/localidad-cascada.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/localidad-cascada.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/domicilio.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/domicilio.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/puntoventa/form.js') }}?v={{ file_exists($formJs) ? filemtime($formJs) : time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('puntoventa', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear punto de venta</h3>
                <div class="card-tools">
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_puntoventa', $filtrosQuery ?? []) }}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('ventas.puntoventa.form')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
