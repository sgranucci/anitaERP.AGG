@extends("theme.$theme.layout")
@section('titulo')
Índice de manuales
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-10 offset-lg-1">
        @include('includes.mensaje')
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-th-list mr-2"></i>Índice de manuales</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    Manuales disponibles por módulo. Use <strong>Abrir manual completo</strong> para consultar la documentación.
                    El resumen de cada módulo se muestra solo si lo solicita.
                </p>
                <div class="row">
                    @foreach ($manuales as $manual)
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100 shadow-sm {{ $manual['disponible'] ? '' : 'bg-light' }}">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">
                                        <i class="fas {{ $manual['icono'] }} text-primary mr-2"></i>{{ $manual['modulo'] }}
                                    </h5>
                                    @if ($manual['disponible'])
                                        <button
                                            type="button"
                                            class="btn btn-link btn-sm p-0 text-left mb-2 align-self-start"
                                            data-toggle="collapse"
                                            data-target="#bajada-manual-{{ $loop->index }}"
                                            aria-expanded="false"
                                            aria-controls="bajada-manual-{{ $loop->index }}"
                                        >
                                            <i class="fas fa-chevron-down mr-1"></i> Ver resumen del módulo
                                        </button>
                                        <div id="bajada-manual-{{ $loop->index }}" class="collapse">
                                            <p class="card-text text-muted small">{{ $manual['bajada'] }}</p>
                                        </div>
                                        <a href="{{ $manual['url'] }}" class="btn btn-primary btn-sm mt-auto" target="_blank" rel="noopener">
                                            <i class="fas fa-book-open"></i> Abrir manual completo
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
