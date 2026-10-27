@extends("theme.$theme.layout")

@section('titulo')
Inicio
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (in_array(config('app.empresa'), ['AGG', 'EL BIERZO', 'CROWN'], true))
            @php
                $logoInicio = match (config('app.empresa')) {
                    'EL BIERZO' => 'logo-bierzo.png',
                    'CROWN' => 'CROWN.jpg',
                    default => config('app.empresa').'.png',
                };
            @endphp
            <div class="text-center py-4">
                <img src="{{ asset('storage/imagenes/logos/'.$logoInicio) }}?v={{ @filemtime(public_path('storage/imagenes/logos/'.$logoInicio)) ?: time() }}" alt="{{ config('app.empresa') }}" class="img-fluid" style="max-height: 320px;">
            </div>
        @endif
    </div>
</div>
@endsection
