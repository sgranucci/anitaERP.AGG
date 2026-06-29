@php
    $nombre = trim((string) ($estado ?? ''));
    $clase = 'badge badge-light';
    $texto = $nombre;

    switch ($nombre) {
        case 'BORRADOR':
            $clase = 'badge badge-secondary';
            break;
        case 'CONFIRMADA':
            $clase = 'badge badge-success';
            break;
        case 'ANULADA':
            $clase = 'badge badge-dark';
            break;
    }
@endphp
<span class="{{ $clase }}">{{ $texto }}</span>
