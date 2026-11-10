@extends("theme.$theme.layout")
@section('titulo')
    Categor&iacute;as
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
@if (!empty($usaAreasCrown))
<script src="{{ asset('assets/pages/scripts/stock/categoria/form_areas_comanda.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/categoria/form_areas_comanda.js')) }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear Categor&iacute;a</h3>
                <div class="card-tools">
                    <a href="{{route('categoria')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_categoria')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    @if (!empty($usaAreasCrown))
                        @include('stock.categoria.partials.areas_comanda_crown')
                    @else
                        @include('stock.categoria.form')
                    @endif
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
