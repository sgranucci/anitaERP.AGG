@php
    $itemId = (int) ($itemId ?? $item_estacionamiento_id ?? 0);
    $nombre = trim((string) ($nombre ?? ''));
    if ($nombre === '' && $itemId > 0) {
        $nombre = '#'.$itemId;
    }
@endphp
@if ($itemId > 0 && can('editar-estacionamiento-item', false))
    <a href="{{ route('editar_estacionamiento_item', ['id' => $itemId]) }}"
       target="_blank"
       rel="noopener"
       class="text-primary"
       title="Consultar ítem de estacionamiento">{{ $nombre !== '' ? $nombre : '—' }}</a>
@else
    {{ $nombre !== '' ? $nombre : '—' }}
@endif
