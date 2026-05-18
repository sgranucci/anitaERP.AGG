@extends("theme.$theme.layout")
@section('titulo')
Centro de ayuda
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-10 offset-lg-1">
        @include('includes.mensaje')
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-book-open mr-2"></i>Centro de ayuda — Manuales de usuario</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    Documentación oficial del sistema. Seleccione el módulo que desea consultar.
                </p>
                <div class="row">
                    @foreach ($manuales as $manual)
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100 shadow-sm {{ $manual['disponible'] ? '' : 'bg-light' }}">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">
                                        <i class="fas {{ $manual['icono'] }} text-primary mr-2"></i>{{ $manual['modulo'] }}
                                    </h5>
                                    <p class="card-text text-muted small flex-grow-1">{{ $manual['descripcion'] }}</p>
                                    @if ($manual['disponible'])
                                        <a href="{{ $manual['url'] }}" class="btn btn-primary btn-sm mt-2" target="_blank" rel="noopener">
                                            <i class="fas fa-external-link-alt"></i> Abrir manual
                                        </a>
                                    @else
                                        <span class="badge badge-secondary mt-2">Próximamente</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
