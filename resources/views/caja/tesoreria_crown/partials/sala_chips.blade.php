@if (($salas ?? collect())->isNotEmpty() || ! empty($exportRuta))
    <div class="card-body py-2 border-bottom bg-white">
        <div class="d-flex flex-wrap align-items-center">
            @if (($salas ?? collect())->isNotEmpty())
                <span class="text-muted small mr-2"><i class="fa fa-building"></i> Sala:</span>
                <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Sala">
                    @foreach ($salas as $opcion)
                        <a href="{{ route($ruta, ['sala_id' => $opcion->id]) }}"
                           class="btn {{ $sala && (int) $sala->id === (int) $opcion->id ? 'btn-info' : 'btn-outline-info' }}">
                            {{ $opcion->codigo }} — {{ $opcion->nombre }}
                        </a>
                    @endforeach
                </div>
            @endif
            @if (! empty($exportRuta))
                <div class="ml-auto js-export-tesoreria-crown">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => $exportRuta,
                        'queryparams' => $exportQueryparams ?? $filtrosQuery ?? [],
                        'variant' => 'compact',
                    ])
                </div>
            @endif
        </div>
    </div>
@endif
