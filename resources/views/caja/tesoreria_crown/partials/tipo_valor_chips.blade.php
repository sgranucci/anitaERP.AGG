@if (($tipos ?? collect())->isNotEmpty())
    @php
        $rutaChips = $rutaChips ?? 'tesoreria_crown_valor';
        $paramTipo = $paramTipo ?? 'tipo_valor_id';
        $queryTipo = $filtrosQuery ?? [];
        unset($queryTipo[$paramTipo]);
        $tipoActivoChip = (int) (($filtros[$paramTipo] ?? 0));
    @endphp
    <div class="card-body py-2 border-bottom bg-white">
        <div class="d-flex flex-wrap align-items-center">
            <span class="text-muted small mr-2">Tipo:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="{{ $ariaChips ?? 'Tipo' }}">
                <a href="{{ route($rutaChips, $queryTipo) }}"
                   class="btn {{ $tipoActivoChip === 0 ? 'btn-info' : 'btn-outline-info' }}">
                    Todos
                </a>
                @foreach ($tipos as $tipo)
                    <a href="{{ route($rutaChips, array_merge($queryTipo, [$paramTipo => $tipo->id])) }}"
                       class="btn {{ $tipoActivoChip === (int) $tipo->id ? 'btn-info' : 'btn-outline-info' }}">
                        {{ $tipo->etiqueta() }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
