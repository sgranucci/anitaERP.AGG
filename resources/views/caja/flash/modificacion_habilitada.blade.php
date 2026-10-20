@extends("theme.$theme.layout")
@section('titulo')
    Flash habilitado
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-8">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Modificación de flash habilitada</h3>
            </div>
            <div class="card-body">
                @php
                    $flash = $solicitud->flash;
                    $fecha = $flash?->fecha?->format('d/m/Y') ?? '';
                    $empresa = (string) ($flash?->empresa?->nombre ?? '');
                @endphp
                <p>
                    El flash del <strong>{{ $fecha }}</strong>
                    @if ($empresa !== '')
                        de <strong>{{ $empresa }}</strong>
                    @endif
                    quedó habilitado para modificar.
                </p>
                <p class="mb-0">Quien pidió el cambio ya puede guardarlo. Al guardar se quita el tilde verde.</p>
            </div>
        </div>
    </div>
</div>
@endsection
