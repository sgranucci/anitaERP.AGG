@extends("theme.$theme.layout")
@section('titulo')
    Planos de mesas
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/gastronomia-crown-plano-editor.css') }}?v={{ @filemtime(public_path('assets/css/gastronomia-crown-plano-editor.css')) }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="https://cdn.jsdelivr.net/npm/konva@9/konva.min.js"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia_crown/plano_editor.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia_crown/plano_editor.js')) }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Nuevo plano de mesas</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_plano_mesa_gastronomia_crown', $filtrosQuery ?? []) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_plano_mesa_gastronomia_crown') }}" id="form-general" class="form-horizontal" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @include('ventas.gastronomia_crown.plano_mesa.form')
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
