@php
    $idxDep = $h->depositos ?? null;
    $idxDepStr = $idxDep ? trim(($idxDep->codigo ?? '').' '.($idxDep->nombre ?? '')) : '';
    $idxExtras = [
        'Cant. '.number_format((float) ($h->cantidad ?? 0), 2, ',', '.'),
        'FC '.number_format((float) ($h->factorcosto ?? 0), 2, ',', '.'),
    ];
    if ($indexGastOpc ?? false) {
        $idxExtras[] = 'Opc. '.($h->esopcional ? 'Sí' : 'No');
        if ($h->esopcional && ($h->ordenopcional ?? '') !== '' && $h->ordenopcional !== null) {
            $idxExtras[] = 'Ord.opc. '.$h->ordenopcional;
        }
    }
    if ($idxDepStr !== '') {
        $idxExtras[] = 'Dep. '.$idxDepStr;
    }
    if (($indexTieneRanura ?? false) && ($h->ranura ?? '') !== '' && $h->ranura !== null) {
        $idxExtras[] = 'Ranura '.$h->ranura;
    }
    $idxExtrasStr = implode(' · ', $idxExtras);
@endphp
<div class="mb-1 @if(! $loop->last) border-bottom border-light pb-1 @endif">
    @if($h->articulo_id)
        <small>
            <a href="{{ route('editar_articulo', ['id' => $h->articulo_id, 'origen' => 'modal_consulta']) }}" class="text-primary" target="_blank" rel="noopener">{{ $h->articulos->sku ?? '' }}</a>
            <span class="text-muted"> — {{ $h->articulos->descripcion ?? '' }} · {{ $idxExtrasStr }}</span>
        </small>
    @elseif($h->formula_hija_id)
        @php
            $subArt = optional($h->formula_hija)->articulos;
            $fhIdx = $h->formula_hija;
            $idxSubExtras = $idxExtras;
            if ($fhIdx && trim((string) ($fhIdx->codigo ?? '')) !== '') {
                array_unshift($idxSubExtras, 'Cód. subf. '.$fhIdx->codigo);
            }
            $idxSubExtrasStr = implode(' · ', $idxSubExtras);
        @endphp
        <small>
            <a href="{{ route('editar_formula_articulo', ['id' => $h->formula_hija_id]) }}" target="_blank" rel="noopener">F&oacute;rmula #{{ $h->formula_hija_id }}</a>
            @if($subArt)
                <span class="text-muted"> —
                @if(! empty($fhIdx->articulo_id))
                    <a href="{{ route('editar_articulo', ['id' => $fhIdx->articulo_id, 'origen' => 'modal_consulta']) }}" class="text-primary" target="_blank" rel="noopener">{{ $subArt->sku ?? '' }}</a>
                @else
                    {{ $subArt->sku ?? '' }}
                @endif
                 — {{ $subArt->descripcion ?? '' }}</span>
            @else
                <span class="text-muted"> — <em>Sin art&iacute;culo cabecera en subf&oacute;rmula</em></span>
            @endif
            @if($fhIdx && trim((string) ($fhIdx->detalle ?? '')) !== '')
                <span class="text-muted"> — {{ $fhIdx->detalle }}</span>
            @endif
            <span class="text-muted"> · {{ $idxSubExtrasStr }}</span>
        </small>
    @endif
</div>
