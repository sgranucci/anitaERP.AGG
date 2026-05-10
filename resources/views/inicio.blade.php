@extends("theme.$theme.layout")

@section('titulo')
Inicio
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (config('app.empresa') == 'AGG')
            <div class="text-center py-4">
                <img src="{{ asset('storage/imagenes/logos/'.config('app.empresa').'.png') }}" alt="AGG" class="img-fluid" style="max-height: 160px;">
            </div>
        @endif
    </div>
</div>
@endsection
