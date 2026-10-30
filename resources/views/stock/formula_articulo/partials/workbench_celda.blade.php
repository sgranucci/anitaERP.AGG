@php
    use App\Support\Stock\FormulaArticuloListadoColumnas;
    $alineacion = $key === 'cantidadunidad' ? 'text-right' : '';
@endphp
<td class="{{ $alineacion }}">
    @if ($key === 'numero' || $key === 'id')
        <small class="text-monospace">{{ FormulaArticuloListadoColumnas::valorCelda($data, $key) }}</small>
        @if ($key === 'numero' || ($key === 'id' && ! in_array('numero', $columnasVisibles ?? [], true)))
            @include('stock.formula_articulo.partials.costo_total_index', ['formula' => $data])
        @endif
    @elseif ($key === 'sku')
        <small>
            @if (! empty($data->articulo_id))
                <a href="{{ route('editar_articulo', ['id' => $data->articulo_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}" class="text-primary" target="_blank" rel="noopener">{{ $data->articulo_sku ?? '' }}</a>
            @else
                <span class="text-muted">—</span>
            @endif
        </small>
    @elseif ($key === 'articulo')
        <small>
            @if (! empty($data->articulo_id))
                {{ $data->articulo_descripcion ?? '' }}
            @else
                <span class="text-muted">Sin artículo cabecera</span>
            @endif
        </small>
    @elseif ($key === 'detalle')
        <span class="small text-wrap" style="max-width: 14rem; display: inline-block;">
            @if (($data->detalle ?? '') !== '')
                {!! nl2br(e($data->detalle)) !!}
            @else
                <span class="text-muted">—</span>
            @endif
        </span>
    @elseif ($key === 'items')
        <div class="small">
            @forelse ($data->formula_articulo_hijos ?? [] as $h)
                @include('stock.formula_articulo.partials.index_linea_item', [
                    'h' => $h,
                    'loop' => $loop,
                    'indexGastOpc' => $indexGastOpc ?? false,
                    'indexTieneRanura' => $indexTieneRanura ?? false,
                    'indexTienePermitido' => $indexTienePermitido ?? false,
                ])
            @empty
                <span class="text-muted">—</span>
            @endforelse
        </div>
    @else
        <small>{{ FormulaArticuloListadoColumnas::valorCelda($data, $key) }}</small>
    @endif
</td>
